<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Http;
use stdClass;

abstract class Controller
{
    protected Logger $logger;
    protected string $secret;

    public function __construct(Logger $logger, string $secret)
    {
        $this->logger = $logger;
        $this->secret = $secret;
    }

    /**
     * @throws ConnectionException
     */
    public function http_payment_init(string $url, array $payload): array
    {
        $jwt = $this->encode($payload);
        $data = ['payload' => $jwt];

        $response = Http::withHeaders([
            'Content-Type' => 'application/*+json',
        ])->post($url, $data);

        $decoded = $response->json();
        $payloadResponse = $decoded['payload'];
        $decodedPayload = $this->decode($payloadResponse);
        return (array)$decodedPayload;
    }

    protected function encode($payload): string
    {
        return JWT::encode($payload, $this->secret, 'HS256');
    }

    protected function decode($payload): stdClass
    {
        return JWT::decode($payload, new Key($this->secret, 'HS256'));
    }
}
