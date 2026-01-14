<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateUserCommand extends Command
{
    protected $name = 'users:create';

    public function handle()
    {
        $name     = $this->ask('Name?');
        $email    = $this->ask('Email?');
        $password = $this->secret('Password?');

        User::create([
            'name'     => $name,
            'email'    => $email,
            'password' => $password
        ]);
    }
}
