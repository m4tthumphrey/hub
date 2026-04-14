<?php

namespace App\Console\Commands\Withings;

use App\Services\Withings\WithingsClient;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class AuthenticateCommand extends Command
{
    private const CACHE_KEY = 'withings.tokens';

    protected $name = 'withings:authenticate';

    public function handle(WithingsClient $client, Repository $cache)
    {
        $tokens = json_decode($cache->get(self::CACHE_KEY), true);

        if (!$this->option('force') && $tokens) {
            if ($tokens['expires_at'] < time() + 3600) {
                $request = $client->post('oauth2', [
                    'form_params' => [
                        'action'        => 'requesttoken',
                        'grant_type'    => 'refresh_token',
                        'client_id'     => config('services.withings.client_id'),
                        'client_secret' => config('services.withings.client_secret'),
                        'refresh_token' => $tokens['refresh_token'],
                        'redirect_uri'  => config('services.withings.redirect_url'),
                    ]
                ]);

                $tokens = json_decode($request->getBody()->getContents(), true)['body'];

                $tokens['expires_at'] = time() + $tokens['expires_in'];

                print_r($tokens);

                $cache->put(self::CACHE_KEY, json_encode($tokens));
            }

            return;
        }

        $code = $this->ask('code');

        $request = $client->post('oauth2', [
            'form_params' => [
                'action'        => 'requesttoken',
                'grant_type'    => 'authorization_code',
                'client_id'     => config('services.withings.client_id'),
                'client_secret' => config('services.withings.client_secret'),
                'code'          => $code,
                'redirect_uri'  => config('services.withings.redirect_url'),
            ]
        ]);

        $tokens = json_decode($request->getBody()->getContents(), true);

        if ($tokens['status'] !== 0) {
            print_r($tokens);

            return;
        }

        $tokens = $tokens['body'];
        $tokens['expires_at'] = time() + $tokens['expires_in'];

        $cache->put(self::CACHE_KEY, json_encode($tokens));
    }

    protected function getOptions()
    {
        return [
            ['force', null, InputOption::VALUE_NONE]
        ];
    }
}
