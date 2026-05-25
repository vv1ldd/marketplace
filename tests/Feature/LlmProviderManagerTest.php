<?php

namespace Tests\Feature;

use App\Services\Llm\LlmProviderManager;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LlmProviderManagerTest extends TestCase
{
    public function test_openai_provider_generates_text_through_cloud_adapter(): void
    {
        config([
            'llm.default' => 'openai',
            'llm.fallback' => [],
            'llm.providers.openai.api_key' => 'test-key',
            'llm.providers.openai.base_url' => 'https://api.openai.test',
            'llm.providers.openai.model' => 'gpt-test',
        ]);

        Http::fake([
            'api.openai.test/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'cloud answer']],
                ],
                'usage' => ['total_tokens' => 12],
            ]),
        ]);

        $response = app(LlmProviderManager::class)->generateText('hello token=secret');

        $this->assertTrue($response->ok);
        $this->assertSame('cloud answer', $response->text);
        $this->assertSame('openai', $response->provider);

        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && str_contains($request->body(), '[redacted-secret]'));
    }

    public function test_manager_falls_back_to_anthropic_when_default_provider_fails(): void
    {
        config([
            'llm.default' => 'openai',
            'llm.fallback' => ['anthropic'],
            'llm.providers.openai.api_key' => 'openai-key',
            'llm.providers.openai.base_url' => 'https://api.openai.test',
            'llm.providers.openai.model' => 'gpt-test',
            'llm.providers.anthropic.api_key' => 'anthropic-key',
            'llm.providers.anthropic.base_url' => 'https://api.anthropic.test',
            'llm.providers.anthropic.model' => 'claude-test',
        ]);

        Http::fake([
            'api.openai.test/v1/chat/completions' => Http::response(['error' => 'down'], 500),
            'api.anthropic.test/v1/messages' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => 'fallback answer'],
                ],
            ]),
        ]);

        $response = app(LlmProviderManager::class)->generateText('hello');

        $this->assertTrue($response->ok);
        $this->assertSame('fallback answer', $response->text);
        $this->assertSame('anthropic', $response->provider);
        $this->assertTrue($response->fallbackUsed);
    }

    public function test_health_reports_configured_cloud_provider(): void
    {
        config([
            'llm.providers.openai.api_key' => 'test-key',
            'llm.providers.openai.model' => 'gpt-test',
        ]);

        $manager = app(LlmProviderManager::class);

        $this->assertTrue($manager->cloudConfigured());
        $this->assertContains('openai', $manager->configuredProviderNames());
    }
}
