<?php

return [
    'default' => env('LLM_PROVIDER', 'local'),
    'fallback' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('LLM_FALLBACK_PROVIDERS', 'openai,anthropic,local'))
    ))),
    'cloud_required' => env('LLM_CLOUD_REQUIRED', false),
    'timeout' => (int) env('LLM_TIMEOUT', 60),
    'redact_prompts' => env('LLM_REDACT_PROMPTS', true),

    'providers' => [
        'local' => [
            'driver' => 'ollama',
            'base_url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'gemma4'),
        ],

        'openai' => [
            'driver' => 'openai',
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com'),
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'organization' => env('OPENAI_ORGANIZATION'),
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-haiku-latest'),
            'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        ],
    ],
];
