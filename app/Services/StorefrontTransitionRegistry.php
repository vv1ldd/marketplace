<?php

namespace App\Services;

final class StorefrontTransitionRegistry
{
    public const VERSION = 'ctg-storefront-v0';

    public const CHECKOUT_ALLOWED = 'CHECKOUT_ALLOWED';
    public const CHECKOUT_BLOCKED = 'CHECKOUT_BLOCKED';
    public const PAYMENT_PENDING = 'PAYMENT_PENDING';
    public const OPEN_SAFE = 'OPEN_SAFE';
    public const WAIT_FOR_BACKEND_STATE = 'WAIT_FOR_BACKEND_STATE';
    public const FORBIDDEN = 'FORBIDDEN';
    public const IGNORED_CLIENT_OVERRIDE = 'IGNORED_CLIENT_OVERRIDE';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function transitions(): array
    {
        return [
            self::CHECKOUT_ALLOWED => [
                'source' => 'StorefrontDecisionService::checkoutDecision',
                'inputs' => ['AVAILABLE', 'STOCK_OK'],
                'emits' => self::CHECKOUT_ALLOWED,
            ],
            self::CHECKOUT_BLOCKED => [
                'source' => 'StorefrontDecisionService::checkoutDecision',
                'inputs' => ['NO_STOCK', 'PROVIDER_UNAVAILABLE', 'VALIDATION_EXCEPTION'],
                'emits' => self::CHECKOUT_BLOCKED,
            ],
            self::PAYMENT_PENDING => [
                'source' => 'StorefrontDecisionService::orderSafeDecision',
                'inputs' => ['ORDER_CREATED', 'PAYMENT_NOT_CAPTURED'],
                'emits' => self::PAYMENT_PENDING,
            ],
            self::OPEN_SAFE => [
                'source' => 'StorefrontDecisionService::orderSafeDecision',
                'inputs' => ['CAPTURED_WITH_CODE', 'AUTHORIZED_IDENTITY'],
                'emits' => self::OPEN_SAFE,
            ],
            self::WAIT_FOR_BACKEND_STATE => [
                'source' => 'StorefrontDecisionService::orderSafeDecision',
                'inputs' => ['PAYMENT_CAPTURED', 'PROVIDER_PENDING', 'CODE_NOT_READY', 'PROVIDER_TIMEOUT'],
                'emits' => self::WAIT_FOR_BACKEND_STATE,
            ],
            self::FORBIDDEN => [
                'source' => 'AuthenticateStorefrontToken|StorefrontCheckoutController::authorizeOrderSafe',
                'inputs' => ['TOKEN_MISSING', 'TOKEN_INVALID', 'TOKEN_EXPIRED', 'WRONG_IDENTITY'],
                'emits' => self::FORBIDDEN,
            ],
            self::IGNORED_CLIENT_OVERRIDE => [
                'source' => 'StorefrontCheckoutController::intent',
                'inputs' => ['CLIENT_PRICE_SNAPSHOT', 'CLIENT_STOCK_HINT'],
                'emits' => self::IGNORED_CLIENT_OVERRIDE,
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function ids(): array
    {
        return array_keys($this->transitions());
    }

    public function has(string $transitionId): bool
    {
        return array_key_exists($transitionId, $this->transitions());
    }
}
