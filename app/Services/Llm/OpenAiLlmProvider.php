<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiLlmProvider implements LlmProviderInterface
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
            return LlmResponse::failure($this->name(), 'OpenAI-compatible provider is not configured.', $this->model());
        }

        try {
            $headers = [];
            if (! empty($this->config['organization'])) {
                $headers['OpenAI-Organization'] = (string) $this->config['organization'];
            }

            $response = Http::timeout((int) ($options['timeout'] ?? config('llm.timeout', 60)))
                ->withToken($this->apiKey())
                ->withHeaders($headers)
                ->post($this->baseUrl().'/v1/chat/completions', [
                    'model' => $this->model(),
                    'messages' => [
                        ['role' => 'system', 'content' => (string) ($options['system'] ?? 'You are a concise marketplace operations assistant.')],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => (float) ($options['temperature'] ?? 0.2),
                    'max_tokens' => (int) ($options['max_tokens'] ?? 900),
                ]);

            if (! $response->successful()) {
                return LlmResponse::failure($this->name(), 'HTTP '.$response->status().': '.$response->body(), $this->model());
            }

            return LlmResponse::success((string) $response->json('choices.0.message.content'), $this->name(), $this->model(), [
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
            'driver' => 'openai',
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
