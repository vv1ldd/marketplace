<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IntentLedgerService;
use App\Services\SimpleL1ProtocolClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleLayer\Sl1e\Exception\Sl1eValidationException;

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

        $authorizeUrl = app(SimpleL1ProtocolClient::class)->authorizationUrl(
            redirectUri: $redirectUri,
            state: $state,
            nonce: $nonce,
            mode: $mode,
            intent: $intent,
            flow: $flow,
            identityHint: $this->simpleL1IdentityHint($request),
            uiLocale: $this->simpleL1Locale($request),
        );
        $handoff = $this->simpleL1HandoffContext($mode, $intent, $returnTo);

        if (! $this->shouldShowSimpleL1Handoff($request, $handoff['key'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'show_handoff' => false,
                    'redirect_url' => $authorizeUrl,
                ]);
            }

            return redirect()->away($authorizeUrl);
        }

        $this->markSimpleL1HandoffSeen($request, $handoff['key']);

        if ($request->expectsJson()) {
            return response()->json([
                'show_handoff' => true,
                'redirect_url' => $authorizeUrl,
                'handoff' => $handoff,
            ]);
        }

        return view('auth.simple-l1-handoff', [
            'authorizeUrl' => $authorizeUrl,
            'mode' => $mode,
            'intentTitle' => $intent['intent_title'] ?: null,
            'returnTo' => $returnTo,
            'handoff' => $handoff,
        ]);
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = (string) session('simple_l1_connect.state');
        $expectedNonce = (string) session('simple_l1_connect.nonce');
        $expectedClientId = (string) session('simple_l1_connect.client_id', config('simple_l1.client_id'));
        $expectedRedirectUri = (string) session(
            'simple_l1_connect.redirect_uri',
            $this->absoluteCurrentHostRoute($request, 'meanly.simple_l1.callback')
        );
        $returnTo = $this->safeReturnTo((string) session('simple_l1_connect.return_to', '/store'));
        $mode = (string) session('simple_l1_connect.mode', 'login');

        abort_unless($expectedState !== '' && hash_equals($expectedState, (string) $request->input('state')), 403);

        $code = trim((string) $request->input('code', ''));
        $proofToken = trim((string) $request->input('proof_token', ''));
        abort_if($code === '' && $proofToken === '', 422, 'Simple L1 authorization code is missing.');

        if ($code !== '') {
            $proofResponse = app(SimpleL1ProtocolClient::class)->exchangeAuthorizationCode($code, $expectedClientId, $expectedRedirectUri);
            $proofToken = (string) data_get($proofResponse, 'proof_token', '');
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
            $status = str_contains($exception->getMessage(), 'malformed') || str_contains($exception->getMessage(), 'missing') ? 422 : 403;
            abort($status, $exception->getMessage());
        }

        $l1Address = $identityProof->entityAddress;
        $keyAddress = $identityProof->keyAddress;
        $alias = $this->simpleL1Alias($proofResponse);
        $displayAlias = $this->simpleL1DisplayAlias($proofResponse);
        $user = $this->resolveOrCreateUserFromProof($proofResponse, $l1Address, $keyAddress);

        if ($user && ! Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $proofHandle = Str::random(48);
        Cache::put($this->proofTokenCacheKey($proofHandle), $proofToken, now()->addMinutes(10));
        $connectIntent = session('simple_l1_connect.intent', []);
        $opensVault = data_get($connectIntent, 'intent_type') === 'meanly.vault.open'
            || str_starts_with($returnTo, '/vault')
            || str_starts_with($returnTo, '/cabinet');
        $handoff = $this->simpleL1HandoffContext($mode, $connectIntent, $returnTo);

        session([
            'simple_l1_identity' => [
                'l1_address' => strtolower($l1Address),
                'entity_l1_address' => strtolower($l1Address),
                'key_l1_address' => $keyAddress,
                'alias' => $alias,
                'display_alias' => $displayAlias,
                'proof_token_hash' => $identityProof->proofTokenHash,
                'proof_handle' => $proofHandle,
                'proof' => $identityProof->proof,
                'mode' => $identityProof->mode,
                'protocol' => 'simple-l1',
                'connected_at' => now()->toIso8601String(),
            ],
            'sovereign_l1_address' => strtolower($l1Address),
        ]);
        if ($opensVault) {
            session()->forget('cabinet_vault_manually_locked');
            session()->put('cabinet_vault_unlocked_until', now()->addMinutes(15)->timestamp);
        }
        $this->markSimpleL1HandoffSeen($request, $handoff['key'], $user);
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

        app(IntentLedgerService::class)->record(
            eventType: 'IDENTITY_CONNECT_EXTERNAL_INTENT',
            intentType: 'identity.connect_external',
            entity: $user ?: $request->user(),
            payload: [
                'connected_entity_l1_address' => strtolower($l1Address),
                'connected_key_l1_address' => $keyAddress,
                'alias' => $alias,
                'display_alias' => $displayAlias,
                'proof_token_hash' => $identityProof->proofTokenHash,
                'return_to_hash' => hash('sha256', $returnTo),
                'mode' => $identityProof->mode,
                'connected_at' => now()->toIso8601String(),
            ],
            request: $request,
            user: $user ?: $request->user(),
            scope: 'identity.external',
            resource: 'simple-l1-wallet',
        );

        return redirect($returnTo)->with('status', 'Simple L1 wallet connected.');
    }

    public function status(): array
    {
        $identity = session('simple_l1_identity');

        return [
            'protocol' => 'simple-l1',
            'authenticated' => is_array($identity) && ! empty($identity['l1_address']),
            'identity' => is_array($identity) ? [
                'l1_address' => $identity['l1_address'] ?? null,
                'entity_l1_address' => $identity['entity_l1_address'] ?? null,
                'key_l1_address' => $identity['key_l1_address'] ?? null,
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

        if ($returnTo === '' || ! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            return '/store';
        }

        return $returnTo;
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
        $current = Auth::user();
        if ($current instanceof User) {
            $existing = User::findByEntityL1Address($entityAddress);
            abort_if($existing instanceof User && $existing->id !== $current->id, 409, 'Simple L1 identity is already connected to another account.');

            $currentAddress = $current->sovereignIdentityAddress();
            abort_if($currentAddress !== null && ! hash_equals(strtolower($currentAddress), $entityAddress), 409, 'Current account is already connected to another Simple L1 identity.');

            $this->attachIdentityToUser($current, $proofResponse, $entityAddress, $keyAddress, $profileName);

            return $current->refresh();
        }

        $user = User::findByEntityL1Address($entityAddress);

        if (! $user) {
            $suffix = strtoupper(substr($entityAddress, -6));
            try {
                $user = User::create([
                    'first_name' => $profileName,
                    'last_name' => 'Wallet',
                    'entity_l1_address' => $entityAddress,
                    'key_l1_address' => $keyAddress,
                    'identity_provider' => 'identity_wildflow',
                    'meta' => [
                        'registration_source' => 'identity_wildflow',
                        'display_name' => $profileName,
                        'profile_suffix' => $suffix,
                    ],
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                $user = User::findByEntityL1Address($entityAddress);
            }
        }

        abort_unless($user instanceof User, 409, 'Simple L1 identity user could not be resolved.');

        $this->attachIdentityToUser($user, $proofResponse, $entityAddress, $keyAddress, $profileName);

        return $user->refresh();
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

    private function attachIdentityToUser(User $user, array $proofResponse, string $entityAddress, ?string $keyAddress, ?string $profileName = null): void
    {
        $meta = $user->meta ?? [];
        $alias = $this->simpleL1Alias($proofResponse);
        $meta['l1_address'] = $entityAddress;
        $meta['entity_l1_address'] = $entityAddress;
        $meta['key_l1_address'] = $keyAddress;
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

        if ($profileName !== null && $profileName !== '' && $this->shouldAdoptSimpleL1ProfileName($user)) {
            $updates['first_name'] = $profileName;
        }

        $user->forceFill($updates)->save();
    }
}
