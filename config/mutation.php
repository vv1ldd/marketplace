<?php

return [
    'region' => env('MARKETPLACE_REGION', env('APP_REGION', 'local')),
    'writer_region' => env('MARKETPLACE_WRITER_REGION', env('MARKETPLACE_REGION', env('APP_REGION', 'local'))),
    'writer_epoch' => env('MARKETPLACE_WRITER_EPOCH', '1'),
    'writer_scope' => env('MARKETPLACE_WRITER_SCOPE', 'marketplace:global'),
    'writer_guard_mode' => env('MUTATION_WRITER_GUARD_MODE', env('MUTATION_GUARD_MODE', 'shadow')),

    'default_mode' => env('MUTATION_GUARD_MODE', 'shadow'),
    'retry_guard_mode' => env('MUTATION_RETRY_GUARD_MODE', env('MUTATION_GUARD_MODE', 'shadow')),
    'webhook_guard_mode' => env('MUTATION_WEBHOOK_GUARD_MODE', env('MUTATION_GUARD_MODE', 'shadow')),
    'cli_guard_mode' => env('MUTATION_CLI_GUARD_MODE', env('MUTATION_GUARD_MODE', 'shadow')),
    'model_hook_mode' => env('MUTATION_MODEL_HOOK_MODE', 'shadow'),
    'ledger_guard_mode' => env('MUTATION_LEDGER_GUARD_MODE', env('MUTATION_GUARD_MODE', 'shadow')),

    'production_cli_requires_confirmation' => (bool) env('MUTATION_PROD_CLI_CONFIRMATION', true),
];
