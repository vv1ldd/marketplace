<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Http;

class FakeProviderGateway
{
    public static int $orderStatus = 200;

    /** @var array<int, string> */
    public static array $codes = ['FAKE-CODE-001'];

    /** @var array<string, mixed>|null */
    public static ?array $orderPayload = null;

    public static function boot(): void
    {
        Http::fake(static fn ($request) => static::responseFor($request));
    }

    /**
     * @param  array<int, string>  $codes
     */
    public static function expectSandbox(
        int $orderStatus = 200,
        array $codes = ['FAKE-CODE-001'],
        ?array $orderPayload = null,
    ): void {
        self::$orderStatus = $orderStatus;
        self::$codes = $codes;
        self::$orderPayload = $orderPayload;
    }

    public static function responseFor($request)
    {
        $url = $request->url();
        $method = strtoupper($request->method());

        if ($method === 'GET' && str_contains($url, 'normalized-cards')) {
            return Http::response(
                ['cards' => array_map(fn (string $code) => ['pin_code' => $code], self::$codes)],
                200,
            );
        }

        if ($method === 'POST' && str_contains($url, '/providers/ezpin-sandbox/order')) {
            return Http::response(
                self::$orderPayload ?? ['order' => ['referenceCode' => 'FAKE-EXT-ORDER-1']],
                self::$orderStatus,
            );
        }

        if ($method === 'POST' && str_contains($url, '/partners/grant-credit')) {
            return Http::response(['success' => true, 'reservation_id' => 'HOLD-TEST'], 200);
        }

        return Http::response(['success' => true, 'reservation_id' => 'HOLD-TEST'], 200);
    }

    public static function dispatchedServiceSku(): ?string
    {
        foreach (Http::recorded() as [$request]) {
            if (strtoupper($request->method()) !== 'POST') {
                continue;
            }

            if (! str_contains($request->url(), '/providers/ezpin-sandbox/order')) {
                continue;
            }

            $payload = $request->data();
            $sku = (string) ($payload['service_sku'] ?? $payload['sku'] ?? '');

            return $sku === '' ? null : $sku;
        }

        return null;
    }
}
