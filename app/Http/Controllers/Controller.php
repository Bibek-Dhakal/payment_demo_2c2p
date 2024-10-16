<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Log\Logger;
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

    public function curl_payment_init(string $url, array $payload): array
    {
        $jwt = $this->encode($payload);
        $data = '{"payload":"' . $jwt . '"}';

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers[] = 'Content-Type: application/*+json';
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($curl);
        curl_close($curl);

        $decoded = json_decode($response, true);
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
