<?php

namespace App\Services\Wealth;

use App\Models\Wealth\Liability;
use App\Models\Wealth\LiabilityRecord;
use App\Models\Wealth\LiabilityValue;
use Carbon\CarbonImmutable;

class LiabilityService
{
    public function __construct(private MortgageCalculator $calculator) {}

    public static function rules(): array
    {
        return [
            'effective_date'      => ['required', 'date', 'before_or_equal:today'],
            'balance'             => ['required', 'numeric', 'min:0', 'max:99999999'],
            'interest_rate'       => ['required', 'numeric', 'min:0', 'max:50'],
            'term_months'         => ['required', 'integer', 'min:1', 'max:600'],
            'fix_end_date'        => ['nullable', 'date'],
            'follow_on_rate'      => ['nullable', 'numeric', 'min:0', 'max:50'],
            'regular_overpayment' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'actual_payment'      => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ];
    }

    /** Forecast for values that have not been saved, using the liability's current one-offs. */
    public function preview(?Liability $liability, array $data): array
    {
        $forecast = $this->calculator->forecast($this->normalise($data) + [
            'one_offs' => $liability ? $this->currentOneOffs($liability) : [],
        ]);

        unset($forecast['schedule'], $forecast['schedule_contractual']);

        return $forecast;
    }

    /**
     * Every save appends a record holding the full set of entered values, the one-offs
     * as they stand, and the results calculated from them. Nothing is ever overwritten.
     */
    public function saveRecord(Liability $liability, array $data, ?string $note = null): LiabilityRecord
    {
        $inputs   = $this->normalise($data) + ['one_offs' => $this->currentOneOffs($liability)];
        $previous = $liability->records()->first();
        $forecast = $this->calculator->forecast($inputs);

        $projected = $previous
            ? $this->calculator->balanceOn($this->inputs($previous), $inputs['effective_date'])
            : null;

        $record = $liability->records()->create($inputs + [
            'monthly_payment'      => $forecast['monthly_payment'],
            'calculated_payment'   => $forecast['calculated_payment'],
            'payoff_date'          => $forecast['payoff_date'],
            'contractual_end_date' => $forecast['contractual_end_date'],
            'interest_remaining'   => $forecast['interest_remaining'],
            'interest_saved'       => $forecast['interest_saved'],
            'balance_at_fix_end'   => $forecast['balance_at_fix_end'],
            'projected_balance'    => $projected,
            'change_note'          => $this->describeChange($previous, $inputs, $projected, $note),
        ]);

        $liability->unsetRelation('records');

        // The log from this record's date onwards now follows the new projection.
        $this->syncLog($liability, rebuild: true);

        return $record;
    }

    /**
     * Keep the balance log up to date: the entered balance on the current record's "as of" date,
     * then the projected balance on each payment date that has passed. Existing rows are left
     * alone unless $rebuild is set, which re-projects everything after the current record.
     * Returns the number of rows added.
     */
    public function syncLog(Liability $liability, bool $rebuild = false): int
    {
        $record = $liability->records()->first();

        if (! $record) {
            return 0;
        }

        $from   = $record->effective_date->toDateString();
        $today  = CarbonImmutable::today()->toDateString();
        $anchor = ['balance' => $record->balance, 'projected_balance' => $record->projected_balance, 'is_manual' => true];

        if ($rebuild) {
            $liability->values()->where('date', '>', $from)->delete();
            $liability->values()->updateOrCreate(['date' => $from], $anchor);
        } else {
            $liability->values()->firstOrCreate(['date' => $from], $anchor);
        }

        $logged = $liability->values()->where('date', '>', $from)->get()->map(fn ($v) => $v->date->toDateString())->all();
        $added  = 0;

        foreach ($this->calculator->forecast($this->inputs($record))['schedule'] as [$date, $balance]) {
            if ($date > $from && $date <= $today && ! in_array($date, $logged)) {
                $liability->values()->create(['date' => $date, 'balance' => $balance, 'projected_balance' => $balance]);
                $added++;
            }
        }

        $liability->unsetRelation('values');

        return $added;
    }

    /**
     * Correct a logged balance. The correction is saved as a new record dated that day,
     * so the projection (and every later log entry) continues from the corrected figure.
     */
    public function correctValue(Liability $liability, LiabilityValue $value, float $balance): LiabilityRecord
    {
        $record   = $liability->records()->first();
        $schedule = $this->calculator->forecast($this->inputs($record))['schedule'];
        $elapsed  = $this->paymentsMade($schedule, CarbonImmutable::parse($value->date));

        return $this->saveRecord($liability, [
            ...$this->inputs($record),
            'effective_date' => $value->date->toDateString(),
            'balance'        => $balance,
            'term_months'    => max(1, $record->term_months - $elapsed),
            // The payment is contractual: a corrected balance must not recalculate it.
            'actual_payment' => $record->monthly_payment,
        ], sprintf('Logged balance for %s corrected.', $value->date->format('j M Y')));
    }

    /**
     * The forecast if, from today, the regular overpayment were different and/or a lump sum were paid.
     * Everything up to today stays as recorded. Nothing is saved.
     */
    public function whatIf(Liability $liability, ?float $regularOverpayment, ?float $lumpSum): ?array
    {
        $current = $this->state($liability);

        if (! $current['forecast']) {
            return null;
        }

        $today  = CarbonImmutable::today();
        $inputs = $this->inputs($liability->records->first());

        if ($regularOverpayment !== null) {
            $inputs['overpayment_change'] = ['from' => $today->toDateString(), 'amount' => $regularOverpayment];
        }

        if ($lumpSum) {
            $inputs['one_offs'][] = ['paid_on' => $today->addDay()->toDateString(), 'amount' => $lumpSum];
        }

        $forecast = $this->calculator->forecast($inputs);
        $elapsed  = $this->paymentsMade($forecast['schedule'], $today);
        $paidOff  = $forecast['payoff_date'] !== null;
        $months   = $paidOff ? max(0, $forecast['months'] - $elapsed) : null;
        $interest = round($forecast['interest_remaining'] - $forecast['schedule'][$elapsed][2], 2);

        return [
            'payoff_date'        => $forecast['payoff_date'],
            'months_remaining'   => $months,
            'interest_remaining' => $interest,
            'balance_at_fix_end' => $forecast['balance_at_fix_end'],
            // Against the forecast as it stands today.
            'months_sooner'      => $paidOff && $current['forecast']['months_remaining'] !== null
                ? $current['forecast']['months_remaining'] - $months
                : null,
            'interest_saved'     => $paidOff ? round($current['forecast']['interest_remaining'] - $interest, 2) : null,
            'schedule'           => array_map(fn ($row) => [$row[0], $row[1]], $forecast['schedule']),
        ];
    }

    /** Projected balance on a date, from the record in force on that date. Null before the first record. */
    public function balanceOn(Liability $liability, CarbonImmutable $date): ?float
    {
        $record = $liability->records->first(fn (LiabilityRecord $r) => $r->effective_date->lte($date));

        return $record ? $this->calculator->balanceOn($this->inputs($record), $date) : null;
    }

    public function state(Liability $liability): array
    {
        $today  = CarbonImmutable::today();
        $record = $liability->records->first();

        $state = [
            'id'                   => $liability->id,
            'type'                 => $liability->type,
            'description'          => $liability->description,
            'property_source_id'   => $liability->property_source_id,
            'record'               => $record,
            'balance_today'        => null,
            'forecast'             => null,
            'schedule'             => [],
            'schedule_contractual' => [],
            'form'                 => null,
            'records'              => $liability->records->sortByDesc('id')->values(),
            // Entries since the current record can be corrected; earlier ones are history.
            'values'               => $liability->values->map(fn (LiabilityValue $v) => [
                'id'                => $v->id,
                'date'              => $v->date->toDateString(),
                'balance'           => $v->balance,
                'projected_balance' => $v->projected_balance,
                'is_manual'         => $v->is_manual,
                'editable'          => $record && $v->date->gte($record->effective_date),
            ])->values(),
            'overpayments'         => $liability->overpayments->map(fn ($o) => [
                'id'      => $o->id,
                'paid_on' => $o->paid_on->toDateString(),
                'amount'  => $o->amount,
                'note'    => $o->note,
                'status'  => match (true) {
                    $record && $o->paid_on->lte($record->effective_date) => 'included',
                    $o->paid_on->lte($today)                             => 'paid',
                    default                                              => 'planned',
                },
            ])->values(),
        ];

        if (! $record) {
            return $state;
        }

        $inputs   = $this->inputs($record);
        $forecast = $this->calculator->forecast($inputs);
        $balance  = $this->calculator->balanceOn($inputs, $today);

        $elapsed = $this->paymentsMade($forecast['schedule'], $today);

        $contractualToday = $forecast['schedule_contractual'][min($elapsed, count($forecast['schedule_contractual']) - 1)][1];

        $state['balance_today'] = $balance;
        $state['forecast']      = [
            'monthly_payment'      => $forecast['monthly_payment'],
            'calculated_payment'   => $forecast['calculated_payment'],
            'payoff_date'          => $forecast['payoff_date'],
            'contractual_end_date' => $forecast['contractual_end_date'],
            'months_remaining'     => $forecast['payoff_date'] ? max(0, $forecast['months'] - $elapsed) : null,
            'months_saved'         => $forecast['months_saved'],
            'interest_remaining'   => round($forecast['interest_remaining'] - $forecast['schedule'][$elapsed][2], 2),
            'interest_saved'       => $forecast['interest_saved'],
            'balance_at_fix_end'   => $forecast['balance_at_fix_end'],
        ];

        $thin = fn (array $schedule) => array_map(fn ($row) => [$row[0], $row[1]], $schedule);

        $state['schedule']             = $thin($forecast['schedule']);
        $state['schedule_contractual'] = $thin($forecast['schedule_contractual']);

        // Starting values for the next save: today's projected position.
        $state['form'] = [
            'effective_date'      => $today->toDateString(),
            'balance'             => $balance,
            'interest_rate'       => $record->interest_rate,
            'term_months'         => max(1, $record->term_months - $elapsed),
            'fix_end_date'        => $record->fix_end_date?->toDateString(),
            'follow_on_rate'      => $record->follow_on_rate,
            'regular_overpayment' => $record->regular_overpayment,
            // Once overpayments have pulled the balance below the contractual path, recalculating
            // from balance and term would understate the payment, so carry the real one forward.
            'actual_payment' => $record->actual_payment
                ?? ($balance < $contractualToday - 1 ? $record->monthly_payment : null),
        ];

        return $state;
    }

    /** How many scheduled payments have fallen due by a date (the schedule's first row is the starting balance). */
    private function paymentsMade(array $schedule, CarbonImmutable $date): int
    {
        $due = count(array_filter($schedule, fn ($row) => $row[0] <= $date->toDateString())) - 1;

        return max(0, min($due, count($schedule) - 1));
    }

    public function inputs(LiabilityRecord $record): array
    {
        return [
            'effective_date'      => $record->effective_date->toDateString(),
            'balance'             => $record->balance,
            'interest_rate'       => $record->interest_rate,
            'term_months'         => $record->term_months,
            'fix_end_date'        => $record->fix_end_date?->toDateString(),
            'follow_on_rate'      => $record->follow_on_rate,
            'regular_overpayment' => $record->regular_overpayment,
            'actual_payment'      => $record->actual_payment,
            'one_offs'            => $record->one_offs ?? [],
        ];
    }

    private function normalise(array $data): array
    {
        $number = fn ($key) => isset($data[$key]) && $data[$key] !== '' ? (float) $data[$key] : null;

        return [
            'effective_date'      => CarbonImmutable::parse($data['effective_date'])->toDateString(),
            'balance'             => round((float) $data['balance'], 2),
            'interest_rate'       => (float) $data['interest_rate'],
            'term_months'         => (int) $data['term_months'],
            'fix_end_date'        => ! empty($data['fix_end_date']) ? CarbonImmutable::parse($data['fix_end_date'])->toDateString() : null,
            'follow_on_rate'      => $number('follow_on_rate'),
            'regular_overpayment' => $number('regular_overpayment') ?? 0.0,
            'actual_payment'      => $number('actual_payment') ?: null,
        ];
    }

    private function currentOneOffs(Liability $liability): array
    {
        return $liability->overpayments()->reorder('paid_on')->get()->map(fn ($o) => [
            'paid_on' => $o->paid_on->toDateString(),
            'amount'  => $o->amount,
            'note'    => $o->note,
        ])->all();
    }

    private function describeChange(?LiabilityRecord $previous, array $new, ?float $projected, ?string $note): string
    {
        if (! $previous) {
            return trim('First record. '.$note);
        }

        $changes = $note ? [$note] : [];
        $money   = fn (float $v) => '£'.number_format($v, fmod($v, 1) == 0.0 ? 0 : 2);
        $rate    = fn (?float $v) => $v === null ? 'none' : rtrim(rtrim(number_format($v, 3), '0'), '.').'%';
        $term    = fn (int $m) => intdiv($m, 12).'y '.($m % 12).'m';
        $date    = fn (?string $d) => $d ? CarbonImmutable::parse($d)->format('j M Y') : 'none';

        if ($projected !== null && abs($new['balance'] - $projected) >= 0.01) {
            $diff      = $new['balance'] - $projected;
            $changes[] = sprintf(
                'Balance entered as %s, %s %s the projection of %s.',
                $money($new['balance']), $money(abs($diff)), $diff < 0 ? 'below' : 'above', $money($projected),
            );
        }

        if ($new['interest_rate'] != $previous->interest_rate) {
            $changes[] = "Rate {$rate($previous->interest_rate)} → {$rate($new['interest_rate'])}.";
        }

        $elapsed  = (int) floor($previous->effective_date->diffInMonths($new['effective_date']));
        $expected = max(1, $previous->term_months - max(0, $elapsed));
        if ($new['term_months'] !== $expected) {
            $changes[] = "Term left {$term($expected)} → {$term($new['term_months'])}.";
        }

        if ($new['fix_end_date'] !== $previous->fix_end_date?->toDateString()) {
            $changes[] = "Fix end date {$date($previous->fix_end_date?->toDateString())} → {$date($new['fix_end_date'])}.";
        }

        if ($new['follow_on_rate'] != $previous->follow_on_rate) {
            $changes[] = "Rate after fix {$rate($previous->follow_on_rate)} → {$rate($new['follow_on_rate'])}.";
        }

        if ($new['regular_overpayment'] != $previous->regular_overpayment) {
            $changes[] = "Regular overpayment {$money($previous->regular_overpayment)} → {$money($new['regular_overpayment'])}.";
        }

        // Carrying the same payment forward explicitly is not a change.
        $samePayment = $new['actual_payment'] == $previous->monthly_payment && ! $previous->actual_payment;

        if ($new['actual_payment'] != $previous->actual_payment && ! $samePayment) {
            $from      = $previous->actual_payment ? $money($previous->actual_payment) : 'calculated';
            $to        = $new['actual_payment'] ? $money($new['actual_payment']) : 'calculated';
            $changes[] = "Lender's payment {$from} → {$to}.";
        }

        return $changes ? implode(' ', $changes) : 'No changes.';
    }
}
