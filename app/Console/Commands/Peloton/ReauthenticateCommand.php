<?php

namespace App\Console\Commands\Peloton;

use App\Services\PelotonAuth;
use Illuminate\Cache\Repository;
use Illuminate\Console\Command;

class ReauthenticateCommand extends Command
{
    protected $name = 'peloton:reauthenticate';

    public function handle(PelotonAuth $pelotonAuth, Repository $repository)
    {
        $tokens = $pelotonAuth->getTokenData();

        $repository->set(PelotonAuth::CACHE_KEY, json_encode($tokens));
    }
}
