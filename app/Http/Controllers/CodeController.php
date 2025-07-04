<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CodeController extends Controller
{

    public function sendForm(Request $request)
    {
        $data = $request->validate([
            'order_uuid' => 'required|uuid',
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

        dd($request->all());
    }
    public function getViewForm(Request $request)
    {
        $data = $request->validate(['order_uuid' => 'required|uuid']);

        if (!$request->hasValidSignature()) {
            return redirect()->route('check-code')->withErrors(['code' => 'Необходимо заново ввести код']);
        }

        return view('form', ['order_uuid' => data_get($data, 'order_uuid')]);
    }

    public function checkCode(Request $request)
    {
        $data = $request->validate(['code' => 'required|string']);

        //TODO проверять в таблице заказов, если соответствует редиректить на подписанную form

        // обмена кода на uuid записи заказа

        $uuid = Str::uuid()->toString();

        return redirect()->temporarySignedRoute('form', now()->addHours(), ['order_uuid' => $uuid]);
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

        return view('check-code', ['is_frame' => (bool)data_get($data, 'is_frame')]);
    }
}
