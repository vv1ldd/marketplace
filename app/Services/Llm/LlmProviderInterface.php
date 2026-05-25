<?php

namespace App\Services\Llm;

interface LlmProviderInterface
{
    public function name(): string;

    public function model(): ?string;

    public function isConfigured(): bool;

    /**
     * @param  array<string, mixed>  $options
     */
    public function generateText(string $prompt, array $options = []): LlmResponse;

    /**
     * @return array{name:string,driver:string,configured:bool,model:?string}
     */
    public function health(): array;
}
