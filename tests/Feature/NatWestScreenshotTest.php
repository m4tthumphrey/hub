<?php

namespace Tests\Feature;

use App\Models\Wealth\Source;
use App\Services\Wealth\ScreenshotTextParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NatWestScreenshotTest extends TestCase
{
    use RefreshDatabase;

    // OCR text from a real NatWest accounts screen (note the £ misread as €).
    private const TEXT = <<<'TEXT'
        22:48
        子
        MH
        Accounts
        ...
        40
        Edit >
        ...
        JOINT
        Select Platinum Account • 35001623 • 54-30-11
        €61,757.27
        PERSONAL
        Premier Select
        £67.56
        Pots
        SAVINGS
        Digital Regular Saver
        +
        £307.00
        Available: £307.00
        Create a pot
        Separate money from your main account
        TEXT;

    private function jointId(): int
    {
        return Source::where('description', 'NatWest joint')->firstOrFail()->id;
    }

    public function test_migration_adds_the_joint_account_as_a_liquid_source(): void
    {
        $this->assertDatabaseHas('wealth_sources', [
            'description' => 'NatWest joint', 'who' => 'Joint', 'current_amount' => 61757,
        ]);
        $this->assertDatabaseHas('wealth_source_values', ['source_id' => $this->jointId(), 'value' => 61757]);
    }

    public function test_reads_both_the_regular_saver_and_the_joint_account(): void
    {
        $updates = (new ScreenshotTextParser)->parse(self::TEXT, 'NatWest');

        $this->assertSame([1 => 307.0, $this->jointId() => 61757.27], $updates);
    }

    public function test_regular_saver_still_reads_without_a_joint_account_on_screen(): void
    {
        $text = "SAVINGS\nDigital Regular Saver\n+\n£307.00\nAvailable: £307.00";

        $this->assertSame([1 => 307.0], (new ScreenshotTextParser)->parse($text, 'NatWest'));
    }

    public function test_screenshot_endpoint_logs_the_joint_balance(): void
    {
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class);

        $text = "JOINT\nSelect Platinum Account • 35001623 • 54-30-11\n€62,010.40\nPERSONAL\nPremier Select\n£67.56";

        $this->post('/wealth/screenshot', ['text' => $text, 'app' => 'NatWest'])->assertOk();

        $this->assertDatabaseHas('wealth_sources', ['description' => 'NatWest joint', 'current_amount' => 62010]);
        $this->assertDatabaseHas('wealth_source_values', [
            'source_id' => $this->jointId(), 'value' => 62010, 'origin' => 'screenshot',
        ]);
    }
}
