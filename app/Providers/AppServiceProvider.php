<?php

namespace App\Providers;

use App\Services\PelotonAuth;
use App\Services\PelotonClient;
use App\Services\PetSureClient;
use App\Services\PushoverClient;
use App\Services\Withings\WithingsClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PetSureClient::class, function ($app) {
            return new PetSureClient($app->make('config')->get('services.petsure.client'));
        });

        $this->app->singleton(PushoverClient::class, function ($app) {
            $config = $app->make('config')->get('services.pushover');

            $client = new PushoverClient($config['client']);
            $client->setUser($config['user']);
            $client->setToken($config['token']);

            return $client;
        });

        $this->app->singleton(PelotonAuth::class, function ($app) {
            $config = $app->make('config')->get('services.peloton.auth');
            $cache  = $app->make('cache.store');

            $auth = new PelotonAuth($config['username'], $config['password'], $cache);
            $auth->authenticate();

            return $auth;
        });

        $this->app->singleton(PelotonClient::class, function ($app) {
            $client = new PelotonClient($app->make('config')->get('services.peloton.client'));
            $client->setToken($app->make(PelotonAuth::class)->getAccessToken());

            return $client;
        });

        $this->app->singleton(WithingsClient::class, function ($app) {
            $tokens = json_decode($app->get('cache.store')->get('withings.tokens'), true);
            $client = new WithingsClient($app->make('config')->get('services.withings.client'));
            $client->setToken($tokens['access_token']);

            return $client;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
