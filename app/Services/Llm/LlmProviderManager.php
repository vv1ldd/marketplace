<?php

namespace App\Services\Llm;

use InvalidArgumentException;

class LlmProviderManager
{
    /** @var array<string, LlmProviderInterface> */
    private array $providers = [];

    public function __construct(private readonly PromptRedactor $redactor)
    {
    }

    public function generateText(string $prompt, array $options = []): LlmResponse
    {
        $prompt = $this->redactor->redact($prompt);
        $providerNames = $this->providerOrder((string) ($options['provider'] ?? config('llm.default', 'local')));
        $lastFailure = null;

        foreach ($providerNames as $index => $providerName) {
            $provider = $this->provider($providerName);
            if (! $provider->isConfigured()) {
                $lastFailure = LlmResponse::failure($providerName, 'Provider is not configured.', $provider->model());
                continue;
            }

            $response = $provider->generateText($prompt, $options);
            if ($response->ok && trim($response->text) !== '') {
                return $index > 0 ? $response->withFallbackUsed() : $response;
            }

            $lastFailure = $response;
        }

        return $lastFailure ?? LlmResponse::failure('none', 'No LLM providers are available.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function health(): array
    {
        return collect(array_keys((array) config('llm.providers', [])))
            ->map(fn (string $name): array => $this->provider($name)->health() + [
                'cloud' => $this->isCloudProvider($name),
                'default' => $name === (string) config('llm.default', 'local'),
                'fallback' => in_array($name, (array) config('llm.fallback', []), true),
            ])
            ->values()
            ->all();
    }

    public function cloudConfigured(): bool
    {
        return collect($this->health())->contains(
            fn (array $provider): bool => (bool) $provider['cloud'] && (bool) $provider['configured']
        );
    }

    public function configuredProviderNames(): array
    {
        return collect($this->health())
            ->filter(fn (array $provider): bool => (bool) $provider['configured'])
            ->pluck('name')
            ->values()
            ->all();
    }

    public function provider(string $name): LlmProviderInterface
    {
        if (isset($this->providers[$name])) {
            return $this->providers[$name];
        }

        $config = (array) config("llm.providers.{$name}", []);
        $driver = (string) ($config['driver'] ?? '');

        return $this->providers[$name] = match ($driver) {
            'ollama' => new OllamaLlmProvider($name, $config),
            'openai' => new OpenAiLlmProvider($name, $config),
            'anthropic' => new AnthropicLlmProvider($name, $config),
            default => throw new InvalidArgumentException("Unsupported LLM provider [{$name}] driver [{$driver}]."),
        };
    }

    private function providerOrder(string $default): array
    {
        return collect([$default])
            ->merge((array) config('llm.fallback', []))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isCloudProvider(string $name): bool
    {
        return in_array((string) config("llm.providers.{$name}.driver"), ['openai', 'anthropic'], true);
    }
}
