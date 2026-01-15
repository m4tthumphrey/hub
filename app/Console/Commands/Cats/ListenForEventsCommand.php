<?php

namespace App\Console\Commands\Cats;

use App\Services\PetSureClient;
use App\Services\PushoverClient;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\InputOption;

class ListenForEventsCommand extends Command
{
    private const CACHE_KEY = 'cats:timeline:id';

    private const CATS = [
        347286 => [
            'name'   => 'Fudge',
            'sounds' => [
                0 => ['FudgeLooking'],
                1 => ['FudgeReturning'],
                2 => ['FudgeLeaving'],
            ]
        ],
        347287 => [
            'name'   => 'Coco',
            'sounds' => [
                0 => ['CocoLooking'],
                1 => ['CocoReturning'],
                2 => ['CocoLeaving'],
            ]
        ],
        347288 => [
            'name'   => 'Buddy',
            'sounds' => [
                0 => ['BuddyLooking'],
                1 => ['BuddyReturning'],
                2 => ['BuddyLeaving'],
            ]
        ],
        347289 => [
            'name'   => 'Teddy',
            'sounds' => [
                0 => ['TeddyLooking'],
                1 => ['TeddyReturning'],
                2 => ['TeddyLeaving'],
            ]
        ],
    ];

    private const DIRECTIONS = [
        0 => 'looked through the door',
        1 => 'came in',
        2 => 'went out',
    ];

    protected $name = 'cats:listen';

    private Client $petsure;
    private Client $pushover;

    public function handle(Repository $cache, PetSureClient $petsure, PushoverClient $pushover)
    {
        $overriddenLastId = $this->option('last-id');
        $sleep            = $this->option('sleep');

        $this->petsure  = $petsure;
        $this->pushover = $pushover;

        if (null !== ($times = $this->option('simulate'))) {
            for ($i = 0; $i < $times; $i++) {
                $this->processMovement([
                    'tag_id'     => Arr::random(array_keys(self::CATS)),
                    'direction'  => Arr::random(array_keys(self::DIRECTIONS)),
                    'created_at' => now()->toIso8601ZuluString()
                ], true);
            }

            return;
        }

        $response = $this->petsure->get('timeline/household/' . config('services.petsure.household_id'));
        $json     = json_decode($response->getBody()->getContents(), true);
        $lastId   = $overriddenLastId ?: $cache->get(self::CACHE_KEY);
        $stored   = false;

        foreach ($json['data'] as $item) {
            if ($item['id'] <= $lastId) {
                break;
            }

            if (!$overriddenLastId && !$stored) {
                $cache->put(self::CACHE_KEY, $item['id']);
                $stored = true;
            }

            if ($item['type'] !== 0) {
                continue;
            }

            foreach ($item['movements'] as $movement) {
                $this->processMovement($movement);
            }
        }

        if ($sleep) {
            sleep($sleep);
        }
    }

    protected function processMovement(array $movement, bool $simulated = false): void
    {
        $tagId     = $movement['tag_id'];
        $direction = $movement['direction'];
        $date      = new Carbon($movement['created_at']);
        $cat       = self::CATS[$tagId];
        $message   = $cat['name'] . ' ' . self::DIRECTIONS[$direction];

        if ($simulated) {
            $message .= ' [simulated]';
        }

        $this->info(sprintf('%s: %s', $date->format('H:i:s'), $message));

        if ($this->option('push')) {
            if (null === ($device = $this->option('push-device'))) {
                $device = config('services.pushover.device');
            }

            $payload = [
                'device'  => $device,
                'sound'   => Arr::random($cat['sounds'][$direction]),
                'message' => $message
            ];

            $this->pushover->post('messages.json', [
                'form_params' => $payload
            ]);

            Log::debug($message, [
                'data' => $payload
            ]);
        }
    }

    protected function getOptions()
    {
        return [
            ['last-id', null, InputOption::VALUE_REQUIRED, 'The id of the last event'],
            ['push', null, InputOption::VALUE_NONE, 'Push notifications'],
            ['push-device', null, InputOption::VALUE_REQUIRED, 'Push notifications to this device'],
            ['simulate', null, InputOption::VALUE_REQUIRED, 'Simulate this number of times'],
            ['sleep', null, InputOption::VALUE_REQUIRED, 'Sleep time in seconds'],
        ];
    }
}
