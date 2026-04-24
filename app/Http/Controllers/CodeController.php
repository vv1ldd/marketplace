<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramJob;
use App\Mail\SendActivationCode;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Http\Services\YmService;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\Settings;
use App\Models\User;
use App\Models\WildflowCatalog;
use App\Services\WildflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Factory;
use Illuminate\View\View;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;
use Illuminate\Support\Str;

class CodeController extends Controller
{
    public function checkEmail(Request $request): View|Factory|RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        if (!session()->has('order_item_info')) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        session()->put('user_exists', User::where('email', $data['email'])->exists());
        session()->put('client_email', $data['email']);

        $verificationCode = rand(100000, 999999);
        session()->put('verification_code', $verificationCode);

        Mail::to($data['email'])->send(new VerificationCodeMail($verificationCode));

        return redirect()->temporarySignedRoute('redeem.activation', now()->addHours());
    }

    public function getViewForm(Request $request): Factory|View
    {
        $order_item_info = session('order_item_info');
        $client_info = $order_item_info['client_info'] ?? null;
        $client_email = session('client_email');

        return view('redeem.step3', compact('client_info', 'client_email'));
    }

    public function resendCode(Request $request): RedirectResponse
    {
        $email = session('client_email');
        if (!$email) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Сессия истекла']);
        }

        $verificationCode = rand(100000, 999999);
        session()->put('verification_code', $verificationCode);

        Mail::to($email)->send(new VerificationCodeMail($verificationCode));

        return back()->with('success', 'Код подтверждения повторно отправлен на ' . $email);
    }

    public function getEmailView(Request $request): View|Factory|RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        $order_item_info = session('order_item_info');
        if (!$order_item_info) {
             return redirect()->route('redeem.code')->withErrors(['code' => 'Сессия истекла']);
        }

        $order_item = OrderItems::where('uuid', $order_item_info['uuid'])->first();
        $order = $order_item?->order;

        return view('redeem.step2', compact('order'));
    }

    public function getFinishView(Request $request): Factory|View
    {
        return view('redeem.finish');
    }

    public function sendForm(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|min:2|max:100',
            'last_name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'phone' => 'required|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',

            'option.0.check' => 'nullable|string|in:on',
            'option.0.ps_network_id' => 'required_if:option.0.check,on|email',
            'option.0.ps_network_password' => 'required_if:option.0.check,on|string|min:6|max:32',
            'option.0.ps_2fa_code' => 'required_if:option.0.check,on|string|min:6|max:32',

            'option.1.check' => 'nullable|string|in:on',
            'option.1.ps_birthday' => 'required_if:option.1.check,on|date_format:Y-m-d',
            'verification_code' => 'required|integer',
            'deliver_to_chat' => 'nullable|string|in:on',
        ]);

        if ($data['verification_code'] != session('verification_code')) {
            return back()->withErrors(['verification_code' => 'Неверный код подтверждения']);
        }

        session()->forget('verification_code');

        if (!session()->has('order_item_info')) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        $data['uuid'] = session('order_item_info')['uuid'];

        $order_item = OrderItems::where('uuid', $data['uuid'])->first();

        if ($order_item->is_activated) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Код уже активирован']);
        }

        if (!$order_item->is_redeemed) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        if ($order_item->activate_till < now()) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Код уже истек']);
        }

        $order = Order::where('id', $order_item->order_id)->first();

        if (!$order) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Заказ не найден']);
        }

        $option_0 = data_get($data, 'option.0');
        $option_1 = data_get($data, 'option.1');

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

        $user = UserController::updateOrCreate(phone: $data['phone'], data: $data, ym_user_id: data_get($data, 'ym_user_id'));

        $order_item = OrderItems::where('uuid', $data['uuid'])->first();

        // 1. Сначала помечаем как активированный (клиент ввел данные)
        $order_item->update([
            'is_activated' => true,
            'client_info' => $data,
        ]);

        // Логируем начало активации
        $order_item->order->comments()->create([
            'user_id' => $user->id,
            'comment' => "Клиент начал процедуру активации (Email: {$data['email']}, Телефон: {$data['phone']})"
        ]);

        $order_item->update([
            'activated_at' => now(),
            'type_id' => $data['type_id'],
            'purchase_status' => 'pending',
        ]);

        // 2. Обновляем статус заказа, чтобы он стал доступен для исполнения (если все товары активированы)
        $order_items = OrderItems::where('order_id', $order->id)->get();
        $activated_all = $order_items->every('is_activated');

        $order->update([
            'user_id' => $user->id,
            'code_activated' => $activated_all,
        ]);

        // 3. Пытаемся сделать автозакупку через Wildflow
        $product = WildflowCatalog::where('sku', $order_item->sku)->first();

        if ($product) {
            $service_sku = data_get($product, 'data.data.product.sku');
            $service_price = data_get($product, 'data.data.price');
            $service = new WildflowService();

            try {
            $order = $order_item->order;
            $is_fake = data_get($order->info, 'fake', false);

            if (!$is_fake) {
                $service->createOrder($service_sku, $order_item->uuid, $service_price, $order_item->count);
                
                $order->comments()->create([
                    'user_id' => $user->id,
                    'comment' => "Запрос на автозакупку отправлен (SKU: $service_sku, Цена: $service_price)"
                ]);
            } else {
                \Log::info("Автозакуп пропущен: Тестовый заказ", ['uuid' => $order_item->uuid]);
                $order->comments()->create([
                    'user_id' => $user->id,
                    'comment' => "Автозакуп пропущен: Тестовый заказ"
                ]);
            }
                sleep(1);
                $cards = $service->getCards($order_item->uuid);
                $original_code = data_get($cards, '0.card_number');

                if ($original_code) {
                    $order->comments()->create([
                        'user_id' => $user->id,
                        'comment' => "Автозакупка успешна. Получен код: " . Str::mask($original_code, '*', 4, -4)
                    ]);
                }

                $order_item->update([
                    'purchase_status' => 'success',
                    'original_code' => $original_code,
                ]);

                // Проверяем, если все товары в заказе успешно закуплены — закрываем заказ автоматически
                if ($order_items->every(fn($item) => $item->purchase_status === 'success' || $item->id === $order_item->id)) {
                    $order->update(['progress_id' => 4]); // 4 - Выполнено
                }

                // Отправляем email с кодом только при успешной закупке
                Mail::to($user->email)->send(new SendActivationCode($original_code, $order));

                // Дублируем в чат Яндекс.Маркета, если выбрано пользователем
                if ($order->chat_id && data_get($data, 'deliver_to_chat') === 'on') {
                    try {
                        $ymService = new \App\Http\Services\YmService($order->shop);
                        $ymService->sendMessage($order->chat_id, view('chat.send_code_message', ['code' => $original_code, 'shop' => $order->shop])->render());
                        
                        $order->comments()->create([
                            'user_id' => $user->id,
                            'comment' => "Код успешно дублирован в чат Яндекс.Маркета"
                        ]);
                    } catch (\Exception $chatE) {
                        \Log::error('YM Chat send error', [$chatE->getMessage()]);
                        $order->comments()->create([
                            'user_id' => $user->id,
                            'comment' => "Ошибка отправки кода в чат: " . $chatE->getMessage()
                        ]);
                    }
                }

            } catch (\Exception $e) {
                \Log::error('wildflow error', [$e->getMessage()]);

                $order->comments()->create([
                    'user_id' => $user->id,
                    'comment' => "Ошибка автозакупки: " . $e->getMessage()
                ]);

                $order_item->update([
                    'purchase_status' => 'failed',
                    'purchase_error' => $e->getMessage(),
                ]);
            }
        } else {
            // Если товара нет в каталоге Wildflow — помечаем для ручной обработки
            $order_item->update([
                'purchase_status' => 'manual',
            ]);
        }

        return redirect()->route('redeem.success');
    }

    public function checkCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|regex:/^[A-Z0-9-]+$/',
        ]);

        $order_item = OrderItems::where('key', $data['code'])->first();

        if (!$order_item) {
            return back()->withErrors(['code' => 'Введен неверный или несуществующий код']);
        }

        if ($order_item->is_activated) {
            return redirect()->route('redeem.code')->withErrors(['code' => 'Код уже успешно активирован. Мы свяжемся с Вами.']);
        }

        $order = Order::where('id', $order_item->order_id)->first();

        if (!$order) {
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

        if (!$order_item->redeem_started_at) {
            $order_item->update(['redeem_started_at' => now()]);
        }

        return redirect()->temporarySignedRoute('redeem.email', now()->addHours());
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

        $hosts = $app_domain . ',' . Settings::get('TRUSTED_HOSTS', config('services.trusted_hosts'));

        $trusted_hosts_array = explode(',', $hosts);

        // Include all active shop domains
        $shop_domains = \App\Models\Shop::where('is_active', true)
            ->pluck('domain')
            ->filter()
            ->toArray();

        $trusted_hosts_array = array_unique(array_merge($trusted_hosts_array, $shop_domains));

        if (!in_array($host, $trusted_hosts_array)) {
            \Illuminate\Support\Facades\Log::warning("Domain mismatch in getCodeView: '$host' not in trusted list [" . implode(',', $trusted_hosts_array) . "]");
            abort(403, "Domain $host is not allowed");
        }

        session()->put('is_frame', (bool)data_get($data, 'is_frame'));

        // 1. Try to find shop by query parameter (Highest priority)
        $shopSlug = $request->query('shop');
        $current_shop = null;
        $prefix = null;
        
        if ($shopSlug) {
            // Sanitize: allow only alphanumeric and dashes
            $shopSlug = preg_replace('/[^A-Z0-9-]/i', '', $shopSlug);
            $cleanSlug = rtrim($shopSlug, '-');
            $current_shop = \App\Models\Shop::where('voucher_prefix', $cleanSlug)
                ->orWhere('voucher_prefix', $cleanSlug . '-')
                ->first();
                
            // Security: if shop parameter is provided but not found, 
            // just use the provided slug as a prefix instead of 404ing.
            $prefix = $current_shop?->voucher_prefix ?? ($cleanSlug . '-');
        }

        // 2. If no shop context from parameter, fallback to host/domain detection
        if (!$current_shop) {
            $current_shop = \App\Models\Shop::where('domain', $host)
                ->orWhere(fn($q) => $host !== $app_domain ? $q->where('domain', 'like', "%$host%") : null)
                ->first();
                
            // Last resort for Meanly domain
            if (!$current_shop && str_contains($host, 'meanly.ru')) {
                $current_shop = \App\Models\Shop::where('domain', 'like', '%meanly.ru%')->first();
            }
            
            $prefix = $prefix ?: ($current_shop?->voucher_prefix ?? 'W1C-');
        }

        // Ensure prefix ends with a dash for consistent UI
        if ($prefix && !str_ends_with($prefix, '-')) {
            $prefix .= '-';
        }

        return view('redeem.step1', compact('prefix'));
    }
}
