<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaLlmProvider implements LlmProviderInterface
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
        return $this->baseUrl() !== '' && $this->model() !== '';
    }

    public function generateText(string $prompt, array $options = []): LlmResponse
    {
        if (! $this->isConfigured()) {
            return LlmResponse::failure($this->name(), 'Ollama provider is not configured.', $this->model());
        }

        try {
            $response = Http::timeout((int) ($options['timeout'] ?? config('llm.timeout', 60)))
                ->post($this->baseUrl().'/api/generate', [
                    'model' => $this->model(),
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => array_filter([
                        'temperature' => $options['temperature'] ?? null,
                    ]),
                ]);

            if (! $response->successful()) {
                return LlmResponse::failure($this->name(), 'HTTP '.$response->status().': '.$response->body(), $this->model());
            }

            return LlmResponse::success((string) $response->json('response'), $this->name(), $this->model());
        } catch (Throwable $e) {
            return LlmResponse::failure($this->name(), $e->getMessage(), $this->model());
        }
    }

    public function health(): array
    {
        return [
            'name' => $this->name(),
            'driver' => 'ollama',
            'configured' => $this->isConfigured(),
            'model' => $this->model(),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }
}
