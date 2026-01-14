<?php

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class PushoverClient extends Client
{
    protected string $user;
    protected string $token;

    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $defaultParams = [
            'token' => $this->token,
            'user'  => $this->user
        ];

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            $options['query'] = array_merge($defaultParams, $options['query'] ?? []);
        } else {
            $options['form_params'] = array_merge($defaultParams, $options['form_params'] ?? []);
        }

        return parent::request($method, $uri, $options);
    }
}
