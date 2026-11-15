<?php

namespace Tests\Feature;

use App\Models\Wealth\Liability;
use App\Models\Wealth\LiabilityRecord;
use App\Models\Wealth\LiabilityValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiabilitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class);
        $this->travelTo('2026-11-15 10:00:00');
    }

    private function details(array $overrides = []): array
    {
        return array_merge([
            'effective_date'      => '2026-11-15',
            'balance'             => 248500,
            'interest_rate'       => 4.29,
            'term_months'         => 256,
            'fix_end_date'        => '2028-03-31',
            'follow_on_rate'      => null,
            'regular_overpayment' => 250,
            'actual_payment'      => null,
        ], $overrides);
    }

    private function mortgage(): Liability
    {
        return Liability::where('type', 'mortgage')->firstOrFail();
    }

    private function addLiquidSource(int $amount): void
    {
        $category = DB::table('wealth_source_categories')->insertGetId(['name' => 'Savings', 'created_at' => now(), 'updated_at' => now()]);
        $source   = DB::table('wealth_sources')->insertGetId([
            'category_id'    => $category, 'description' => 'Savings', 'who' => 'Matt',
            'current_amount' => $amount, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('wealth_source_values')->insert(['source_id' => $source, 'value' => $amount, 'created_at' => now()]);
    }

    public function test_migrations_seed_the_house_and_an_empty_mortgage(): void
    {
        $this->assertDatabaseHas('wealth_sources', ['description' => 'House', 'current_amount' => 475000]);

        $state = $this->getJson('/wealth/liabilities')->assertOk()->json('liabilities.0');

        $this->assertSame('mortgage', $state['type']);
        $this->assertNotNull($state['property_source_id']);
        $this->assertNull($state['record']);
        $this->assertNull($state['forecast']);
    }

    public function test_saving_details_stores_inputs_and_results_together(): void
    {
        $this->postJson("/wealth/liabilities/{$this->mortgage()->id}/records", $this->details())->assertOk();

        $record = LiabilityRecord::sole();
        $this->assertSame(248500.0, $record->balance);
        $this->assertSame(250.0, $record->regular_overpayment);
        $this->assertSame(1483.34, $record->monthly_payment);
        $this->assertSame('2048-03-15', $record->contractual_end_date->toDateString());
        $this->assertTrue($record->payoff_date->lt($record->contractual_end_date));
        $this->assertSame('First record.', $record->change_note);

        $state = $this->getJson('/wealth/liabilities')->json('liabilities.0');
        $this->assertEquals(248500, $state['balance_today']);
        $this->assertEquals(1483.34, $state['forecast']['monthly_payment']);
        $this->assertSame($record->payoff_date->toDateString(), $state['forecast']['payoff_date']);
        $this->assertNotEmpty($state['schedule']);
    }

    public function test_each_save_appends_a_record_and_describes_the_change(): void
    {
        $id = $this->mortgage()->id;
        $this->postJson("/wealth/liabilities/{$id}/records", $this->details());

        $this->travelTo('2027-02-20 10:00:00');
        $form = $this->getJson('/wealth/liabilities')->json('liabilities.0.form');

        // Three payments have fallen due, so the form starts from the projected position.
        $this->assertSame('2027-02-20', $form['effective_date']);
        $this->assertSame(253, $form['term_months']);
        $this->assertLessThan(248500, $form['balance']);
        $this->assertEquals(1483.34, $form['actual_payment']);

        $this->postJson("/wealth/liabilities/{$id}/records", array_merge($form, [
            'balance'             => $form['balance'] - 100,
            'regular_overpayment' => 400,
        ]))->assertOk();

        $this->assertSame(2, LiabilityRecord::count());

        $first  = LiabilityRecord::orderBy('id')->first();
        $latest = LiabilityRecord::orderByDesc('id')->first();

        $this->assertSame(248500.0, $first->balance);
        $this->assertSame(250.0, $first->regular_overpayment);
        $this->assertEquals($form['balance'], $latest->projected_balance);
        $this->assertStringContainsString('£100 below the projection', $latest->change_note);
        $this->assertStringContainsString('Regular overpayment £250 → £400.', $latest->change_note);
        $this->assertSame(1483.34, $latest->monthly_payment);
        $this->assertTrue($latest->payoff_date->lt($first->payoff_date));
    }

    public function test_preview_calculates_without_saving(): void
    {
        $this->postJson('/wealth/liabilities/preview', $this->details(['liability_id' => $this->mortgage()->id]))
            ->assertOk()
            ->assertJsonPath('monthly_payment', 1483.34)
            ->assertJsonMissingPath('schedule');

        $this->assertSame(0, LiabilityRecord::count());
    }

    public function test_one_off_overpayments_move_the_forecast_and_are_recorded(): void
    {
        $id = $this->mortgage()->id;
        $this->postJson("/wealth/liabilities/{$id}/records", $this->details());
        $before = LiabilityRecord::sole()->payoff_date;

        $this->postJson("/wealth/liabilities/{$id}/overpayments", ['paid_on' => '2027-01-15', 'amount' => 5000, 'note' => 'Bonus'])->assertOk();

        $latest = LiabilityRecord::orderByDesc('id')->first();
        $this->assertSame(2, LiabilityRecord::count());
        $this->assertTrue($latest->payoff_date->lt($before));
        $this->assertSame('2043-03-15', $latest->payoff_date->toDateString());
        $this->assertSame('One-off overpayment of £5,000 on 15 Jan 2027 added.', $latest->change_note);
        $this->assertEquals([['paid_on' => '2027-01-15', 'amount' => 5000, 'note' => 'Bonus']], $latest->one_offs);

        $state = $this->getJson('/wealth/liabilities')->json('liabilities.0');
        $this->assertSame('planned', $state['overpayments'][0]['status']);

        $this->deleteJson("/wealth/liabilities/{$id}/overpayments/{$state['overpayments'][0]['id']}")->assertOk();

        $latest = LiabilityRecord::orderByDesc('id')->first();
        $this->assertSame(3, LiabilityRecord::count());
        $this->assertSame($before->toDateString(), $latest->payoff_date->toDateString());
        $this->assertSame([], $latest->one_offs);
    }

    public function test_what_if_changes_the_projection_without_saving_anything(): void
    {
        $id = $this->mortgage()->id;
        $this->postJson("/wealth/liabilities/{$id}/records", $this->details());

        $this->travelTo('2027-02-20 10:00:00');
        $current = $this->getJson('/wealth/liabilities')->json('liabilities.0');

        // No changes: identical to the forecast as it stands.
        $same = $this->postJson("/wealth/liabilities/{$id}/what-if", [])->assertOk()->json('what_if');
        $this->assertSame($current['forecast']['payoff_date'], $same['payoff_date']);
        $this->assertEquals(0, $same['months_sooner']);
        $this->assertEquals(0, $same['interest_saved']);
        $this->assertEquals($current['schedule'], $same['schedule']);

        // A bigger overpayment from today: the past is untouched, the end comes sooner.
        $more = $this->postJson("/wealth/liabilities/{$id}/what-if", ['regular_overpayment' => 600])->json('what_if');
        $this->assertEquals(array_slice($current['schedule'], 0, 4), array_slice($more['schedule'], 0, 4));
        $this->assertGreaterThan(0, $more['months_sooner']);
        $this->assertGreaterThan(0, $more['interest_saved']);

        // Stopping overpayments pushes it later; a lump sum pulls it in further.
        $none = $this->postJson("/wealth/liabilities/{$id}/what-if", ['regular_overpayment' => 0])->json('what_if');
        $this->assertLessThan(0, $none['months_sooner']);

        $lump = $this->postJson("/wealth/liabilities/{$id}/what-if", ['regular_overpayment' => 600, 'lump_sum' => 20000])->json('what_if');
        $this->assertGreaterThan($more['months_sooner'], $lump['months_sooner']);

        $this->assertSame(1, LiabilityRecord::count());
        $this->assertDatabaseCount('wealth_liability_overpayments', 0);
        $this->assertSame($current, $this->getJson('/wealth/liabilities')->json('liabilities.0'));
    }

    public function test_balances_are_logged_automatically_on_each_payment_date(): void
    {
        $id = $this->mortgage()->id;
        $this->postJson("/wealth/liabilities/{$id}/records", $this->details());

        $this->assertSame(1, LiabilityValue::count());

        $this->travelTo('2027-02-20 10:00:00');
        $values = $this->getJson('/wealth/liabilities')->json('liabilities.0.values');

        $this->assertSame(['2027-02-15', '2027-01-15', '2026-12-15', '2026-11-15'], array_column($values, 'date'));
        $this->assertSame([false, false, false, true], array_column($values, 'is_manual'));
        $this->assertEquals(248500, $values[3]['balance']);
        $this->assertEquals(247655.05, $values[2]['balance']);

        // Loading again, or running the command, adds nothing more.
        $this->getJson('/wealth/liabilities');
        $this->artisan('wealth:log-liabilities')->expectsOutput('Logged 0 balance(s).');
        $this->assertSame(4, LiabilityValue::count());

        $this->travelTo('2027-03-15 10:00:00');
        $this->artisan('wealth:log-liabilities')->expectsOutput('Logged 1 balance(s).');
    }

    public function test_correcting_a_logged_balance_re_anchors_everything_after_it(): void
    {
        $id = $this->mortgage()->id;
        $this->postJson("/wealth/liabilities/{$id}/records", $this->details());

        $this->travelTo('2027-02-20 10:00:00');
        $before = collect($this->getJson('/wealth/liabilities')->json('liabilities.0.values'))->keyBy('date');
        $jan    = $before['2027-01-15'];

        $this->putJson("/wealth/liabilities/{$id}/values/{$jan['id']}", ['balance' => $jan['balance'] - 300])->assertOk();

        $state = $this->getJson('/wealth/liabilities')->json('liabilities.0');
        $after = collect($state['values'])->keyBy('date');

        // The corrected entry keeps what the projection had said, for the drift.
        $this->assertEquals($jan['balance'] - 300, $after['2027-01-15']['balance']);
        $this->assertEquals($jan['balance'], $after['2027-01-15']['projected_balance']);
        $this->assertTrue($after['2027-01-15']['is_manual']);

        // Earlier entries are untouched and now locked; later ones follow the corrected figure.
        $this->assertEquals($before['2026-12-15']['balance'], $after['2026-12-15']['balance']);
        $this->assertFalse($after['2026-12-15']['editable']);
        $this->assertEqualsWithDelta($before['2027-02-15']['balance'] - 301, $after['2027-02-15']['balance'], 1);
        $this->assertEquals($after['2027-02-15']['balance'], $state['balance_today']);

        // Saved as a new record with the same payment, nothing overwritten.
        $latest = LiabilityRecord::orderByDesc('id')->first();
        $this->assertSame(2, LiabilityRecord::count());
        $this->assertSame('2027-01-15', $latest->effective_date->toDateString());
        $this->assertSame(1483.34, $latest->monthly_payment);
        $this->assertSame(
            'Logged balance for 15 Jan 2027 corrected. Balance entered as £246,507.08, £300 below the projection of £246,807.08.',
            $latest->change_note,
        );

        $this->putJson("/wealth/liabilities/{$id}/values/{$after['2026-12-15']['id']}", ['balance' => 1])->assertStatus(422);
    }

    public function test_other_debts_can_be_added(): void
    {
        $this->postJson('/wealth/liabilities', [
            'description'    => 'Car finance',
            'effective_date' => '2026-11-15',
            'balance'        => 6200,
            'interest_rate'  => 6.9,
            'term_months'    => 23,
        ])->assertOk();

        $state = $this->getJson('/wealth/liabilities')->json('liabilities.1');
        $this->assertSame('loan', $state['type']);
        $this->assertSame('Car finance', $state['description']);
        $this->assertSame('2028-10-15', $state['forecast']['payoff_date']);
    }

    public function test_validation_rejects_bad_details(): void
    {
        $this->postJson("/wealth/liabilities/{$this->mortgage()->id}/records", $this->details(['balance' => -1, 'effective_date' => '2027-01-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['balance', 'effective_date']);
    }

    public function test_dashboard_totals_split_liquid_property_and_liabilities(): void
    {
        $this->addLiquidSource(50000);
        $this->postJson("/wealth/liabilities/{$this->mortgage()->id}/records", $this->details());

        $data   = $this->getJson('/wealth/data')->assertOk()->json();
        $totals = collect($data['totals'])->pluck('total', 'name');

        $this->assertEquals(50000, $totals['Total']);
        $this->assertArrayNotHasKey('Property', $totals->all());
        $this->assertSame(['property' => 475000, 'liabilities' => 248500, 'net_worth' => 276500], $data['summary']);
    }

    public function test_history_includes_liabilities_and_net_worth(): void
    {
        $this->addLiquidSource(50000);
        $this->postJson("/wealth/liabilities/{$this->mortgage()->id}/records", $this->details());

        $this->travelTo('2026-12-20 10:00:00');
        $history = $this->getJson('/wealth/history')->assertOk()->json();

        $this->assertEquals([['date' => '2026-11-15', 'total' => 50000]], $history['total_over_time']);
        $this->assertSame(
            [['date' => '2026-11-15', 'total' => 248500], ['date' => '2026-12-20', 'total' => 247655]],
            $history['liabilities_over_time'],
        );
        $this->assertSame(
            [['date' => '2026-11-15', 'total' => 276500], ['date' => '2026-12-20', 'total' => 277345]],
            $history['net_worth_over_time'],
        );
    }
}
