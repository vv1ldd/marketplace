<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IntentLedgerService;
use App\Services\L1IdentityService;
use App\Services\SimpleL1IdentityRegistryService;
use App\Services\SimpleL1ProtocolClient;
use App\Services\SimpleL1VerificationResultService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleLayer\Sl1e\Exception\Sl1eValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SimpleL1ConnectController extends Controller
{
    public function connect(Request $request): RedirectResponse|View|JsonResponse
    {
        $returnTo = $this->safeReturnTo((string) $request->query('return_to', '/store'));
        $requestedMode = (string) $request->query('mode', 'login');
        $flow = $requestedMode === 'connect' ? 'connect' : null;
        $mode = $requestedMode === 'register' ? 'register' : 'login';
        $state = Str::random(40);
        $nonce = Str::random(40);
        $redirectUri = $this->absoluteCurrentHostRoute($request, 'meanly.simple_l1.callback');
        $intent = [
            'intent_type' => $this->cleanIntentParam($request->query('intent_type'), 80),
            'intent_title' => $this->cleanIntentParam($request->query('intent_title'), 96),
            'intent_description' => $this->cleanIntentParam($request->query('intent_description'), 220),
            'intent_cta' => $this->cleanIntentParam($request->query('intent_cta'), 64),
            'intent_nonce' => $this->cleanIntentParam($request->query('intent_nonce'), 80),
            'intent_resource' => $this->cleanIntentParam($request->query('intent_resource'), 160),
        ];

        session([
            'simple_l1_connect.state' => $state,
            'simple_l1_connect.nonce' => $nonce,
            'simple_l1_connect.client_id' => config('simple_l1.client_id', $request->getHost()),
            'simple_l1_connect.redirect_uri' => $redirectUri,
            'simple_l1_connect.return_to' => $returnTo,
            'simple_l1_connect.mode' => $mode,
            'simple_l1_connect.flow' => $flow,
            'simple_l1_connect.intent' => array_filter($intent),
        ]);
        Cache::put($this->connectStateCacheKey($state), [
            'state' => $state,
            'nonce' => $nonce,
            'client_id' => config('simple_l1.client_id', $request->getHost()),
            'redirect_uri' => $redirectUri,
            'return_to' => $returnTo,
            'mode' => $mode,
            'flow' => $flow,
            'intent' => array_filter($intent),
            'host' => $request->getHost(),
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        app(IntentLedgerService::class)->record(
            eventType: 'IDENTITY_CONNECT_EXTERNAL_START_INTENT',
            intentType: 'identity.connect_external.start',
            entity: $request->user(),
            payload: [
                'state_hash' => hash('sha256', $state),
                'return_to_hash' => hash('sha256', $returnTo),
                'mode' => $mode,
                'intent_type' => $intent['intent_type'] ?: null,
                'started_at' => now()->toIso8601String(),
            ],
            request: $request,
            user: $request->user(),
            scope: 'identity.external',
            resource: 'simple-l1-wallet',
        );

        $simpleL1Client = app(SimpleL1ProtocolClient::class);
        $authorizeUrl = $simpleL1Client->authorizationUrl(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            intent: $intent,
            flow: $flow,
            identityHint: $this->simpleL1IdentityHint($request),
            uiLocale: $this->simpleL1Locale($request),
        );
        $deepLinkUrl = $simpleL1Client->authorizationDeepLinkUrl(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            intent: $intent,
            flow: $flow,
            identityHint: $this->simpleL1IdentityHint($request),
            uiLocale: $this->simpleL1Locale($request),
        );
        $nativeDeepLinkAutoLaunch = (bool) config('simple_l1.native_deep_link_auto_launch', false);
        $handoff = $this->simpleL1HandoffContext($mode, $intent, $returnTo);

        if (! $this->shouldShowSimpleL1Handoff($request, $handoff['key'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'show_handoff' => false,
                    'redirect_url' => $authorizeUrl,
                    'deep_link_url' => $deepLinkUrl,
                    'native_auto_launch' => $nativeDeepLinkAutoLaunch,
                    'handoff_state' => $state,
                    'status_url' => $this->absoluteCurrentHostRoute($request, 'meanly.simple_l1.status', ['state' => $state]),
                    'return_to' => $returnTo,
                ]);
            }

            return redirect()->away($authorizeUrl);
        }

        $this->markSimpleL1HandoffSeen($request, $handoff['key']);

        if ($request->expectsJson()) {
            return response()->json([
                'show_handoff' => true,
                'redirect_url' => $authorizeUrl,
                'deep_link_url' => $deepLinkUrl,
                'native_auto_launch' => $nativeDeepLinkAutoLaunch,
                    'handoff_state' => $state,
                    'status_url' => $this->absoluteCurrentHostRoute($request, 'meanly.simple_l1.status', ['state' => $state]),
                    'return_to' => $returnTo,
                'handoff' => $handoff,
            ]);
        }

        return view('auth.simple-l1-handoff', [
            'authorizeUrl' => $authorizeUrl,
            'deepLinkUrl' => $deepLinkUrl,
            'nativeDeepLinkAutoLaunch' => $nativeDeepLinkAutoLaunch,
            'mode' => $mode,
            'intentTitle' => $intent['intent_title'] ?: null,
            'returnTo' => $returnTo,
            'handoffState' => $state,
            'statusUrl' => $this->absoluteCurrentHostRoute($request, 'meanly.simple_l1.status', ['state' => $state]),
            'handoff' => $handoff,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $callbackState = (string) $request->input('state', '');
        $connectContext = $this->connectContextForCallback($request, $callbackState);
        $expectedState = (string) data_get($connectContext, 'state', '');
        $expectedNonce = (string) data_get($connectContext, 'nonce', '');
        $expectedClientId = (string) data_get($connectContext, 'client_id', config('simple_l1.client_id'));
        $expectedRedirectUri = (string) data_get(
            $connectContext,
            'redirect_uri',
            $this->absoluteCurrentHostRoute($request, 'meanly.simple_l1.callback')
        );
        $returnTo = $this->safeReturnTo((string) data_get($connectContext, 'return_to', '/store'));
        $mode = (string) data_get($connectContext, 'mode', 'login');

        abort_unless($expectedState !== '' && hash_equals($expectedState, $callbackState), 403);

        $code = trim((string) $request->input('code', ''));
        $proofToken = trim((string) $request->input('proof_token', ''));
        $isNativeDirectProof = false;
        $verificationResult = null;
        $directProofResponse = config('simple_l1.accept_native_direct_proof', false)
            ? $this->simpleL1DirectProofResponse(
                $request->input('proof_response', $request->input('proof')),
                $proofToken,
            )
            : null;
        abort_if($code === '' && $proofToken === '' && $directProofResponse === null, 422, 'Simple L1 authorization code is missing.');

        if ($code !== '') {
            $proofResponse = app(SimpleL1ProtocolClient::class)->exchangeAuthorizationCode($code, $expectedClientId, $expectedRedirectUri);
            $proofToken = (string) data_get($proofResponse, 'proof_token', '');
        } elseif ($directProofResponse !== null) {
            $isNativeDirectProof = true;
            $proofResponse = $directProofResponse;
            $proofToken = (string) data_get($proofResponse, 'proof_token', $proofToken);
            try {
                if (config('simple_l1.require_native_direct_proof_signature', true)) {
                    $this->assertNativeDirectProofSignature($proofResponse);
                }
                app(SimpleL1IdentityRegistryService::class)->assertNativeDirectProofCanAuthenticate(
                    $proofResponse,
                    Auth::user(),
                    data_get($connectContext, 'flow') === 'connect',
                );
            } catch (HttpExceptionInterface $exception) {
                $this->recordRejectedNativeVerificationResult($proofResponse, $exception->getMessage());
                if ($this->shouldReturnNativeDirectProofToMarketplace($exception, $connectContext)) {
                    return redirect($this->safeReturnTo((string) data_get($connectContext, 'return_to', '/store')))
                        ->with('status', 'Meanly app approval was not accepted for this marketplace yet. Continue online if you want to use this browser.');
                }
                throw $exception;
            }
        } else {
            $proofResponse = app(SimpleL1ProtocolClient::class)->introspectProof($proofToken);
        }

        abort_if($proofToken === '', 422, 'Simple L1 proof token is missing.');
        abort_unless((bool) data_get($proofResponse, 'active'), 422, 'Simple L1 proof is not active.');

        try {
            $identityProof = app(SimpleL1ProtocolClient::class)->validateProof(
                proofResponse: $proofResponse,
                proofToken: $proofToken,
                clientId: $expectedClientId,
                redirectUri: $expectedRedirectUri,
                state: $expectedState,
                nonce: $expectedNonce,
                mode: $mode,
            );
        } catch (Sl1eValidationException $exception) {
            if ($isNativeDirectProof) {
                $this->recordRejectedNativeVerificationResult($proofResponse, $exception->getMessage());
            }
            $status = str_contains($exception->getMessage(), 'malformed') || str_contains($exception->getMessage(), 'missing') ? 422 : 403;
            abort($status, $exception->getMessage());
        }
        if ($isNativeDirectProof) {
            $verificationResult = app(SimpleL1VerificationResultService::class)->accepted($proofResponse);
        }

        $l1Address = $identityProof->entityAddress;
        $keyAddress = $identityProof->keyAddress;
        $alias = $this->simpleL1Alias($proofResponse);
        $displayAlias = $this->simpleL1DisplayAlias($proofResponse);
        $this->releaseConflictingAuthenticatedAccountForConnectFlow($connectContext, $l1Address);
        $user = $this->resolveOrCreateUserFromProof($proofResponse, $l1Address, $keyAddress);
        $username = $user?->username;
        $publicUsername = $user?->publicUsername();
        if ($isNativeDirectProof && $user instanceof User) {
            app(SimpleL1IdentityRegistryService::class)->recordNativeDirectProof($proofResponse, $user, $verificationResult);
        }

        if ($user && (int) Auth::id() !== (int) $user->id) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $proofHandle = Str::random(48);
        Cache::put($this->proofTokenCacheKey($proofHandle), $proofToken, now()->addMinutes(10));
        $connectIntent = (array) data_get($connectContext, 'intent', []);
        $returnPath = parse_url($returnTo, PHP_URL_PATH) ?: $returnTo;
        $opensVault = data_get($connectIntent, 'intent_type') === 'meanly.vault.open'
            || str_starts_with($returnPath, '/vault')
            || str_starts_with($returnPath, '/cabinet');
        $handoff = $this->simpleL1HandoffContext($mode, $connectIntent, $returnTo);

        $sessionIdentity = [
            'l1_address' => strtolower($l1Address),
            'entity_l1_address' => strtolower($l1Address),
            'key_l1_address' => $keyAddress,
            'username' => $username,
            'alias' => $alias,
            'display_alias' => $publicUsername ?: $displayAlias,
            'proof_token_hash' => $identityProof->proofTokenHash,
            'proof_handle' => $proofHandle,
            'proof' => $identityProof->proof,
            'mode' => $identityProof->mode,
            'protocol' => 'simple-l1',
            'connected_at' => now()->toIso8601String(),
        ];
        $this->storeSimpleL1IdentityInSession($request, $sessionIdentity, strtolower($l1Address), $opensVault);
        $this->markSimpleL1HandoffSeen($request, $handoff['key'], $user);
        Cache::put($this->browserHandoffCompletionCacheKey($expectedState), [
            'user_id' => $user?->id,
            'identity' => $sessionIdentity,
            'sovereign_l1_address' => strtolower($l1Address),
            'opens_vault' => $opensVault,
            'return_to' => $returnTo,
            'completed_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));
        session()->forget([
            'simple_l1_connect.state',
            'simple_l1_connect.nonce',
            'simple_l1_connect.client_id',
            'simple_l1_connect.redirect_uri',
            'simple_l1_connect.return_to',
            'simple_l1_connect.mode',
            'simple_l1_connect.flow',
            'simple_l1_connect.intent',
        ]);
        Cache::forget($this->connectStateCacheKey($expectedState));

        $ledgerPayload = [
            'connected_entity_l1_address' => strtolower($l1Address),
            'connected_key_l1_address' => $keyAddress,
            'username' => $username,
            'alias' => $alias,
            'display_alias' => $publicUsername ?: $displayAlias,
            'proof_token_hash' => $identityProof->proofTokenHash,
            'routing_decision_id' => data_get($identityProof->proof, 'routingDecisionId'),
            'policy_version' => data_get($identityProof->proof, 'policyVersion'),
            'return_to_hash' => hash('sha256', $returnTo),
            'mode' => $identityProof->mode,
            'connected_at' => now()->toIso8601String(),
        ];
        if ($verificationResult !== null) {
            $ledgerPayload['verification_result_id'] = data_get($verificationResult, 'verification_result_id');
            $ledgerPayload['verification_result'] = $verificationResult;
        }

        app(IntentLedgerService::class)->record(
            eventType: 'IDENTITY_CONNECT_EXTERNAL_INTENT',
            intentType: 'identity.connect_external',
            entity: $user ?: $request->user(),
            payload: $ledgerPayload,
            request: $request,
            user: $user ?: $request->user(),
            scope: 'identity.external',
            resource: 'simple-l1-wallet',
        );

        return redirect($returnTo)->with('status', 'Simple L1 wallet connected.');
    }

    public function status(Request $request): array
    {
        $redirectUrl = null;
        $state = (string) $request->query('state', '');
        if ($state !== '') {
            $redirectUrl = $this->completeBrowserBoundHandoff($request, $state);
        }

        $identity = session('simple_l1_identity');

        return [
            'protocol' => 'simple-l1',
            'authenticated' => is_array($identity) && ! empty($identity['l1_address']),
            'redirect_url' => $redirectUrl,
            'identity' => is_array($identity) ? [
                'l1_address' => $identity['l1_address'] ?? null,
                'entity_l1_address' => $identity['entity_l1_address'] ?? null,
                'key_l1_address' => $identity['key_l1_address'] ?? null,
                'username' => $identity['username'] ?? null,
                'alias' => $identity['alias'] ?? null,
                'display_alias' => $identity['display_alias'] ?? null,
                'mode' => $identity['mode'] ?? null,
                'protocol' => $identity['protocol'] ?? 'simple-l1',
                'connected_at' => $identity['connected_at'] ?? null,
            ] : null,
        ];
    }

    public function complete(Request $request): RedirectResponse
    {
        abort_unless(is_array(session('simple_l1_identity')) && session('simple_l1_identity.entity_l1_address'), 403);

        $next = $this->safeReturnTo((string) $request->query('next', '/vault'));
        return redirect($next);
    }

    private function safeReturnTo(string $returnTo): string
    {
        $returnTo = trim($returnTo);

        if ($returnTo === '') {
            return '/store';
        }

        if (str_starts_with($returnTo, '/') && ! str_starts_with($returnTo, '//')) {
            return $returnTo;
        }

        $parts = parse_url($returnTo);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '/store';
        }

        $port = $parts['port'] ?? null;
        $origin = $scheme.'://'.$host.($port !== null ? ':'.$port : '');
        $allowedOrigins = collect((array) config('storefront.allowed_return_origins', []))
            ->map(fn (string $allowed): string => rtrim(strtolower($allowed), '/'))
            ->filter()
            ->values();
        if (! $allowedOrigins->contains(rtrim(strtolower($origin), '/'))) {
            return '/store';
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return '/store';
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $origin.$path.$query.$fragment;
    }

    /**
     * Native app callbacks can return through a browser context that does not
     * carry the original Laravel session cookie. Recover the OAuth state from a
     * short-lived server-side cache while still verifying the random state value.
     *
     * @return array<string, mixed>
     */
    private function connectContextForCallback(Request $request, string $state): array
    {
        $sessionState = (string) session('simple_l1_connect.state');
        if ($sessionState !== '' && hash_equals($sessionState, $state)) {
            return [
                'state' => $sessionState,
                'nonce' => (string) session('simple_l1_connect.nonce'),
                'client_id' => (string) session('simple_l1_connect.client_id', config('simple_l1.client_id')),
                'redirect_uri' => (string) session(
                    'simple_l1_connect.redirect_uri',
                    $this->absoluteCurrentHostRoute($request, 'meanly.simple_l1.callback')
                ),
                'return_to' => (string) session('simple_l1_connect.return_to', '/store'),
                'mode' => (string) session('simple_l1_connect.mode', 'login'),
                'flow' => session('simple_l1_connect.flow'),
                'intent' => (array) session('simple_l1_connect.intent', []),
            ];
        }

        if ($state === '') {
            return [];
        }

        $cached = Cache::get($this->connectStateCacheKey($state));

        return is_array($cached) ? $cached : [];
    }

    /**
     * Named routes are registered once per public domain. Use a path-only route
     * and attach the current request host so auth round-trips stay on the market
     * domain that initiated them.
     */
    private function absoluteCurrentHostRoute(Request $request, string $name, array $parameters = []): string
    {
        return rtrim($request->getSchemeAndHttpHost(), '/').route($name, $parameters, false);
    }

    private function connectStateCacheKey(string $state): string
    {
        return 'simple_l1:connect_state:'.hash('sha256', $state);
    }

    private function browserHandoffCompletionCacheKey(string $state): string
    {
        return 'simple_l1:browser_handoff_complete:'.hash('sha256', $state);
    }

    /**
     * Native apps may open the HTTPS callback in the system default browser.
     * Only the original browser tab has this session state, so it must be the
     * one that applies the completed identity to its own cookies.
     */
    private function completeBrowserBoundHandoff(Request $request, string $state): ?string
    {
        $sessionState = (string) $request->session()->get('simple_l1_connect.state', '');
        if ($sessionState === '' || ! hash_equals($sessionState, $state)) {
            return null;
        }

        $completion = Cache::get($this->browserHandoffCompletionCacheKey($state));
        if (! is_array($completion)) {
            return null;
        }

        $userId = (int) data_get($completion, 'user_id', 0);
        if ($userId > 0 && (int) Auth::id() !== $userId) {
            $user = User::find($userId);
            if ($user instanceof User) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        }

        $identity = (array) data_get($completion, 'identity', []);
        $sovereignAddress = (string) data_get($completion, 'sovereign_l1_address', data_get($identity, 'entity_l1_address', ''));
        if ($identity !== [] && $sovereignAddress !== '') {
            $this->storeSimpleL1IdentityInSession(
                $request,
                $identity,
                $sovereignAddress,
                (bool) data_get($completion, 'opens_vault', false),
            );
        }

        $request->session()->forget([
            'simple_l1_connect.state',
            'simple_l1_connect.nonce',
            'simple_l1_connect.client_id',
            'simple_l1_connect.redirect_uri',
            'simple_l1_connect.return_to',
            'simple_l1_connect.mode',
            'simple_l1_connect.flow',
            'simple_l1_connect.intent',
        ]);
        Cache::forget($this->browserHandoffCompletionCacheKey($state));

        return $this->safeReturnTo((string) data_get($completion, 'return_to', '/store'));
    }

    /**
     * @param array<string, mixed> $identity
     */
    private function storeSimpleL1IdentityInSession(Request $request, array $identity, string $sovereignAddress, bool $opensVault): void
    {
        $request->session()->put([
            'simple_l1_identity' => $identity,
            'sovereign_l1_address' => strtolower($sovereignAddress),
        ]);

        if ($opensVault) {
            $request->session()->forget('cabinet_vault_manually_locked');
            $request->session()->put('cabinet_vault_unlocked_until', now()->addMinutes(15)->timestamp);
        }
    }

    private function cleanIntentParam(mixed $value, int $limit): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/[<>\x00-\x1F\x7F]+/u', ' ', $value) ?: '';
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return Str::limit(trim($value), $limit, '');
    }

    private function simpleL1IdentityHint(Request $request): ?string
    {
        $sessionAddress = (string) (
            data_get($request->session()->get('simple_l1_identity'), 'entity_l1_address')
            ?: data_get($request->session()->get('simple_l1_identity'), 'l1_address')
            ?: ''
        );
        if (preg_match('/^sl1e_[a-f0-9]{39}$/i', $sessionAddress) === 1) {
            return strtolower($sessionAddress);
        }

        $user = $request->user();
        if ($user instanceof User && $user->sovereignIdentityAddress()) {
            return strtolower((string) $user->sovereignIdentityAddress());
        }

        return null;
    }

    private function simpleL1Locale(Request $request): string
    {
        $locale = trim((string) ($request->query('ui_locale') ?: app()->getLocale()));
        $locale = str_replace('_', '-', strtolower($locale));

        return str_starts_with($locale, 'ru') ? 'ru' : 'en';
    }

    /**
     * Native app proof is the primary path for connect flows, but an unenrolled
     * or host-ineligible app key should degrade to online passkey instead of a
     * terminal 403 page.
     *
     * @param array<string, mixed> $connectContext
     */
    private function shouldReturnNativeDirectProofToMarketplace(HttpExceptionInterface $exception, array $connectContext): bool
    {
        if ($exception->getStatusCode() !== 403 || data_get($connectContext, 'flow') !== 'connect') {
            return false;
        }

        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'not enrolled for this entity')
            || str_contains($message, 'not eligible for this relying party')
            || str_contains($message, 'entity bootstrap mismatch');
    }

    /**
     * @param array<string, mixed> $intent
     * @return array{key:string,title:string,body:string,facts:array<int, string>,cta:string}
     */
    private function simpleL1HandoffContext(string $mode, array $intent, string $returnTo): array
    {
        $intentType = strtolower((string) data_get($intent, 'intent_type', ''));

        if ($intentType === 'meanly.vault.open' || str_starts_with($returnTo, '/vault') || str_starts_with($returnTo, '/cabinet')) {
            return [
                'key' => 'vault_open',
                'title' => __('auth.simple_l1.vault_open.title'),
                'body' => __('auth.simple_l1.vault_open.body'),
                'facts' => [
                    __('auth.simple_l1.vault_open.facts.owner_only'),
                    __('auth.simple_l1.vault_open.facts.no_keys'),
                ],
                'cta' => __('auth.simple_l1.vault_open.cta'),
            ];
        }

        if (str_contains($intentType, 'pay') || str_contains($intentType, 'checkout') || str_contains($intentType, 'wallet')) {
            return [
                'key' => 'wallet_pay',
                'title' => __('auth.simple_l1.wallet_pay.title'),
                'body' => __('auth.simple_l1.wallet_pay.body'),
                'facts' => [
                    __('auth.simple_l1.wallet_pay.facts.wallet_stays_private'),
                    __('auth.simple_l1.wallet_pay.facts.result_only'),
                ],
                'cta' => __('auth.simple_l1.wallet_pay.cta'),
            ];
        }

        if ($mode === 'register') {
            return [
                'key' => 'identity_create',
                'title' => __('auth.simple_l1.identity_create.title'),
                'body' => __('auth.simple_l1.identity_create.body'),
                'facts' => [
                    __('auth.simple_l1.identity_create.facts.passkey_device'),
                    __('auth.simple_l1.identity_create.facts.return_after'),
                ],
                'cta' => __('auth.simple_l1.identity_create.cta'),
            ];
        }

        return [
            'key' => 'identity_confirm',
            'title' => __('auth.simple_l1.identity_confirm.title'),
            'body' => __('auth.simple_l1.identity_confirm.body'),
            'facts' => [
                __('auth.simple_l1.identity_confirm.facts.no_password'),
                __('auth.simple_l1.identity_confirm.facts.passkey_device'),
            ],
            'cta' => __('auth.simple_l1.identity_confirm.cta'),
        ];
    }

    private function shouldShowSimpleL1Handoff(Request $request, string $key): bool
    {
        if ((bool) $request->session()->get('simple_l1_handoff_seen.'.$key, false)) {
            return false;
        }

        $user = $request->user();
        if ($user instanceof User) {
            if (data_get($user->meta, 'simple_l1.handoff_seen.'.$key)) {
                return false;
            }
        }

        return true;
    }

    private function markSimpleL1HandoffSeen(Request $request, string $key, ?User $user = null): void
    {
        $seenAt = now()->toIso8601String();
        $request->session()->put('simple_l1_handoff_seen.'.$key, $seenAt);

        $user ??= $request->user();
        if (! $user instanceof User || data_get($user->meta, 'simple_l1.handoff_seen.'.$key)) {
            return;
        }

        $meta = $user->meta ?? [];
        $meta['simple_l1'] = $meta['simple_l1'] ?? [];
        $meta['simple_l1']['handoff_seen'] = array_merge($meta['simple_l1']['handoff_seen'] ?? [], [
            $key => $seenAt,
        ]);
        $user->meta = $meta;
        $user->save();
    }

    private function assertProofMatchesConnectSession(
        array $proof,
        string $expectedClientId,
        string $expectedRedirectUri,
        string $expectedState,
        string $expectedNonce,
        string $expectedMode,
    ): void {
        abort_unless(hash_equals($expectedClientId, (string) data_get($proof, 'clientId')), 403, 'Simple L1 proof client mismatch.');
        abort_unless(hash_equals($expectedRedirectUri, (string) data_get($proof, 'redirectUri')), 403, 'Simple L1 proof redirect mismatch.');
        abort_unless(hash_equals($expectedState, (string) data_get($proof, 'state')), 403, 'Simple L1 proof state mismatch.');
        abort_unless($expectedNonce !== '' && hash_equals($expectedNonce, (string) data_get($proof, 'nonce')), 403, 'Simple L1 proof nonce mismatch.');
        abort_unless(hash_equals($expectedMode, (string) data_get($proof, 'mode', 'login')), 403, 'Simple L1 proof mode mismatch.');
        abort_unless(in_array((string) data_get($proof, 'type'), ['sl1e.login.proof.v1', 'sl1e.register.proof.v1', 'sl1e.intent.proof.v1'], true), 403, 'Simple L1 proof type mismatch.');

        $entityAddress = (string) data_get($proof, 'entityAddress');
        $keyAddress = (string) data_get($proof, 'keyAddress');
        abort_unless(preg_match('/^sl1e_[a-f0-9]{39}$/i', $entityAddress) === 1, 422, 'Simple L1 entity address is malformed.');
        abort_unless($keyAddress === '' || preg_match('/^sl1_[a-f0-9]{40}$/i', $keyAddress) === 1, 422, 'Simple L1 key address is malformed.');

        $expiresAt = data_get($proof, 'expiresAt');
        $expiresAtTimestamp = is_string($expiresAt) ? strtotime($expiresAt) : false;
        abort_unless($expiresAtTimestamp !== false && $expiresAtTimestamp >= now()->getTimestamp(), 403, 'Simple L1 proof expired.');

        $issuedAt = data_get($proof, 'issuedAt');
        if (is_string($issuedAt) && ($issuedAtTimestamp = strtotime($issuedAt)) !== false) {
            abort_unless($issuedAtTimestamp >= now()->subMinutes(10)->getTimestamp(), 403, 'Simple L1 proof is stale.');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function simpleL1DirectProofResponse(mixed $value, string $proofToken = ''): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value) ? $value : $this->decodeSimpleL1ProofPayload((string) $value);
        if (! is_array($decoded)) {
            return null;
        }

        $proof = data_get($decoded, 'proof');
        if (! is_array($proof) && isset($decoded['type'])) {
            $proof = $decoded;
        }

        if (! is_array($proof)) {
            return null;
        }

        $proofJson = json_encode($proof, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        $proofToken = trim((string) data_get($decoded, 'proof_token', $proofToken));

        return [
            'protocol' => data_get($decoded, 'protocol', 'simple-l1'),
            'active' => (bool) data_get($decoded, 'active', true),
            'proof_token' => $proofToken !== '' ? $proofToken : 'native:'.hash('sha256', $proofJson),
            'proof' => $proof,
            'identity' => is_array(data_get($decoded, 'identity')) ? data_get($decoded, 'identity') : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeSimpleL1ProofPayload(string $value): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $normalized = strtr($value, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);
        $json = base64_decode($normalized, true);
        if (! is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $proofResponse
     */
    private function assertNativeDirectProofSignature(array $proofResponse): void
    {
        $proof = data_get($proofResponse, 'proof');
        abort_unless(is_array($proof), 422, 'Simple L1 native proof is missing.');

        $publicKey = (string) data_get($proof, 'keyPublicKey', '');
        $signature = (string) data_get($proof, 'signature', '');
        $algorithm = (string) data_get($proof, 'signatureAlgorithm', '');
        abort_unless($publicKey !== '' && $signature !== '', 422, 'Simple L1 native proof signature is missing.');
        abort_unless($algorithm === 'p256-sha256-der', 422, 'Simple L1 native proof signature algorithm is unsupported.');

        $expectedKeyAddress = app(L1IdentityService::class)->keyAddressFromPublicKey($publicKey);
        abort_unless(hash_equals($expectedKeyAddress, strtolower((string) data_get($proof, 'keyAddress', ''))), 403, 'Simple L1 native proof key mismatch.');

        $rawPublicKey = $this->decodeNativePublicKey($publicKey);
        $rawSignature = $this->base64UrlDecode($signature);
        abort_unless($rawPublicKey !== null && $rawSignature !== null, 422, 'Simple L1 native proof key material is malformed.');

        $payload = $this->nativeProofSigningPayload($proof);
        $providedPayload = (string) data_get($proof, 'signaturePayload', '');
        if ($providedPayload !== '') {
            abort_unless(hash_equals($payload, $providedPayload), 403, 'Simple L1 native proof payload mismatch.');
        }

        $pem = $this->p256X963PublicKeyToPem($rawPublicKey);
        $verified = @openssl_verify($payload, $rawSignature, $pem, OPENSSL_ALGO_SHA256);
        abort_unless($verified === 1, 403, 'Simple L1 native proof signature mismatch.');
    }

    /**
     * @param array<string, mixed> $proofResponse
     */
    private function recordRejectedNativeVerificationResult(array $proofResponse, string $message): void
    {
        $decision = $this->nativeDirectProofRejectionDecision($message);
        $verificationResult = app(SimpleL1VerificationResultService::class)->rejected(
            proofResponse: $proofResponse,
            decision: $decision,
            checks: $this->nativeDirectProofRejectionSteps($decision),
        );

        Log::warning('simple_l1.native_direct_proof_rejected', [
            'verification_result' => $verificationResult,
        ]);
    }

    private function nativeDirectProofRejectionDecision(string $message): string
    {
        $message = Str::lower($message);

        return match (true) {
            str_contains($message, 'signature'),
            str_contains($message, 'payload mismatch'),
            str_contains($message, 'key material') => 'SIGNATURE_REJECTED',
            str_contains($message, 'nonce') => 'NONCE_REPLAY',
            str_contains($message, 'expired'),
            str_contains($message, 'stale') => 'PROOF_EXPIRED',
            str_contains($message, 'request host'),
            str_contains($message, 'relying party') => 'HOST_REJECTED',
            str_contains($message, 'client') => 'CLIENT_REJECTED',
            str_contains($message, 'key'),
            str_contains($message, 'entity') => 'KEY_BINDING_REJECTED',
            default => 'POLICY_REJECTED',
        };
    }

    /**
     * @return array<string, string>
     */
    private function nativeDirectProofRejectionSteps(string $decision): array
    {
        return match ($decision) {
            'SIGNATURE_REJECTED' => ['signature' => 'failed'],
            'KEY_BINDING_REJECTED' => [
                'signature' => 'passed',
                'key_binding' => 'failed',
            ],
            'HOST_REJECTED' => [
                'signature' => 'passed',
                'key_binding' => 'passed',
                'host_policy' => 'failed',
            ],
            'CLIENT_REJECTED' => [
                'signature' => 'passed',
                'key_binding' => 'passed',
                'client_policy' => 'failed',
            ],
            'NONCE_REPLAY' => [
                'signature' => 'passed',
                'key_binding' => 'passed',
                'host_policy' => 'passed',
                'client_policy' => 'passed',
                'nonce' => 'failed',
            ],
            'PROOF_EXPIRED' => [
                'signature' => 'passed',
                'key_binding' => 'passed',
                'host_policy' => 'passed',
                'client_policy' => 'passed',
                'nonce' => 'passed',
                'expiration' => 'failed',
            ],
            default => [
                'signature' => 'passed',
                'key_binding' => 'passed',
                'host_policy' => 'failed',
                'client_policy' => 'failed',
            ],
        };
    }

    /**
     * @param array<string, mixed> $proof
     */
    private function nativeProofSigningPayload(array $proof): string
    {
        return implode("\n", [
            (string) data_get($proof, 'type', ''),
            (string) data_get($proof, 'routingDecisionId', ''),
            (string) data_get($proof, 'policyVersion', ''),
            (string) data_get($proof, 'clientId', ''),
            strtolower((string) data_get($proof, 'requestHost', '')),
            (string) data_get($proof, 'redirectUri', ''),
            (string) data_get($proof, 'state', ''),
            (string) data_get($proof, 'nonce', ''),
            (string) data_get($proof, 'mode', ''),
            strtolower((string) data_get($proof, 'entityAddress', '')),
            strtolower((string) data_get($proof, 'keyAddress', '')),
            (string) data_get($proof, 'issuedAt', ''),
            (string) data_get($proof, 'expiresAt', ''),
            (string) data_get($proof, 'intent.type', ''),
            (string) data_get($proof, 'intent.nonce', ''),
            (string) data_get($proof, 'intent.resource', ''),
        ]);
    }

    private function decodeNativePublicKey(string $publicKey): ?string
    {
        $publicKey = trim($publicKey);
        if (! str_starts_with($publicKey, 'base64url:')) {
            return null;
        }

        $raw = $this->base64UrlDecode(substr($publicKey, strlen('base64url:')));
        if ($raw === null || strlen($raw) !== 65 || $raw[0] !== "\x04") {
            return null;
        }

        return $raw;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = strtr($value, '-_', '+/');
        $normalized .= str_repeat('=', (4 - strlen($normalized) % 4) % 4);
        $decoded = base64_decode($normalized, true);

        return is_string($decoded) ? $decoded : null;
    }

    private function p256X963PublicKeyToPem(string $publicKey): string
    {
        $algorithm = $this->derSequence(
            $this->derOid(hex2bin('2A8648CE3D0201') ?: '')
            .$this->derOid(hex2bin('2A8648CE3D030107') ?: '')
        );
        $subjectPublicKey = $this->derBitString($publicKey);
        $spki = $this->derSequence($algorithm.$subjectPublicKey);

        return "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode($spki), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
    }

    private function derSequence(string $value): string
    {
        return "\x30".$this->derLength(strlen($value)).$value;
    }

    private function derOid(string $value): string
    {
        return "\x06".$this->derLength(strlen($value)).$value;
    }

    private function derBitString(string $value): string
    {
        return "\x03".$this->derLength(strlen($value) + 1)."\x00".$value;
    }

    private function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff).$bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function proofTokenForSessionIdentity(array $identity): ?string
    {
        $handle = (string) ($identity['proof_handle'] ?? '');
        if ($handle === '') {
            return null;
        }

        return Cache::get($this->proofTokenCacheKey($handle));
    }

    private function proofTokenCacheKey(string $handle): string
    {
        return 'simple_l1:proof_token:'.$handle;
    }

    private function resolveOrCreateUserFromProof(array $proofResponse, string $entityAddress, ?string $keyAddress): ?User
    {
        $profileName = $this->simpleL1ProfileName($proofResponse, $entityAddress);
        $usernameCandidate = $this->simpleL1UsernameCandidate($proofResponse, $entityAddress, $profileName);
        $current = Auth::user();
        if ($current instanceof User) {
            $existing = User::findByEntityL1Address($entityAddress);
            abort_if($existing instanceof User && $existing->id !== $current->id, 409, 'Simple L1 identity is already connected to another account.');

            $currentAddress = $current->sovereignIdentityAddress();
            abort_if($currentAddress !== null && ! hash_equals(strtolower($currentAddress), $entityAddress), 409, 'Current account is already connected to another Simple L1 identity.');

            $this->attachIdentityToUser($current, $proofResponse, $entityAddress, $keyAddress, $profileName, $usernameCandidate);

            return $current->refresh();
        }

        $user = User::findByEntityL1Address($entityAddress);

        if (! $user) {
            $suffix = strtoupper(substr($entityAddress, -6));
            try {
                $username = User::makeUniqueUsername($usernameCandidate);
                $user = User::create([
                    'first_name' => $profileName,
                    'last_name' => 'Wallet',
                    'username' => $username,
                    'username_key' => $username,
                    'entity_l1_address' => $entityAddress,
                    'key_l1_address' => $keyAddress,
                    'identity_provider' => 'identity_wildflow',
                    'meta' => [
                        'registration_source' => 'identity_wildflow',
                        'username' => $username,
                        'display_name' => $profileName,
                        'profile_suffix' => $suffix,
                    ],
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $user = User::findByEntityL1Address($entityAddress);
            }
        }

        abort_unless($user instanceof User, 409, 'Simple L1 identity user could not be resolved.');

        $this->attachIdentityToUser($user, $proofResponse, $entityAddress, $keyAddress, $profileName, $usernameCandidate);

        return $user->refresh();
    }

    /**
     * Connect/vault flows authenticate as the proof identity. If the browser
     * still carries an older Laravel account, do not try to rebind that account.
     *
     * @param array<string, mixed> $connectContext
     */
    private function releaseConflictingAuthenticatedAccountForConnectFlow(array $connectContext, string $entityAddress): void
    {
        if (data_get($connectContext, 'flow') !== 'connect') {
            return;
        }

        $current = Auth::user();
        if (! $current instanceof User) {
            return;
        }

        $currentAddress = $current->sovereignIdentityAddress();
        if ($currentAddress === null || hash_equals(strtolower($currentAddress), strtolower($entityAddress))) {
            return;
        }

        Auth::logout();
    }

    private function simpleL1Alias(array $proofResponse): ?string
    {
        foreach ([
            data_get($proofResponse, 'proof.alias'),
            data_get($proofResponse, 'identity.alias'),
        ] as $candidate) {
            $alias = trim((string) $candidate);
            if ($alias !== '' && (str_starts_with($alias, '@') || str_ends_with(strtolower($alias), '.sl1.one') || str_contains($alias, '@'))) {
                return $alias;
            }
        }

        return null;
    }

    private function simpleL1DisplayAlias(array $proofResponse): ?string
    {
        foreach ([
            data_get($proofResponse, 'proof.displayAlias'),
            data_get($proofResponse, 'proof.display_alias'),
            data_get($proofResponse, 'identity.display_alias'),
        ] as $candidate) {
            $alias = $this->cleanSimpleL1ProfileName($candidate);
            if ($alias !== '') {
                return Str::limit($alias, 48, '');
            }
        }

        return null;
    }

    private function simpleL1ProfileName(array $proofResponse, string $entityAddress): string
    {
        foreach ([
            data_get($proofResponse, 'proof.displayAlias'),
            data_get($proofResponse, 'proof.display_alias'),
            data_get($proofResponse, 'identity.display_alias'),
            data_get($proofResponse, 'proof.alias'),
            data_get($proofResponse, 'identity.alias'),
            data_get($proofResponse, 'proof.displayName'),
            data_get($proofResponse, 'proof.username'),
        ] as $candidate) {
            $name = $this->cleanSimpleL1ProfileName($candidate);
            if ($name !== '') {
                return Str::limit($name, 48, '');
            }
        }

        return 'SL1E '.strtoupper(substr($entityAddress, -6));
    }

    private function simpleL1UsernameCandidate(array $proofResponse, string $entityAddress, ?string $profileName = null): string
    {
        foreach ([
            data_get($proofResponse, 'proof.username'),
            data_get($proofResponse, 'identity.username'),
            data_get($proofResponse, 'proof.alias'),
            data_get($proofResponse, 'identity.alias'),
            data_get($proofResponse, 'proof.displayAlias'),
            data_get($proofResponse, 'proof.display_alias'),
            data_get($proofResponse, 'identity.display_alias'),
            $profileName,
        ] as $candidate) {
            $username = User::normalizeUsername($candidate);
            if ($username !== null) {
                return $username;
            }
        }

        return 'user_'.strtolower(substr($entityAddress, -8));
    }

    private function cleanSimpleL1ProfileName(mixed $value): string
    {
        $name = trim((string) $value);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/^@/i', '', $name) ?: $name;
        $name = preg_replace('/\.sl1\.one$/i', '', $name) ?: $name;
        $name = preg_replace('/@(simplelayer\.one|sl1)$/i', '', $name) ?: $name;
        if (str_contains($name, '@')) {
            $name = explode('@', $name, 2)[0];
        }

        $name = preg_replace('/[^\pL\pN._ -]+/u', ' ', $name) ?: '';
        $name = preg_replace('/\s+/u', ' ', $name) ?: '';

        return trim($name);
    }

    private function shouldAdoptSimpleL1ProfileName(User $user): bool
    {
        $firstName = trim((string) $user->first_name);
        $displayName = trim((string) data_get($user->meta, 'display_name', ''));

        return $firstName === ''
            || ($user->identity_provider === 'identity_wildflow'
                && ($firstName === 'SL1E' || str_starts_with($displayName, 'SL1E ')));
    }

    private function attachIdentityToUser(User $user, array $proofResponse, string $entityAddress, ?string $keyAddress, ?string $profileName = null, ?string $usernameCandidate = null): void
    {
        $meta = $user->meta ?? [];
        $alias = $this->simpleL1Alias($proofResponse);
        $username = $user->username ?: User::makeUniqueUsername($usernameCandidate, $user->id);
        $meta['l1_address'] = $entityAddress;
        $meta['entity_l1_address'] = $entityAddress;
        $meta['key_l1_address'] = $keyAddress;
        if ($username !== null) {
            $meta['username'] = $username;
        }
        $meta['simple_l1'] = array_merge($meta['simple_l1'] ?? [], [
            'protocol' => 'simple-l1',
            'address_version' => \App\Services\L1IdentityService::ADDRESS_VERSION_ENTITY_V1,
            'key_address_version' => \App\Services\L1IdentityService::ADDRESS_VERSION_PASSKEY_V1,
            'identity_rule' => 'external_identity_provider',
            'identity_provider' => config('simple_l1.identity_provider_url'),
            'last_connected_at' => now()->toIso8601String(),
        ]);
        if ($alias !== null) {
            $meta['simple_l1']['alias'] = $alias;
        }
        $displayAlias = $this->simpleL1DisplayAlias($proofResponse);
        if ($displayAlias !== null) {
            $meta['simple_l1']['display_alias'] = $displayAlias;
        }
        if ($username !== null) {
            $meta['simple_l1']['username'] = $username;
        }
        $meta['identity_wildflow'] = [
            'protocol' => data_get($proofResponse, 'proof.protocol', 'simple-layer-one'),
            'entity_type' => data_get($proofResponse, 'proof.entityType'),
            'entity_address_version' => data_get($proofResponse, 'proof.entityAddressVersion'),
            'key_type' => data_get($proofResponse, 'proof.keyType'),
            'key_address_version' => data_get($proofResponse, 'proof.keyAddressVersion'),
            'proof_type' => data_get($proofResponse, 'proof.type'),
            'proof_issued_at' => data_get($proofResponse, 'proof.issuedAt'),
        ];

        if ($profileName !== null && $profileName !== '') {
            $meta['display_name'] = $profileName;
            $meta['simple_l1']['display_name'] = $profileName;
        }

        $updates = [
            'entity_l1_address' => $entityAddress,
            'key_l1_address' => $keyAddress,
            'identity_provider' => 'identity_wildflow',
            'meta' => $meta,
        ];

        if ($username !== null) {
            $updates['username'] = $username;
            $updates['username_key'] = $username;
        }

        if ($profileName !== null && $profileName !== '' && $this->shouldAdoptSimpleL1ProfileName($user)) {
            $updates['first_name'] = $profileName;
        }

        $user->forceFill($updates)->save();
    }
}
