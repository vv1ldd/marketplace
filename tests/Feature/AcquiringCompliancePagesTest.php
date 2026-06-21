<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcquiringCompliancePagesTest extends TestCase
{
    #[DataProvider('compliancePages')]
    public function test_public_acquiring_compliance_pages_redirect_to_frontend(string $path): void
    {
        $this->assertStorefrontRedirect($this->get($path), $path);
    }

    public static function compliancePages(): array
    {
        return [
            ['/company'],
            ['/payment'],
            ['/delivery'],
            ['/refund'],
            ['/offer'],
            ['/privacy'],
            ['/terms'],
        ];
    }
}
