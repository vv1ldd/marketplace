<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Throwable;

class AnthropicLlmProvider implements LlmProviderInterface
{
    public function __construct(
        private readonly string $name,
        private readonly array $config,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function model(): ?string
    {
        return (string) ($this->config['model'] ?? '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '' && $this->baseUrl() !== '' && $this->model() !== '';
    }

    public function generateText(string $prompt, array $options = []): LlmResponse
    {
        if (! $this->isConfigured()) {
            return LlmResponse::failure($this->name(), 'Anthropic provider is not configured.', $this->model());
        }

        try {
            $response = Http::timeout((int) ($options['timeout'] ?? config('llm.timeout', 60)))
                ->withHeaders([
                    'x-api-key' => $this->apiKey(),
                    'anthropic-version' => (string) ($this->config['version'] ?? '2023-06-01'),
                ])
                ->post($this->baseUrl().'/v1/messages', [
                    'model' => $this->model(),
                    'system' => (string) ($options['system'] ?? 'You are a concise marketplace operations assistant.'),
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => (float) ($options['temperature'] ?? 0.2),
                    'max_tokens' => (int) ($options['max_tokens'] ?? 900),
                ]);

            if (! $response->successful()) {
                return LlmResponse::failure($this->name(), 'HTTP '.$response->status().': '.$response->body(), $this->model());
            }

            return LlmResponse::success((string) $response->json('content.0.text'), $this->name(), $this->model(), [
                'usage' => $response->json('usage'),
            ]);
        } catch (Throwable $e) {
            return LlmResponse::failure($this->name(), $e->getMessage(), $this->model());
        }
    }

    public function health(): array
    {
        return [
            'name' => $this->name(),
            'driver' => 'anthropic',
            'configured' => $this->isConfigured(),
            'model' => $this->model(),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    private function apiKey(): string
    {
        return (string) ($this->config['api_key'] ?? '');
    }
}
