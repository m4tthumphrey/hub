<?php

namespace App\Services\Withings;

use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;

class WithingsClient extends Client
{
    private ?string $token = null;

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function spost($uri, array $payload = [], array $options = []): array
    {
        if (count($payload)) {
            $signable = ['action', 'client_id', 'nonce'];

            if ($uri === 'signature') {
                $signable = ['action', 'client_id', 'timestamp'];
            } elseif (!isset($payload['nonce'])) {
                $nonce = $this->spost('signature', [
                    'action'    => 'getnonce',
                    'timestamp' => time()
                ]);

                $payload['nonce'] = $nonce['nonce'];
            }

            if (!isset($payload['client_id'])) {
                $payload['client_id'] = config('services.withings.client_id');
            }

            ksort($payload);

            $payload['signature']   = hash_hmac('sha256', implode(',', Arr::only($payload, $signable)), config('services.withings.client_secret'));
            $options['form_params'] = $payload;
        }

        $response = $this->post($uri, $options);
        $json     = json_decode($response->getBody()->getContents(), true);

        if ($json['status'] === 0) {
            return $json['body'];
        }

        throw new WithingsException($json['error'], $json['status']);
    }

    public function request(string $method, $uri = '', array $options = []): ResponseInterface
    {
        $options['headers']['Authorization'] = 'Bearer ' . $this->token;

        return parent::request($method, $uri, $options);
    }

}
