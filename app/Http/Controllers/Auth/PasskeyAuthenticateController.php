<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Services\IntentLedgerService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;
use Spatie\LaravelPasskeys\Http\Requests\AuthenticateUsingPasskeysRequest;
use Spatie\LaravelPasskeys\Http\Controllers\AuthenticateUsingPasskeyController as BaseController;
use Spatie\LaravelPasskeys\Models\Passkey;
use Spatie\LaravelPasskeys\Support\Config;

class PasskeyAuthenticateController extends BaseController
{
    public function __invoke(AuthenticateUsingPasskeysRequest $request)
    {
        $options = Session::get('passkey-authentication-options');

        if (! is_string($options) || $options === '') {
            $options = $this->cachedOptionsForAssertion((string) $request->input('start_authentication_response'));

            if (is_string($options) && $options !== '') {
                Session::put('passkey-authentication-options', $options);
            }
        }

        if (! is_string($options) || $options === '') {
            session()->flash('authenticatePasskey::message', 'Контекст входа устарел. Нажмите «Войти» еще раз и подтвердите Passkey.');

            return back();
        }

        $findAuthenticatableUsingPasskey = Config::getAction(
            'find_passkey',
            FindPasskeyToAuthenticateAction::class,
        );

        $passkey = $findAuthenticatableUsingPasskey->execute(
            $request->input('start_authentication_response'),
            $options,
        );

        if (! $passkey) {
            return $this->invalidPasskeyResponse();
        }

        $authenticatable = $passkey->authenticatable;

        if (! $authenticatable) {
            return $this->invalidPasskeyResponse();
        }

        $ledgerEntry = $this->recordAuthLoginIntent(
            request: $request,
            authenticatable: $authenticatable,
            passkey: $passkey,
            optionsJson: $options,
            redirectTarget: $this->defaultRedirectTarget($authenticatable),
        );

        $this->forgetCachedOptions($options);
        $this->logInAuthenticatable($authenticatable, $request->boolean('remember'));

        if ($authenticatable instanceof User) {
            $seller = $authenticatable->primarySellerAccount();

            if ($seller) {
                auth()->guard('sellers')->login($seller, $request->boolean('remember'));
            }
        }

        Session::put('auth_login_ledger_id', $ledgerEntry->id);
        Session::put('auth_login_fingerprint', $ledgerEntry->fingerprint);

        event(new PasskeyUsedToAuthenticateEvent($passkey, $request));

        return $this->validPasskeyResponse($request);
    }

    protected function validPasskeyResponse(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user) {
            // Sovereign validator identities land in the operations console.
            if ($user->hasOpsSovereignAccess()) {
                return redirect()->intended('/ops');
            }

            if ($user->isMerchantNode()) {
                return redirect('/merchant');
            }

        }

        return redirect('/vault');
    }

    private function cachedOptionsForAssertion(string $assertionJson): ?string
    {
        $assertion = json_decode($assertionJson, true);
        $clientDataJson = (string) data_get($assertion, 'response.clientDataJSON', '');
        $clientData = json_decode($this->base64UrlDecode($clientDataJson), true);
        $challenge = (string) data_get($clientData, 'challenge', '');

        if ($challenge === '') {
            return null;
        }

        return Cache::pull($this->cacheKey($challenge));
    }

    private function recordAuthLoginIntent(
        Request $request,
        Authenticatable $authenticatable,
        Passkey $passkey,
        string $optionsJson,
        string $redirectTarget,
    ): \App\Models\SovereignLedger {
        $challenge = (string) data_get(json_decode($optionsJson, true), 'challenge', '');

        return app(IntentLedgerService::class)->record(
            eventType: 'AUTH_LOGIN_INTENT',
            intentType: 'auth.login',
            entity: $authenticatable instanceof \Illuminate\Database\Eloquent\Model ? $authenticatable : null,
            payload: [
                'challenge_hash' => $challenge !== '' ? hash('sha256', $challenge) : null,
                'remember_requested' => $request->boolean('remember'),
                'redirect_target' => $redirectTarget,
                'logged_in_at' => now()->toIso8601String(),
            ],
            request: $request,
            passkey: $passkey,
            user: $authenticatable instanceof User ? $authenticatable : null,
            scope: 'auth.session',
            resource: 'session',
            triggerSource: 'DID:SYS | AUTH_LOGIN:#'.$authenticatable->getAuthIdentifier(),
        );
    }

    private function defaultRedirectTarget(Authenticatable $authenticatable): string
    {
        if ($authenticatable instanceof User) {
            if ($authenticatable->hasOpsSovereignAccess()) {
                return '/ops';
            }

            if ($authenticatable->isMerchantNode()) {
                return '/merchant';
            }

        }

        return '/vault';
    }

    private function forgetCachedOptions(string $optionsJson): void
    {
        $challenge = (string) data_get(json_decode($optionsJson, true), 'challenge', '');

        if ($challenge !== '') {
            Cache::forget($this->cacheKey($challenge));
        }
    }

    private function base64UrlDecode(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

        return base64_decode($base64, true) ?: '';
    }

    private function cacheKey(string $challenge): string
    {
        return 'passkeys:authentication-options:'.sha1($challenge);
    }
}
