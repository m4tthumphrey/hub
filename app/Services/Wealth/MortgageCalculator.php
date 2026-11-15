<?php

namespace App\Services\Wealth;

use Carbon\CarbonImmutable;

/**
 * Repayment maths for mortgages and other amortising debts.
 *
 * Inputs are a plain array (the fields stored on a liability record):
 *   effective_date, balance, interest_rate (annual %), term_months,
 *   fix_end_date?, follow_on_rate?, regular_overpayment?, actual_payment?,
 *   overpayment_change? => ['from' => 'Y-m-d', 'amount' => float]  (a different regular overpayment for payments after that date),
 *   one_offs? => [['paid_on' => 'Y-m-d', 'amount' => float], ...]
 *
 * Interest is added monthly. Overpayments shorten the term; they never
 * reduce the contractual payment.
 */
class MortgageCalculator
{
    public const MAX_MONTHS = 1200;

    public static function payment(float $balance, float $annualRate, int $months): float
    {
        $months = max(1, $months);
        $r      = $annualRate / 1200;

        if ($r == 0.0) {
            return round($balance / $months, 2);
        }

        return round($balance * $r / (1 - (1 + $r) ** -$months), 2);
    }

    public function forecast(array $in): array
    {
        $base = $this->run($in, false);
        $with = $this->run($in, true, null, $base['post_fix_payment']);

        return [
            'monthly_payment'      => $with['payment'],
            'calculated_payment'   => $with['calculated'],
            'payoff_date'          => $with['payoff_date'],
            'contractual_end_date' => $base['payoff_date'],
            'months'               => $with['months'],
            'months_saved'         => $with['payoff_date'] && $base['payoff_date'] ? $base['months'] - $with['months'] : null,
            'interest_remaining'   => $with['interest'],
            'interest_saved'       => $with['payoff_date'] && $base['payoff_date'] ? round($base['interest'] - $with['interest'], 2) : null,
            'balance_at_fix_end'   => $with['balance_at_fix_end'],
            'schedule'             => $with['schedule'],
            'schedule_contractual' => $base['schedule'],
        ];
    }

    /** Projected balance on a given date, including overpayments made by then. */
    public function balanceOn(array $in, CarbonImmutable|string $date): float
    {
        $date  = CarbonImmutable::parse($date)->startOfDay();
        $start = CarbonImmutable::parse($in['effective_date'])->startOfDay();

        if ($date->lte($start)) {
            return round((float) $in['balance'], 2);
        }

        $postFixPayment = $this->run($in, false)['post_fix_payment'];

        return $this->run($in, true, $date, $postFixPayment)['balance'];
    }

    /**
     * $postFixPayment is the contractual payment after the fix ends, taken from the
     * run without overpayments, so that overpaying never lowers the payment.
     */
    private function run(array $in, bool $overpayments, ?CarbonImmutable $until = null, ?float $postFixPayment = null): array
    {
        $start      = CarbonImmutable::parse($in['effective_date'])->startOfDay();
        $balance    = round((float) $in['balance'], 2);
        $annual     = (float) $in['interest_rate'];
        $term       = max(1, (int) $in['term_months']);
        $calculated = self::payment($balance, $annual, $term);
        $actual     = (float) ($in['actual_payment'] ?? 0);
        $payment    = $actual > 0 ? $actual : $calculated;

        // A lender payment higher or lower than the calculated one implies a different real term.
        if ($actual > 0) {
            $term = $this->impliedTerm($balance, $annual, $actual) ?? $term;
        }

        $fixEnd = ! empty($in['fix_end_date']) ? CarbonImmutable::parse($in['fix_end_date'])->startOfDay() : null;
        $follow = isset($in['follow_on_rate']) && $in['follow_on_rate'] !== '' && $in['follow_on_rate'] !== null
            ? (float) $in['follow_on_rate']
            : $annual;

        $regular = $overpayments ? (float) ($in['regular_overpayment'] ?? 0) : 0.0;
        $change  = $overpayments ? ($in['overpayment_change'] ?? null) : null;
        $changeFrom = $change ? CarbonImmutable::parse($change['from'])->startOfDay() : null;
        $oneOffs = [];
        if ($overpayments) {
            foreach ($in['one_offs'] ?? [] as $oneOff) {
                $oneOffs[] = [CarbonImmutable::parse($oneOff['paid_on'])->startOfDay(), (float) $oneOff['amount']];
            }
        }

        $rate        = $annual / 1200;
        $switched    = false;
        $atFixEnd    = null;
        $interest    = 0.0;
        $months      = 0;
        $payoffDate  = null;
        $newPayment  = null;
        $periodStart = $start;
        $schedule    = [[$start->toDateString(), $balance, 0.0]];

        $applyOneOffs = function (CarbonImmutable $after, CarbonImmutable $upTo) use (&$balance, $oneOffs) {
            foreach ($oneOffs as [$paidOn, $amount]) {
                if ($paidOn->gt($after) && $paidOn->lte($upTo)) {
                    $balance = max(0.0, round($balance - $amount, 2));
                }
            }
        };

        while ($balance > 0 && $months < self::MAX_MONTHS) {
            $date = $start->addMonthsNoOverflow($months + 1);

            if ($until && $date->gt($until)) {
                break;
            }

            $months++;

            if ($fixEnd && ! $switched && $date->gt($fixEnd)) {
                $switched = true;
                $atFixEnd = $balance;

                if ($follow !== $annual) {
                    $rate       = $follow / 1200;
                    $payment    = $postFixPayment ?? self::payment($balance, $follow, max(1, $term - ($months - 1)));
                    $newPayment = $payment;
                }
            }

            $applyOneOffs($periodStart, $date);
            $periodStart = $date;

            if ($balance > 0) {
                $monthInterest = round($balance * $rate, 2);

                $due = $payment + ($changeFrom && $date->gt($changeFrom) ? (float) $change['amount'] : $regular);

                if ($due <= $monthInterest) {
                    // Payments don't cover the interest: the debt is never repaid.
                    $months = self::MAX_MONTHS;
                    break;
                }

                $interest += $monthInterest;
                $balance = round($balance + $monthInterest - min($balance + $monthInterest, $due), 2);

                // A few pounds left over from rounding are settled with the final payment.
                if ($balance < 5) {
                    $balance = 0.0;
                }
            }

            $schedule[] = [$date->toDateString(), $balance, round($interest, 2)];

            if ($balance <= 0) {
                $payoffDate = $date->toDateString();
            }
        }

        if ($until && $balance > 0) {
            $applyOneOffs($periodStart, $until);
        }

        return [
            'payment'            => $payment,
            'calculated'         => $calculated,
            'months'             => $months,
            'payoff_date'        => $payoffDate,
            'interest'           => round($interest, 2),
            'balance'            => $balance,
            'balance_at_fix_end' => $atFixEnd,
            'post_fix_payment'   => $newPayment,
            'schedule'           => $schedule,
        ];
    }

    private function impliedTerm(float $balance, float $annualRate, float $payment): ?int
    {
        $r = $annualRate / 1200;

        if ($r == 0.0) {
            return (int) ceil($balance / $payment);
        }

        if ($payment <= $balance * $r) {
            return null;
        }

        return (int) ceil(-log(1 - $balance * $r / $payment) / log(1 + $r));
    }
}
