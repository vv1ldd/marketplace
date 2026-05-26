<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction;
use Spatie\LaravelPasskeys\Support\Config;

class PasskeyAuthenticationOptionsController
{
    public function __invoke()
    {
        $action = Config::getAction(
            'generate_passkey_authentication_options',
            GeneratePasskeyAuthenticationOptionsAction::class,
        );

        $options = $action->execute();

        Session::put('passkey-authentication-options', $options);
        Session::save();

        $challenge = (string) data_get(json_decode($options, true), 'challenge');

        if ($challenge !== '') {
            Cache::put($this->cacheKey($challenge), $options, now()->addMinutes(5));
        }

        return $options;
    }

    private function cacheKey(string $challenge): string
    {
        return 'passkeys:authentication-options:'.sha1($challenge);
    }
}
