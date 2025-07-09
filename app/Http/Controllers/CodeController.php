<?php

namespace App\Http\Controllers;

use App\Helpers\SendMessage;
use App\Http\Services\TelegramService;
use App\Jobs\SendTelegramJob;
use App\Models\Order;
use App\Models\OrderItems;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CodeController extends Controller
{

    public function getFinishView(Request $request)
    {
        $data = $request->validate(['is_frame' => 'nullable|string|in:1,0',]);

        return view('finish', ['is_frame' => (bool)data_get($data, 'is_frame')]);
    }

    public function sendForm(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|regex:/^1GROS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/',
            'is_frame' => 'nullable|string|in:1,0',
            'first_name' => 'required|string|min:2|max:100',
            'last_name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
            'phone' => 'required|regex:/^\+7 \(\d{3}\) \d{3}-\d{2}-\d{2}$/',

            'option.0.check' => 'nullable|string|in:on',
            'option.0.ps_network_id' => 'required_if:option.0.check,on|email',
            'option.0.ps_network_password' => 'required_if:option.0.check,on|string|min:6|max:32',
            'option.0.ps_2fa_code' => 'nullable|string|min:6|max:32',

            'option.1.check' => 'nullable|string|in:on',
            'option.1.ps_birthday' => 'required_if:option.1.check,on|date_format:Y-m-d',
        ]);

        $order_item = OrderItems::where('key', $data['code'])->first();

        if ($order_item->is_activated) {
            return redirect()->route('redeem')->withErrors(['code' => 'Код уже активирован']);
        }

        if (!$order_item->is_redeemed) {
            return redirect()->route('redeem')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        if ($order_item->activate_till < now()) {
            return redirect()->route('redeem')->withErrors(['code' => 'Код уже истек']);
        }

        $order = Order::where('id', $order_item->order_id)->first();

        $option_0 = data_get($data, 'option.0');
        $option_1 = data_get($data, 'option.1');

        if ($option_0) {
            $data['option'] = $option_0;
        } elseif ($option_1) {
            $data['option'] = $option_1;
        }

        $order_item = OrderItems::where('key', $data['code'])->first();

        $order_item->update([
            'is_activated' => true,
            'client_info' => $data,
        ]);

        SendTelegramJob::dispatchSync(order: $order, status: 'send_form', order_item_id: $order_item->id);

        return view('finish', ['is_frame' => (bool)data_get($data, 'is_frame')]);
    }

    public function getViewForm(Request $request)
    {
        $data = $request->validate(['code' => 'required|string|regex:/^1GROS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', 'is_frame' => 'nullable|string|in:1,0']);

        if (!$request->hasValidSignature()) {
            return redirect()->route('redeem')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        $order_item = OrderItems::where('key', $data['code'])->where('is_activated', false)->first();

        if (!$order_item) {
            return back()->withErrors(['code' => 'Введен неверный или несуществующий код']);
        }

        $order = Order::find($order_item->order_id);

        return view('form', ['code' => data_get($data, 'code'), 'is_frame' => (bool)data_get($data, 'is_frame'), 'client_info' => $order->client_info]);
    }

    public function checkCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|regex:/^1GROS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/',
            'is_frame' => 'nullable|string|in:1,0',
        ]);

        $order_item = OrderItems::where('key', $data['code'])->where('is_activated', false)->first();

        if (!$order_item) {
            return back()->withErrors(['code' => 'Введен неверный или несуществующий код']);
        }

        $order_item->update(['is_redeemed' => true]);

        $order = Order::where('id', $order_item->order_id)->first();

        if (!$order) {
            return back()->withErrors(['code' => 'Заказ не был найден']);
        }

        return redirect()->temporarySignedRoute('form', now()->addHours(), ['code' => $data['code'], 'is_frame' => (bool)data_get($data, 'is_frame')]);
    }

    public function getCodeView(Request $request)
    {
        try {
            $data = $request->validate(['is_frame' => 'nullable|string|in:1,0']);
        } catch (ValidationException $exception) {
            abort(403, $exception->getMessage());
        }

        $host = $request->httpHost();

        $trusted_hosts = explode(',', config('services.trusted_hosts'));

        if (!in_array($host, $trusted_hosts)) {
            abort(403);
        }

        return view('redeem', ['is_frame' => (bool)data_get($data, 'is_frame')]);
    }
}
