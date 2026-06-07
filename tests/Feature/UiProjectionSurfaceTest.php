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

    public function test_migrated_next_surfaces_are_not_redirect_only_wrappers(): void
    {
        $files = [
            'frontend/app/business/[[...path]]/page.jsx',
            'frontend/app/services/[[...path]]/page.jsx',
            'frontend/app/catalog-network/[[...path]]/page.jsx',
            'frontend/app/products-search/page.jsx',
            'frontend/app/meanly-ai/page.jsx',
            'frontend/app/partner/page.jsx',
            'frontend/app/store/[[...path]]/page.jsx',
            'frontend/app/catalog/[...path]/page.jsx',
            'frontend/app/legal-entities/register/page.jsx',
            'frontend/app/login/page.jsx',
            'frontend/app/register/page.jsx',
            'frontend/app/cabinet/page.jsx',
            'frontend/app/cabinet/[...path]/page.jsx',
            'frontend/app/cabinet/register/page.jsx',
            'frontend/app/vault/register/page.jsx',
            'frontend/app/redeem/[[...path]]/page.jsx',
            'frontend/app/ops/[[...path]]/page.jsx',
            'frontend/app/reader/page.jsx',
            'frontend/app/terminal/page.jsx',
        ];

        foreach ($files as $file) {
            $contents = File::get(base_path($file));

            $this->assertStringNotContainsString("from 'next/navigation'", $contents, $file.' should render a projection, not redirect.');
            $this->assertStringNotContainsString('redirect(', $contents, $file.' should render a projection, not redirect.');
        }
    }
}
