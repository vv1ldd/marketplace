<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UiProjectionSurfaceTest extends TestCase
{
    public function test_legacy_ui_surfaces_return_projection_contracts_without_blade_html(): void
    {
        foreach (['business', 'services', 'partner', 'redeem/code', 'ops', 'reader', 'terminal', 'meanly-ai'] as $surface) {
            $this->getJson('/api/ui/v1/projections/'.$surface)
                ->assertOk()
                ->assertJsonPath('contract.name', 'ui-projection')
                ->assertJsonPath('contract.dto_boundary', 'transitions_not_conditions')
                ->assertJsonMissing(['html'])
                ->assertJsonStructure([
                    'projection' => [
                        'type',
                        'title',
                        'lead',
                        'sections',
                        'actions' => [
                            'allowed_actions',
                            'blocked_actions',
                            'next_action',
                            'blocking_reason',
                        ],
                    ],
                ]);
        }
    }
}
