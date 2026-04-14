<?php

namespace App\Services;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class PelotonClient extends Client
{
    private ?string $token = null;

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $options['headers']['Authorization'] = 'Bearer ' . $this->token;

        return parent::request($method, $uri, $options);
    }
}
