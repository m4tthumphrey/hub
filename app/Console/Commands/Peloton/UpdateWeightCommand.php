<?php

namespace App\Console\Commands\Peloton;

use App\Models\WeightLog;
use App\Services\PelotonClient;
use Illuminate\Console\Command;

class UpdateWeightCommand extends Command
{
    protected $name = 'peloton:weight';

    public function handle(PelotonClient $pelotonClient)
    {
        $pelotonUserId = config('services.peloton.user_id');

        $weight   = WeightLog::latest()->first();
        $response = $pelotonClient->get('user/' . $pelotonUserId);
        $user     = json_decode($response->getBody()->getContents(), true);

        $pelotonWeight = round($user['weight'], 2);
        $loggedWeight  = round($weight->weight_kilograms, 2);

        if ($loggedWeight !== $pelotonWeight) {
            $pelotonClient->put('user/' . $pelotonUserId, [
                'json' => [
                    'weight'      => $loggedWeight,
                    'weight_unit' => $user['weight_unit']
                ]
            ]);
        }
    }
}
