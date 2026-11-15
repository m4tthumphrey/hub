<?php

namespace App\Console\Commands\Wealth;

use App\Models\Wealth\Liability;
use App\Services\Wealth\LiabilityService;
use Illuminate\Console\Command;

class LogLiabilitiesCommand extends Command
{
    protected $signature = 'wealth:log-liabilities';

    protected $description = 'Log the projected balance of each liability for payment dates that have passed';

    public function handle(LiabilityService $liabilities): int
    {
        $added = Liability::all()->sum(fn (Liability $liability) => $liabilities->syncLog($liability));

        $this->info("Logged {$added} balance(s).");

        return self::SUCCESS;
    }
}
