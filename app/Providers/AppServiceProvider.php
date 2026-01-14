<?php

namespace App\Providers;

use App\Services\PetSureClient;
use App\Services\PushoverClient;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
