<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRedeemWildflowPurchase;
use App\Mail\SendActivationCode;
use App\Mail\VerificationCodeMail;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\Settings;
use App\Models\User;
use App\Models\WildflowCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Factory;
use Illuminate\View\View;

class CodeController extends Controller
{
    public function checkEmail(Request $request): View|Factory|RedirectResponse
    {
        \Log::info('REDEEM_SESS: checkEmail START. SessionID=' . session()->getId() . ' | hasInfo=' . (session()->has('order_item_info') ? 'YES' : 'NO'));
        
        $data = $request->validate(['email' => 'required|email']);

        $uuid = session('order_item_info')['uuid'] ?? $request->input('uuid');

        if (! $uuid) {
            \Log::warning('REDEEM_SESS: checkEmail FAILED. No UUID in session or request.');
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        if (! session()->has('order_item_info')) {
            session()->put('order_item_info', ['uuid' => $uuid]);
        }

        session()->put('user_exists', User::findByEmail($data['email']) !== null);
        session()->put('client_email', $data['email']);

        $verificationCode = rand(100000, 999999);
        session()->put('verification_code', $verificationCode);

        Mail::to($data['email'])->send(new VerificationCodeMail($verificationCode));

        return redirect()->temporarySignedRoute('redeem.activation', now()->addHours(), ['uuid' => $uuid]);
    }

    public function getViewForm(Request $request): Factory|View
    {
        $uuid = session('order_item_info.uuid') ?? $request->query('uuid');
        
        if (! $uuid && ! session()->has('order_item_info')) {
             // Fallback to step 1 if everything is lost
             return view('redeem.step1', ['prefix' => '', 'redeemShop' => null]); 
        }

        $order_item_info = session('order_item_info') ?? ['uuid' => $uuid];
        $client_info = $order_item_info['client_info'] ?? null;
        $client_email = session('client_email');

        $redeemCollectExtendedProfile = false;
        if ($uuid = data_get($order_item_info, 'uuid')) {
            $redeemCollectExtendedProfile = OrderItems::with(['game', 'order.shop'])
                ->where('uuid', $uuid)
                ->first()
                ?->redeemCollectsExtendedProfile() ?? false;
        }

        return view('redeem.step3', compact('client_info', 'client_email', 'redeemCollectExtendedProfile'));
    }

    public function resendCode(Request $request): RedirectResponse
    {
        $email = session('client_email');
        if (! $email) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Сессия истекла']);
        }

        $verificationCode = rand(100000, 999999);
        session()->put('verification_code', $verificationCode);

        Mail::to($email)->send(new VerificationCodeMail($verificationCode));

        return back()->with('success', 'Код подтверждения повторно отправлен на '.$email);
    }

    public function getEmailView(Request $request): View|Factory|RedirectResponse
    {
        if (app()->environment('production') && ! $request->hasValidSignature()) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        $uuid = session('order_item_info.uuid') ?? $request->query('uuid');
        
        if (! $uuid) {
            \Log::warning('REDEEM_SESS: getEmailView FAILED. No UUID.');
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        if (! session()->has('order_item_info')) {
             session()->put('order_item_info', ['uuid' => $uuid]);
        }

        $order_item = OrderItems::where('uuid', $uuid)->first();
        $order = $order_item?->order;

        return view('redeem.step2', compact('order'));
    }

    public function getFinishView(Request $request): Factory|View|RedirectResponse
    {
        $order_item = null;

        $hasValidSignature = app()->environment('production') ? $request->hasValidSignature() : true;
        if ($hasValidSignature && $request->filled('uuid')) {
            $order_item = OrderItems::where('uuid', $request->string('uuid'))->first();
            if (! $order_item || ! $order_item->is_activated) {
                abort(403, 'Ссылка недействительна или истекла.');
            }
            session()->put('order_item_info', [
                'uuid' => $order_item->uuid,
                'type_form_id' => $order_item->type_form_id,
            ]);
        } else {
            $order_item_info = session('order_item_info');
            if (! $order_item_info) {
                return redirect()->route('redeem.code');
            }

            $order_item = OrderItems::where('uuid', $order_item_info['uuid'])->first();
            if (! $order_item) {
                return redirect()->route('redeem.code');
            }
        }

        $order_item->refresh();
        $order_item->loadMissing('order');
        $standardized = $order_item->standardized_data;
        $redeemFinishPollUrl = null;

        if ($order_item->purchase_status === 'pending' && ! filled($order_item->original_code)) {
            $redeemFinishPollUrl = URL::temporarySignedRoute(
                'redeem.finish-status',
                now()->addHours(2),
                ['uuid' => $order_item->uuid]
            );
        }

        if (filled($order_item->original_code)) {
            // ⛓️ Sovereign Ledger: Record the DEFINITIVE DELIVERY
            app(\App\Services\LedgerService::class)->record($order_item->order->shop, 'VOUCHER_CODE_VIEWED', $order_item, [
                'via' => 'page_load',
                'customer_id' => $order_item->order->user_id,
            ]);
        }

        return view('redeem.finish', compact('order_item', 'standardized', 'redeemFinishPollUrl'));
    }

    public function redeemFinishStatus(Request $request): JsonResponse
    {
        $uuid = null;
        if ($request->hasValidSignature()) {
            $uuid = $request->query('uuid');
        } elseif (session()->has('order_item_info.uuid')) {
            $uuid = session('order_item_info.uuid');
        }

        if (! $uuid) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order_item = OrderItems::where('uuid', $uuid)->first();
        if (! $order_item || ! $order_item->is_activated) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (! $request->hasValidSignature() && (string) session('order_item_info.uuid') !== (string) $uuid) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order_item->refresh();

        if (filled($order_item->original_code)) {
            // ⛓️ Sovereign Ledger: Record the DEFINITIVE DELIVERY
            app(\App\Services\LedgerService::class)->record($order_item->order?->shop, 'VOUCHER_CODE_VIEWED', $order_item, [
                'via' => 'polling',
                'customer_id' => $order_item->order?->user_id,
            ]);
        }

        return response()->json([
            'purchase_status' => $order_item->purchase_status,
            'original_code' => $order_item->original_code,
            'purchase_error' => $order_item->purchase_error,
            'has_code' => filled($order_item->original_code),
        ]);
    }

    public function sendForm(Request $request)
    {
        \Log::info('REDEEM_SESS: sendForm START. SessionID=' . session()->getId() . ' | hasInfo=' . (session()->has('order_item_info') ? 'YES' : 'NO'));

        $uuid = session('order_item_info')['uuid'] ?? $request->input('uuid');
        
        if (! $uuid) {
            \Log::warning('REDEEM_SESS: sendForm FAILED. No UUID in session or request.');
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        \Log::info('REDEEM_SESS: sendForm PROCEEDING. UUID=' . $uuid);

        $order_item = OrderItems::with(['game', 'order.shop'])->where('uuid', $uuid)->first();
        if (! $order_item) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        $showPlaystationRedeemAccountForm = $order_item->showPlaystationRedeemAccountForm();
        $redeemCollectExtendedProfile = $order_item->redeemCollectsExtendedProfile();

        $rules = [
            'email' => 'required|email',
            'verification_code' => 'required|integer',
            'deliver_to_chat' => 'nullable|string|in:on',
        ];

        if ($redeemCollectExtendedProfile) {
            $rules['first_name'] = 'required|string|min:2|max:100';
            $rules['last_name'] = 'required|string|min:2|max:100';
            $rules['phone'] = 'required|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/';
        }

        if ($showPlaystationRedeemAccountForm) {
            $rules = array_merge($rules, [
                'option.0.check' => 'nullable|string|in:on',
                'option.0.ps_network_id' => 'required_if:option.0.check,on|email',
                'option.0.ps_network_password' => 'required_if:option.0.check,on|string|min:6|max:32',
                'option.0.ps_2fa_code' => 'required_if:option.0.check,on|string|min:6|max:32',
                'option.1.check' => 'nullable|string|in:on',
                'option.1.ps_birthday' => 'required_if:option.1.check,on|date_format:Y-m-d',
            ]);
        }

        $data = $request->validate($rules);

        if (! $redeemCollectExtendedProfile) {
            $data['first_name'] = $request->input('first_name');
            $data['last_name'] = $request->input('last_name');
            $data['phone'] = $request->input('phone');
        }

        $sessionCode = session('verification_code');
        $localBypassCode = app()->environment('local')
            ? trim((string) config('app.redeem_local_verification_code'))
            : '';
        $localBypass = $localBypassCode !== ''
            && (string) $data['verification_code'] === $localBypassCode;

        if (! $localBypass && (int) $data['verification_code'] !== (int) $sessionCode && app()->environment('production')) {
            return back()->withErrors(['verification_code' => 'Неверный код подтверждения']);
        }

        session()->forget('verification_code');

        $data['uuid'] = $uuid;

        if ($order_item->is_activated) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Код уже активирован']);
        }

        if (! $order_item->is_redeemed) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        if ($order_item->activate_till < now()) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Код уже истек']);
        }

        $order = Order::where('id', $order_item->order_id)->first();

        if (! $order) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Заказ не найден']);
        }

        $option_0 = data_get($data, 'option.0');
        $option_1 = data_get($data, 'option.1');

        if ($showPlaystationRedeemAccountForm) {
            if ($option_0) {
                unset($option_0['check']);
                $data['option'] = $option_0;
                $data['type_id'] = 3;
            } elseif ($option_1) {
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

        // Извлекаем YM User ID из первоначальной технической информации заказа
        $ym_user_id = data_get($order->client_info, 'id');

        $user = UserController::updateOrCreate(phone: $data['phone'] ?? null, data: $data, ym_user_id: $ym_user_id);

        $order_item = OrderItems::where('uuid', $data['uuid'])->first();

        // 1. Сначала помечаем как активированный (клиент ввел данные)
        $order_item->update([
            'is_activated' => true,
            'client_info' => $data,
        ]);

        // Логируем начало активации
        $phonePart = isset($data['phone']) && $data['phone'] !== ''
            ? ", Телефон: {$data['phone']}"
            : '';
        $order_item->order->comments()->create([
            'user_id' => $user->id,
            'user_type' => $user::class,
            'comment' => "Клиент начал процедуру активации (Email: {$data['email']}{$phonePart})",
        ]);

        $order_item->update([
            'activated_at' => now(),
            'type_id' => $data['type_id'],
            'purchase_status' => 'pending',
        ]);

        // 2. Обновляем статус заказа, чтобы он стал доступен для исполнения (если все товары активированы)
        $order_items = OrderItems::where('order_id', $order->id)->get();
        $activated_all = $order_items->every('is_activated');

        // Заменяем технические данные (proxy email/phone Маркета) на настоящие, которые клиент ввел в redeem
        $rawClientInfo = $order->client_info ?? [];
        $client_info = is_array($rawClientInfo) ? $rawClientInfo : (json_decode($rawClientInfo, true) ?? []);
        $client_info['email'] = $data['email'];
        $client_info['phone'] = $data['phone'] ?? null;
        $client_info['firstName'] = $data['first_name'] ?? null;
        $client_info['lastName'] = $data['last_name'] ?? null;

        $order->update([
            'user_id' => $user->id,
            'client_info' => $client_info,
            'code_activated' => $activated_all,
        ]);

        // 3. Процесс активации: Wildflow или dev-демо (без строки в wildflow_catalogs)
        $product = WildflowCatalog::findForOrderOfferSku($order_item->sku);
        $runPurchaseFlow = $product
            || $order->isDevRedeemSimulation()
            || $order->isDevAsyncRedeemDemo()
            || $order->isYandexSandboxOrder();

        if ($runPurchaseFlow) {
            $service_sku = $product ? data_get($product, 'data.data.product.sku') : 'redeem-demo';
            $service_price = $product ? data_get($product, 'data.data.price') : 0;

            try {
                $order = $order_item->order;
                $original_code = null;

                if ($order->isYandexSandboxOrder()) {
                    \Log::info('Активация пропущен: Яндекс.Маркет sandbox', ['uuid' => $order_item->uuid]);
                    $order->comments()->create([
                        'user_id' => $user->id,
                        'user_type' => $user::class,
                        'comment' => 'Активация пропущен: тестовый заказ Яндекс.Маркета (info.fake). Реальный Wildflow не вызывается.',
                    ]);
                    $order_item->update([
                        'purchase_status' => 'none',
                        'original_code' => null,
                    ]);
                } elseif ($order->isDevAsyncRedeemDemo()) {
                    Bus::dispatchAfterResponse(new ProcessRedeemWildflowPurchase(
                        $order_item->id,
                        $user->id,
                        data_get($data, 'deliver_to_chat') === 'on',
                    ));

                    $order->comments()->create([
                        'user_id' => $user->id,
                        'user_type' => $user::class,
                        'comment' => 'Активация товара (dev async demo): после ответа страницы — без отдельного queue:work. SKU: '.$service_sku.'.',
                    ]);
                } elseif ($order->isDevRedeemSimulation()) {
                    $original_code = 'GIFTCARD_EXAMPLE';
                    $order->comments()->create([
                        'user_id' => $user->id,
                        'user_type' => $user::class,
                        'comment' => 'Симуляция активацияа (dev_simulation): выдан тестовый код, Wildflow не вызывался.',
                    ]);
                    $order_item->update([
                        'purchase_status' => 'success',
                        'original_code' => $original_code,
                        'purchase_error' => null,
                    ]);

                    if ($order_items->every(fn ($item) => $item->purchase_status === 'success' || $item->id === $order_item->id)) {
                        $order->update(['progress_id' => 4]);
                    }

                    Mail::to($user->email)->send(new SendActivationCode($original_code, $order));

                    if ($order->chat_id && data_get($data, 'deliver_to_chat') === 'on') {
                        try {
                            $ymService = new \App\Http\Services\YmService($order->shop);
                            $ymService->sendMessage($order->chat_id, view('chat.send_code_message', ['code' => $original_code, 'shop' => $order->shop])->render());
                            $order->comments()->create([
                                'user_id' => $user->id,
                                'user_type' => $user::class,
                                'comment' => 'Код (симуляция) продублирован в чат Яндекс.Маркета',
                            ]);
                        } catch (\Exception $chatE) {
                            \Log::error('YM Chat send error', [$chatE->getMessage()]);
                            $order->comments()->create([
                                'user_id' => $user->id,
                                'user_type' => $user::class,
                                'comment' => 'Ошибка отправки кода в чат: '.$chatE->getMessage(),
                            ]);
                        }
                    }
                } else {
                    ProcessRedeemWildflowPurchase::dispatchSync(
                        $order_item->id,
                        $user->id,
                        data_get($data, 'deliver_to_chat') === 'on',
                    );

                    $order->comments()->create([
                        'user_id' => $user->id,
                        'user_type' => $user::class,
                        'comment' => 'Процесс активации запущен (SKU: '.$service_sku.', цена: '.$service_price.'). Код появится на странице и будет отправлен на email.',
                    ]);
                }

            } catch (\Exception $e) {
                \Log::error('wildflow error', [$e->getMessage()]);

                if ($product) {
                    WildflowCatalog::deactivateIfProviderOutOfStock($e->getMessage(), $order_item->sku);
                }

                $order->comments()->create([
                    'user_id' => $user->id,
                    'user_type' => $user::class,
                    'comment' => 'Ошибка при активации товара: '.$e->getMessage(),
                ]);

                $order_item->update([
                    'purchase_status' => 'failed',
                    'purchase_error' => $e->getMessage(),
                ]);
            }
        } else {
            // Нет каталога и это не dev-демо / sandbox — ручная обработка
            $order_item->update([
                'purchase_status' => 'manual',
            ]);
        }

        return redirect()->route('redeem.success');
    }

    public function checkCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string',
        ]);

        $code = strtoupper(preg_replace('/\s+/', '', $data['code']));

        if (! preg_match('/^[A-Z0-9-]+$/', $code)) {
            throw ValidationException::withMessages([
                'code' => 'Допустимы только латинские буквы, цифры и дефисы.',
            ]);
        }

        // Restore canonical dashes format based on VoucherEngine rules
        $canonicalCode = \App\Services\VoucherEngine::formatCanonical($code);
        if ($canonicalCode) {
            $code = $canonicalCode;
        }

        $order_item = OrderItems::findByKeyWith($code, ['order.shop']);

        if (! $order_item) {
            return back()->withErrors(['code' => 'Введен неверный или несуществующий код']);
        }

        if ($order_item->is_activated) {
            $shop = $order_item->order?->shop;
            $support = null;
            if ($shop && (filled($shop->support_email) || filled($shop->support_telegram))) {
                $support = [
                    'shop_name' => $shop->name,
                    'support_email' => $shop->support_email,
                    'support_telegram' => $shop->support_telegram,
                ];
            }

            $redirect = redirect()
                ->route('redeem.code', request()->query())
                ->withErrors(['code' => 'Код уже успешно активирован. Мы свяжемся с Вами.']);

            if ($support !== null) {
                $redirect->with('redeem_support', $support);
            }

            return $redirect;
        }

        $order = Order::where('id', $order_item->order_id)->first();

        if (! $order) {
            return back()->withErrors(['code' => 'Заказ не был найден']);
        }

        if ($order_item->activate_till < now()) {
            return back()->withErrors(['code' => 'Код уже истек']);
        }

        $order_item->update(['is_redeemed' => true]);

        session()->put('order_item_info', [
            'uuid' => $order_item->uuid,
            'type_form_id' => $order_item->type_form_id,
        ]);
        
        \Log::info('REDEEM_SESS: checkCode SUCCESS. SessionID=' . session()->getId() . ' | UUID=' . $order_item->uuid);

        if (! $order_item->redeem_started_at) {
            $order_item->update(['redeem_started_at' => now()]);
        }

        return redirect()->temporarySignedRoute('redeem.email', now()->addHours(), ['uuid' => $order_item->uuid]);
    }

    public function getCodeView(Request $request): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
    {
        try {
            $data = $request->validate(['is_frame' => 'nullable|string|in:1,0']);
        } catch (ValidationException $exception) {
            abort(403, $exception->getMessage());
        }

        $host = $request->httpHost();
        $app_domain = config('app.domain');

        // Локальная разработка: не блокировать artisan serve / valet по списку из БД
        if (app()->environment('local') && preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i', $host)) {
            $trusted_hosts_array = [$host];
        } else {

            // Объединяем: APP_DOMAIN + TRUSTED_HOSTS из .env (config) + то же из таблицы settings (если есть),
            // чтобы запись в БД не «перебивала» .env целиком.
            $chunks = array_filter([$app_domain]);
            foreach ([config('services.trusted_hosts'), Settings::get('TRUSTED_HOSTS')] as $csv) {
                if (filled($csv)) {
                    $chunks = array_merge($chunks, array_map('trim', explode(',', (string) $csv)));
                }
            }

            $trusted_hosts_array = array_values(array_unique(array_filter($chunks)));

            // Include all active shop domains
            $shop_domains = \App\Models\Shop::where('is_active', true)
                ->pluck('domain')
                ->filter()
                ->map(fn ($d) => trim(preg_replace('#^https?://#i', '', (string) $d), '/'))
                ->filter()
                ->all();

            $trusted_hosts_array = array_unique(array_merge(
                $trusted_hosts_array,
                $shop_domains,
                config('app.admin_panel_hosts', []),
                config('app.partner_panel_hosts', []),
            ));
        }

        if (! in_array($host, $trusted_hosts_array, true)) {
            \Illuminate\Support\Facades\Log::warning("Domain mismatch in getCodeView: '$host' not in trusted list [".implode(',', $trusted_hosts_array).']');
            abort(403, "Domain $host is not allowed");
        }

        session()->put('is_frame', (bool) data_get($data, 'is_frame'));

        // 1. Try to find shop by query parameter (Highest priority)
        $shopSlug = $request->query('shop');
        $current_shop = null;
        $prefix = null;

        if ($shopSlug) {
            // Sanitize: allow only alphanumeric and dashes
            $shopSlug = preg_replace('/[^A-Z0-9-]/i', '', $shopSlug);
            $cleanSlug = rtrim($shopSlug, '-');
            $current_shop = \App\Models\Shop::where('voucher_prefix', $cleanSlug)
                ->orWhere('voucher_prefix', $cleanSlug.'-')
                ->first();

            // Security: if shop parameter is provided but not found,
            // just use the provided slug as a prefix instead of 404ing.
            $prefix = $current_shop?->voucher_prefix ?? ($cleanSlug.'-');
        }

        // 2. If no shop context from parameter, fallback to host/domain detection
        if (! $current_shop) {
            $current_shop = \App\Models\Shop::where('domain', $host)
                ->orWhere(fn ($q) => $host !== $app_domain ? $q->where('domain', 'like', "%$host%") : null)
                ->first();

            // Last resort for Meanly domain
            if (! $current_shop && str_contains($host, 'meanly.ru')) {
                $current_shop = \App\Models\Shop::where('domain', 'like', '%meanly.ru%')->first();
            }

            $prefix = $prefix ?: (string) ($current_shop?->voucher_prefix ?? '');
        }

        // Один завершающий дефис для подсказки/маски (как в GenerateSecureCode), без жёсткого W1C
        if ($prefix !== '' && ! str_ends_with($prefix, '-')) {
            $prefix .= '-';
        }

        return view('redeem.step1', [
            'prefix' => $prefix,
            'redeemShop' => $current_shop,
        ]);
    }
}
