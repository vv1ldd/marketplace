<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramJob;
use App\Models\Order\Order;
use App\Models\Order\OrderItems;
use App\Models\PlayStation\PlayStationAlt;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\Factory;
use Illuminate\View\View;

class CodeController extends Controller
{
    public function checkEmail(Request $request): View|Factory|RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email']);

        if (!session()->has('order_item_info')) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        session()->put('user_exists', User::where('email', $data['email'])->exists());

        return redirect()->temporarySignedRoute('redeem.step3', now()->addHours());
    }

    public function getEmailView(Request $request): View|Factory|RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        return view('redeem.step2');
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
        ]);

        if (!session()->has('order_item_info')) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        $data['uuid'] = session('order_item_info')['uuid'];

        $order_item = OrderItems::where('uuid', $data['uuid'])->first();

        if ($order_item->is_activated) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Код уже активирован']);
        }

        if (!$order_item->is_redeemed) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        if ($order_item->activate_till < now()) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Код уже истек']);
        }

        $order = Order::where('id', $order_item->order_id)->first();

        if (!$order) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Заказ не найден']);
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

        $order_item->update([
            'is_activated' => true,
            'client_info' => $data,
            'activated_at' => now(),
            'type_id' => $data['type_id'],
        ]);

        $order_items = OrderItems::where('order_id', $order->id)->get();

        $activated_all = false;

        foreach ($order_items as $item) {
            if ($item->is_activated) {
                $activated_all = true;
            } else {
                $activated_all = false;
                break;
            }
        }

        $order->update([
            'user_id' => $user->id,
            'code_activated' => $activated_all,
        ]);

        SendTelegramJob::dispatchSync(order_id: $order->order_id, status: 'send_form', order_item_id: $order_item->id);

        return view('finish');
    }

    public function getViewForm(Request $request)
    {
        if (!$request->hasValidSignature() || !session()->has('order_item_info')) {
            return redirect()->route('redeem.step1')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        $data = [
            'uuid' => session('order_item_info')['uuid'],
        ];

        $order_item = OrderItems::where('uuid', $data['uuid'])->first();

        if (!$order_item) {
            return view('redeem')->withErrors(['code' => 'Введен неверный или несуществующий код']);
        }

        $order = Order::find($order_item->order_id);

        if (!$order) {
            return view('redeem')->withErrors(['code' => 'Заказ не был найден']);
        }

        return view('redeem.step3', ['client_info' => $order->client_info]);
    }

    public function checkCode(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|regex:/^1GROS-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/',
        ]);

        $order_item = OrderItems::where('key', $data['code'])->first();

        if (!$order_item) {
            return back()->withErrors(['code' => 'Введен неверный или несуществующий код']);
        }

        if ($order_item->is_activated) {
            return redirect()->route('redeem')->withErrors(['code' => 'Код уже успешно активирован. Мы свяжемся с Вами.']);
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

        return redirect()->temporarySignedRoute('redeem.step2', now()->addHours());
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

        if (!in_array($host, $trusted_hosts_array)) {
            abort(403);
        }

        session()->put('is_frame', (bool)data_get($data, 'is_frame'));

        return view('redeem.step1');
    }
}
