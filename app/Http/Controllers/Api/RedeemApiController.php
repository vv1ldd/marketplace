<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UserController;
use App\Jobs\ProcessRedeemWildflowPurchase;
use App\Jobs\SendTelegramJob;
use App\Mail\SendActivationCode;
use App\Mail\VerificationCodeMail;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\WildflowCatalog;
use App\Services\Provider\ProviderHub;
use App\Services\WildflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RedeemApiController extends Controller
{
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|min:10', // Убираем жесткий regex, чтобы поддерживать разные префиксы
        ]);

        $code = $request->input('code');

        $canonicalCode = \App\Services\VoucherEngine::formatCanonical($code);
        if ($canonicalCode) {
            $code = $canonicalCode;
        }

        $application = $request->attributes->get('api_application');

        $order_item = OrderItems::findByKeyWith($code, ['order.shop']);

        if (! $order_item) {
            return response()->json(['message' => 'Введен неверный или несуществующий код'], 404);
        }

        if ($order_item->order?->shop_id !== $application->shop_id) {
            return response()->json(['message' => 'Этот код принадлежит другому магазину'], 403);
        }

        if ($order_item->is_activated) {
            return response()->json(['message' => 'Код уже успешно активирован'], 422);
        }

        if ($order_item->activate_till < now()) {
            return response()->json(['message' => 'Код уже истек'], 422);
        }

        // Mark as redeemed (as in web version)
        $order_item->update(['is_redeemed' => true]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'uuid' => $order_item->uuid,
                'sku' => $order_item->sku,
                'type_form_id' => $order_item->type_form_id,
            ],
        ]);
    }

    /**
     * Send verification email.
     */
    public function sendVerification(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'email' => 'required|email',
        ]);

        $code = $request->input('code');
        $email = $request->input('email');

        $canonicalCode = \App\Services\VoucherEngine::formatCanonical($code);
        if ($canonicalCode) {
            $code = $canonicalCode;
        }

        $order_item = OrderItems::findByKeyWith($code, ['order']);
        $application = $request->attributes->get('api_application');

        if (! $order_item) {
            return response()->json(['message' => 'Код не найден'], 404);
        }

        if ($order_item->order?->shop_id !== $application->shop_id) {
            return response()->json(['message' => 'Этот код принадлежит другому магазину'], 403);
        }

        $verificationCode = rand(100000, 999999);

        // Store in cache for 1 hour, keyed by the redeem code
        Cache::put("redeem_verification:{$code}", [
            'verification_code' => $verificationCode,
            'email' => $email,
        ], now()->addHour());

        Mail::to($email)->send(new VerificationCodeMail($verificationCode));

        return response()->json([
            'status' => 'success',
            'message' => 'Код подтверждения отправлен на почту',
        ]);
    }

    /**
     * Activate the code with client data.
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'verification_code' => 'required|string',
            'email' => 'required|email',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string',
        ]);

        $code = $request->input('code');

        $canonicalCode = \App\Services\VoucherEngine::formatCanonical($code);
        if ($canonicalCode) {
            $code = $canonicalCode;
        }

        $verificationCodeInput = $request->input('verification_code');

        $localBypassCode = app()->environment('local')
            ? trim((string) config('app.redeem_local_verification_code'))
            : '';
        $localBypass = $localBypassCode !== ''
            && (string) $verificationCodeInput === $localBypassCode;

        // Bypass check if verified via Passkey or trusted by storefront
        if (! in_array($verificationCodeInput, ['PASSKEY_AUTH', 'TRUSTED_USER']) && ! $localBypass) {
            $cachedData = Cache::get("redeem_verification:{$code}");

            if (! $cachedData || $verificationCodeInput != $cachedData['verification_code']) {
                return response()->json(['message' => 'Неверный или истекший код подтверждения'], 422);
            }
        }

        $order_item = OrderItems::findByKeyWith($code, ['order.shop', 'game']);
        $application = $request->attributes->get('api_application');

        if (! $order_item) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        if ($order_item->order?->shop_id !== $application->shop_id) {
            return response()->json(['message' => 'Этот код принадлежит другому магазину'], 403);
        }

        if ($order_item->is_activated) {
            return response()->json(['message' => 'Код уже активирован'], 422);
        }

        $order = Order::find($order_item->order_id);
        if (! $order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        if ($order_item->showPlaystationRedeemAccountForm()) {
            $request->validate([
                'option.0.check' => 'nullable|string|in:on,1',
                'option.0.ps_network_id' => 'required_if:option.0.check,on,1|email',
                'option.0.ps_network_password' => 'required_if:option.0.check,on,1|string|min:6|max:32',
                'option.0.ps_2fa_code' => 'required_if:option.0.check,on,1|string|min:6|max:32',
                'option.1.check' => 'nullable|string|in:on,1',
                'option.1.ps_birthday' => 'required_if:option.1.check,on,1|date_format:Y-m-d',
            ]);
        }

        $redeemCollectExtendedProfile = $order_item->redeemCollectsExtendedProfile();
        if ($redeemCollectExtendedProfile) {
            $request->validate([
                'first_name' => 'required|string|min:2|max:100',
                'last_name' => 'required|string|min:2|max:100',
                'phone' => 'required|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',
            ]);
        }

        $data = $request->all();

        // Forced override: Use contact info from the API Application (the shop)
        $apiApplication = $request->attributes->get('api_application');
        if ($apiApplication && $apiApplication->first_name && $apiApplication->last_name) {
            $data['first_name'] = $apiApplication->first_name;
            $data['last_name'] = $apiApplication->last_name;
            $data['phone'] = $apiApplication->phone ?: ($data['phone'] ?? null);
        } elseif ($redeemCollectExtendedProfile) {
            $data['first_name'] = $data['first_name'] ?: 'Пользователь';
            $data['last_name'] = $data['last_name'] ?: 'Meanly';
            $data['phone'] = $data['phone'] ?: null;
        } else {
            $data['first_name'] = $data['first_name'] ?? null;
            $data['last_name'] = $data['last_name'] ?? null;
            $data['phone'] = $data['phone'] ?? null;
        }

        if ($order_item->showPlaystationRedeemAccountForm()) {
            $option_0 = data_get($data, 'option.0');
            $option_1 = data_get($data, 'option.1');

            if ($option_0 && ($option_0['check'] ?? false)) {
                unset($option_0['check']);
                $data['option'] = $option_0;
                $data['type_id'] = 3;
            } elseif ($option_1 && ($option_1['check'] ?? false)) {
                unset($option_1['check']);
                $data['option'] = $option_1;
                $data['type_id'] = 2;
            } else {
                $data['type_id'] = 1;
            }
        } else {
            $data['type_id'] = 1;
            unset($data['option']);
        }

        $user = UserController::updateOrCreate(phone: $data['phone'] ?? null, data: $data);

        $order->update(['user_id' => $user->id]);

        $order_item->update([
            'is_activated' => true,
            'client_info' => $data,
            'activated_at' => now(),
            'type_id' => $data['type_id'],
        ]);

        // Активацияка: Wildflow или dev-демо без каталога (как в CodeController)
        $product = WildflowCatalog::findForOrderOfferSku($order_item->sku);
        $runPurchaseFlow = $product
            || $order->isDevRedeemSimulation()
            || $order->isDevAsyncRedeemDemo()
            || $order->isYandexSandboxOrder();

        if ($runPurchaseFlow) {
            $service_sku = $product ? data_get($product, 'data.data.product.sku') : 'redeem-demo';
            $service_price = $product ? data_get($product, 'data.data.price') : 0;
            $wf_service = new WildflowService;

            try {
                $original_code = null;
                $deferItemPurchaseUpdate = false;

                if ($order->isYandexSandboxOrder()) {
                    $original_code = 'GIFTCARD_EXAMPLE';
                    $order->comments()->create([
                        'user_id' => null,
                        'comment' => '✅ Активация пропущен (Яндекс sandbox / info.fake). Тестовый код для API: '.\Illuminate\Support\Str::mask($original_code, '*', 4, -4),
                    ]);
                } elseif ($order->isDevAsyncRedeemDemo()) {
                    $order_item->update(['purchase_status' => 'pending']);
                    Bus::dispatchAfterResponse(new ProcessRedeemWildflowPurchase($order_item->id, $user->id, false));
                    $order->comments()->create([
                        'user_id' => null,
                        'comment' => '✅ Активация (dev async demo) в очереди; Wildflow не вызывается.',
                    ]);
                    $deferItemPurchaseUpdate = true;
                } elseif ($order->isDevRedeemSimulation()) {
                    $original_code = 'GIFTCARD_EXAMPLE';
                    $order->comments()->create([
                        'user_id' => null,
                        'comment' => '✅ Симуляция активацияа (dev_simulation): '.\Illuminate\Support\Str::mask($original_code, '*', 4, -4),
                    ]);
                    $hub = app(ProviderHub::class);
                    $provider = $order_item->game->provider ?? \App\Models\Provider::where('type', 'wildflow')->first();
                    $driver = $hub->forProvider($provider);

                    // 🛡️ Sovereign Ledger: Record API Purchase START
                    app(\App\Services\LedgerService::class)->record($order->shop, 'PROVIDER_ORDER_START', $order_item, [
                        'provider' => $provider->type,
                        'via' => 'api',
                        'reference' => $order_item->uuid,
                    ]);

                    // 1. Create order
                    $externalOrderId = $driver->createOrder(
                        sku: $product->sku,
                        reference: $order_item->uuid,
                        price: (float)($product->retail_price ?? 0),
                        quantity: (int)$order_item->count,
                        meta: [
                            'type' => $product->type ?? 'gift_card',
                            'email' => $data['email'] ?? 'sataniyazow@gmail.com',
                        ]
                    );

                    sleep(1);

                    // 2. Fetch the cards
                    $codes = $driver->getCodes($externalOrderId);
                    $original_code = !empty($codes) ? $codes[0] : null;

                    if ($original_code) {
                        // ⛓️ Sovereign Ledger: Record API Purchase SUCCESS
                        app(\App\Services\LedgerService::class)->record($order->shop, 'PROVIDER_ORDER_SUCCESS', $order_item, [
                            'provider' => $provider->type,
                            'external_id' => $externalOrderId,
                            'via' => 'api',
                        ]);

                        $order->comments()->create([
                            'user_id' => null,
                            'comment' => "✅ Автоматическая выдача кода ({$provider->name}): ".\Illuminate\Support\Str::mask($original_code, '*', 4, -4),
                        ]);
                    } else {
                        // ⛓️ Sovereign Ledger: Record empty codes failure
                        app(\App\Services\LedgerService::class)->record($order->shop, 'PROVIDER_ORDER_FAILED', $order_item, [
                            'provider' => $provider->type,
                            'message' => 'Empty codes list',
                            'via' => 'api',
                        ]);
                    }
                }

                if (! $deferItemPurchaseUpdate) {
                    $order_item->update([
                        'purchase_status' => $original_code ? 'success' : 'failed',
                        'original_code' => $original_code,
                        'purchase_error' => $original_code ? null : 'Provider: пустой ответ getCodes',
                    ]);

                    if ($original_code) {
                        Mail::to($request->input('email'))->send(new SendActivationCode($original_code, $order));
                    }
                }

            } catch (\Exception $exception) {
                // ⛓️ Sovereign Ledger: Record Exception failure
                app(\App\Services\LedgerService::class)->record($order->shop, 'PROVIDER_ORDER_FAILED', $order_item, [
                    'provider' => $provider->type ?? 'unknown',
                    'message' => $exception->getMessage(),
                    'via' => 'api',
                ]);

                \Log::error('Ошибка активацияки в Redeem API activate', [
                    'error' => $exception->getMessage(),
                    'uuid' => $order_item->uuid,
                ]);

                if ($product) {
                    WildflowCatalog::deactivateIfProviderOutOfStock($exception->getMessage(), $order_item->sku);
                }

                $order_item->update([
                    'purchase_status' => 'failed',
                    'purchase_error' => $exception->getMessage(),
                ]);

                $order->update(['is_problem' => true]);
            }
        } else {
            $order_item->update([
                'purchase_status' => 'manual',
            ]);
        }

        // Обновляем статус заказа
        $order_items = OrderItems::where('order_id', $order->id)->get();
        $activated_all = $order_items->every('is_activated');
        $purchased_all = $order_items->every(fn ($item) => $item->purchase_status === 'success');

        $order_update_data = [
            'user_id' => $user->id,
            'client_info' => array_merge($order->client_info ?? [], $data),
            'code_activated' => $activated_all,
        ];

        if ($purchased_all) {
            $order_update_data['progress_id'] = 4; // Выполнено
        }

        $order->update($order_update_data);

        SendTelegramJob::dispatchSync(order_id: $order->order_id, status: 'send_form', order_item_id: $order_item->id);

        // Clear cache
        Cache::forget("redeem_verification:{$code}");

        return response()->json([
            'status' => 'success',
            'message' => 'Активация и закупка успешно завершены',
        ]);
    }
}
