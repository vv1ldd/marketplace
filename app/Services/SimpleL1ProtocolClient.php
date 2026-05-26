<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class SimpleL1ProtocolClient
{
    private readonly string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('simple_l1.identity_provider_url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function introspectProof(string $proofToken): array
    {
        $response = $this->client()
            ->withHeaders(['X-Simple-L1-Proof' => $proofToken])
            ->post('/api/simple-l1/proofs/introspect');

        if (! $response->ok()) {
            throw new \RuntimeException('Simple L1 proof could not be verified.');
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function decideCapability(string $proofToken, string $capability, string $scope, array $context = []): array
    {
        $response = $this->client()->post('/api/simple-l1/capabilities/decide', [
            'proof_token' => $proofToken,
            'capability' => $capability,
            'scope' => $scope,
            'context' => $context,
        ]);

        if (! $response->ok()) {
            throw new \RuntimeException('Simple L1 capability decision failed.');
        }

        return $response->json();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function submitIntent(
        string $proofToken,
        string $capability,
        string $scope,
        array $payload,
        string $idempotencyKey,
    ): array {
        $response = $this->client()->post('/api/simple-l1/intents', [
            'proof_token' => $proofToken,
            'capability' => $capability,
            'scope' => $scope,
            'payload' => $payload,
            'idempotency_key' => $idempotencyKey,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Simple L1 intent submission failed.');
        }

        return $response->json();
    }

    private function client(): PendingRequest
    {
        $client = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(10);

        if (! config('simple_l1.verify_tls', true)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }
}
