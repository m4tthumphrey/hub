<?php

namespace Tests\Unit;

use App\Services\Wealth\MortgageCalculator;
use PHPUnit\Framework\TestCase;

class MortgageCalculatorTest extends TestCase
{
    private function inputs(array $overrides = []): array
    {
        return array_merge([
            'effective_date'      => '2026-11-15',
            'balance'             => 248500,
            'interest_rate'       => 4.29,
            'term_months'         => 256,
            'fix_end_date'        => '2028-03-31',
            'follow_on_rate'      => null,
            'regular_overpayment' => 0,
            'actual_payment'      => null,
            'one_offs'            => [],
        ], $overrides);
    }

    public function test_monthly_payment_matches_the_standard_formula(): void
    {
        $this->assertSame(1483.34, MortgageCalculator::payment(248500, 4.29, 256));
        // Textbook check: £100,000 at 6% over 25 years.
        $this->assertSame(644.30, MortgageCalculator::payment(100000, 6, 300));
        $this->assertSame(1000.00, MortgageCalculator::payment(12000, 0, 12));
    }

    public function test_without_overpayments_the_loan_runs_its_full_term(): void
    {
        $f = (new MortgageCalculator)->forecast($this->inputs());

        $this->assertSame(1483.34, $f['monthly_payment']);
        $this->assertSame(256, $f['months']);
        $this->assertSame('2048-03-15', $f['payoff_date']);
        $this->assertSame('2048-03-15', $f['contractual_end_date']);
        $this->assertSame(0, $f['months_saved']);
        $this->assertEqualsWithDelta(131236, $f['interest_remaining'], 30);
    }

    public function test_overpayments_shorten_the_term_and_save_interest(): void
    {
        $f = (new MortgageCalculator)->forecast($this->inputs([
            'regular_overpayment' => 250,
            'one_offs'            => [['paid_on' => '2027-01-15', 'amount' => 5000]],
        ]));

        $this->assertSame(1483.34, $f['monthly_payment']);
        // Closed form: 248,500 at 4.29% paying 1,733.34 takes 201.3 months; the 5,000 one-off removes about five more.
        $this->assertSame('2043-03-15', $f['payoff_date']);
        $this->assertSame(60, $f['months_saved']);
        $this->assertGreaterThan(30000, $f['interest_saved']);
        $this->assertEqualsWithDelta(229356, $f['balance_at_fix_end'], 60);
        $this->assertSame(0.0, end($f['schedule'])[1]);
    }

    public function test_one_offs_on_or_before_the_effective_date_are_already_in_the_balance(): void
    {
        $calc = new MortgageCalculator;

        $this->assertSame(
            $calc->forecast($this->inputs())['payoff_date'],
            $calc->forecast($this->inputs(['one_offs' => [['paid_on' => '2026-11-15', 'amount' => 5000]]]))['payoff_date'],
        );
    }

    public function test_follow_on_rate_changes_the_payment_after_the_fix(): void
    {
        $calc   = new MortgageCalculator;
        $same   = $calc->forecast($this->inputs());
        $higher = $calc->forecast($this->inputs(['follow_on_rate' => 6.5]));

        // Recalculated over the same remaining term, so the end date holds and the interest rises.
        $this->assertSame($same['payoff_date'], $higher['payoff_date']);
        $this->assertGreaterThan($same['interest_remaining'], $higher['interest_remaining']);
    }

    public function test_overpaying_never_lowers_the_payment_when_the_rate_changes(): void
    {
        $calc = new MortgageCalculator;
        $over = ['regular_overpayment' => 250];

        $same   = $calc->forecast($this->inputs($over));
        $nudged = $calc->forecast($this->inputs($over + ['follow_on_rate' => 4.30]));

        // A tiny rate change must only nudge the end date, not reset the term.
        $this->assertLessThanOrEqual(1, abs($same['months'] - $nudged['months']));
    }

    public function test_lenders_actual_payment_overrides_the_calculated_one(): void
    {
        $f = (new MortgageCalculator)->forecast($this->inputs(['actual_payment' => 1700]));

        $this->assertSame(1700.0, $f['monthly_payment']);
        $this->assertSame(1483.34, $f['calculated_payment']);
        $this->assertLessThan(256, $f['months']);
    }

    public function test_balance_projection_between_saves(): void
    {
        $calc = new MortgageCalculator;
        $in   = $this->inputs([
            'regular_overpayment' => 250,
            'one_offs'            => [['paid_on' => '2027-01-20', 'amount' => 5000]],
        ]);

        $this->assertSame(248500.0, $calc->balanceOn($in, '2026-11-15'));
        $this->assertSame(248500.0, $calc->balanceOn($in, '2026-12-14'));

        // One payment: 248500 + 888.39 interest - 1733.34.
        $this->assertSame(247655.05, $calc->balanceOn($in, '2026-12-15'));

        // Two payments by 15 Jan, then the one-off lands on the 20th.
        $afterTwo = $calc->balanceOn($in, '2027-01-19');
        $this->assertSame(round($afterTwo - 5000, 2), $calc->balanceOn($in, '2027-01-20'));
    }

    public function test_a_payment_that_does_not_cover_interest_never_pays_off(): void
    {
        $f = (new MortgageCalculator)->forecast($this->inputs(['actual_payment' => 500]));

        $this->assertNull($f['payoff_date']);
        $this->assertNull($f['months_saved']);
    }
}
