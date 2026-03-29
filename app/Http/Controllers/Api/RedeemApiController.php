<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\UserController;
use App\Jobs\SendTelegramJob;
use App\Mail\VerificationCodeMail;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class RedeemApiController extends Controller
{
    /**
     * Verify if the redeem code is valid.
     */
    public function verifyCode(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|regex:/^W1C-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/',
        ]);

        $code = $request->input('code');
        $order_item = OrderItems::where('key', $code)->first();

        if (!$order_item) {
            return response()->json(['message' => 'Введен неверный или несуществующий код'], 404);
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
            ]
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

        // Check if code exists to prevent spam
        if (!OrderItems::where('key', $code)->exists()) {
            return response()->json(['message' => 'Код не найден'], 404);
        }

        $verificationCode = rand(100000, 999999);
        
        // Store in cache for 1 hour, keyed by the redeem code
        Cache::put("redeem_verification:{$code}", [
            'verification_code' => $verificationCode,
            'email' => $email
        ], now()->addHour());

        Mail::to($email)->send(new VerificationCodeMail($verificationCode));

        return response()->json([
            'status' => 'success',
            'message' => 'Код подтверждения отправлен на почту'
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
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'required|email',
            'phone' => 'nullable|string',

            'option.0.check' => 'nullable|string|in:on,1',
            'option.0.ps_network_id' => 'required_if:option.0.check,on,1|email',
            'option.0.ps_network_password' => 'required_if:option.0.check,on,1|string|min:6|max:32',
            'option.0.ps_2fa_code' => 'required_if:option.0.check,on,1|string|min:6|max:32',

            'option.1.check' => 'nullable|string|in:on,1',
            'option.1.ps_birthday' => 'required_if:option.1.check,on,1|date_format:Y-m-d',
        ]);

        $code = $request->input('code');
        $verificationCodeInput = $request->input('verification_code');

        // Bypass check if verified via Passkey or trusted by storefront
        if (! in_array($verificationCodeInput, ['PASSKEY_AUTH', 'TRUSTED_USER'])) {
            $cachedData = Cache::get("redeem_verification:{$code}");

            if (!$cachedData || $verificationCodeInput != $cachedData['verification_code']) {
                return response()->json(['message' => 'Неверный или истекший код подтверждения'], 422);
            }
        }

        $order_item = OrderItems::where('key', $code)->first();

        if (!$order_item) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        if ($order_item->is_activated) {
            return response()->json(['message' => 'Код уже активирован'], 422);
        }

        $order = Order::find($order_item->order_id);
        if (!$order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        $data = $request->all();

        // Forced override: Use contact info from the API Application (the shop)
        $apiApplication = $request->attributes->get('api_application');
        if ($apiApplication && $apiApplication->first_name && $apiApplication->last_name) {
            $data['first_name'] = $apiApplication->first_name;
            $data['last_name']  = $apiApplication->last_name;
            $data['phone']      = $apiApplication->phone ?: $data['phone'];
        } else {
            // Global defaults if no application context
            $data['first_name'] = $data['first_name'] ?: 'Пользователь';
            $data['last_name']  = $data['last_name'] ?: 'Meanly';
            $data['phone']      = $data['phone'] ?: null;
        }

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

        $user = UserController::updateOrCreate(phone: $data['phone'] ?? null, data: $data);

        $order_item->update([
            'is_activated' => true,
            'client_info' => $data,
            'activated_at' => now(),
            'type_id' => $data['type_id'],
        ]);

        // Same logic as CodeController to update order status
        $order_items = OrderItems::where('order_id', $order->id)->get();
        $activated_all = $order_items->every('is_activated');

        $order->update([
            'user_id' => $user->id,
            'code_activated' => $activated_all,
        ]);

        SendTelegramJob::dispatchSync(order_id: $order->order_id, status: 'send_form', order_item_id: $order_item->id);

        // Clear cache
        Cache::forget("redeem_verification:{$code}");

        return response()->json([
            'status' => 'success',
            'message' => 'Активация успешно завершена'
        ]);
    }
}
