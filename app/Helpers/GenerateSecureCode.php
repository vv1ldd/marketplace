<?php

namespace App\Helpers;

use App\Services\VoucherEngine;

/**
 * @deprecated Use \App\Services\VoucherEngine::issue() directly for full SVC format support.
 *
 * Kept for backward compatibility. Routes all calls through the new VoucherEngine
 * so existing code will automatically produce SVC-format codes.
 */
class GenerateSecureCode
{
    /**
     * @throws \Random\RandomException
     */
    public static function generate(?string $prefix = null, ?string $sku = null): string
    {
        // Strip any trailing dashes from legacy prefix usage
        $issuerPrefix = rtrim($prefix ?? 'WLD', '-');

        return VoucherEngine::issue(issuerPrefix: $issuerPrefix, sku: $sku);
    }
}
