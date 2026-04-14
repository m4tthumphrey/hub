<?php

namespace App\Console\Commands\Withings;

use App\Models\WeightLog;
use App\Services\Withings\WithingsClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class GetWeightCommand extends Command
{
    protected $name = 'withings:weight';

    public function handle(WithingsClient $withingsClient)
    {
        $weight = WeightLog::latest()->first();

        $measures = $withingsClient->spost('measure', [
            'action'     => 'getmeas',
            'lastupdate' => $weight->created_at->timestamp ?? time() - (24 * 60 * 60 * 7),
            'meastype'   => 1,
            'category'   => 1
        ]);

        if (empty($measures['measuregrps'])) {
            return;
        }

        $measureGroup = Arr::last($measures['measuregrps']);
        $measure      = $measureGroup['measures'][0];

        $weightInGrams     = $measure['value'];
        $weightInKilograms = round($weightInGrams * pow(10, $measure['unit']), 2);
        $weightInPounds    = round($weightInKilograms * 2.205, 2);
        $weightInStone     = round($weightInKilograms / 6.35, 2);

        if (!$weight || $weightInGrams !== $weight->weight_grams) {
            WeightLog::create([
                'date'             => Carbon::parse($measureGroup['date'])->format('Y-m-d H:i:s'),
                'weight_grams'     => $weightInGrams,
                'weight_kilograms' => $weightInKilograms,
                'weight_pounds'    => $weightInPounds,
                'weight_stone'     => $weightInStone
            ]);
        }
    }
}
