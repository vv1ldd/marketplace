<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\LegalEntity;
use App\Services\L1IdentityService;
use App\Services\LegalEntityMigrationPillService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;

class LegalEntityMigrationPillController extends Controller
{
    public function __construct(private readonly LegalEntityMigrationPillService $pills)
    {
    }

    public function issue(Request $request, LegalEntity $legalEntity)
    {
        $actor = $request->user();
        abort_unless($actor && method_exists($actor, 'hasAnyRole') && $actor->hasAnyRole(['super_admin', 'manager', 'support']), 403);

        $data = $request->validate([
            'target_domain' => ['nullable', 'string', 'max:255'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $targetDomain = $data['target_domain'] ?? config('app.production_domain', config('app.domain'));

        [$pill, $token] = $this->pills->issueForOwner(
            legalEntity: $legalEntity,
            targetDomain: $targetDomain,
            issuedBy: $actor,
            issuedIp: $request->ip(),
            expiresAt: now()->addDays((int) ($data['expires_days'] ?? 7)),
        );

        return response()->json([
            'success' => true,
            'migration_url' => $this->pills->migrationUrl($token, $pill->target_domain),
            'expires_at' => $pill->expires_at?->toIso8601String(),
        ]);
    }

    public function show(Request $request, string $token)
    {
        $pill = $this->pills->findConsumableByToken($token);

        return view('auth.migration-pill', [
            'token' => $token,
            'pill' => $pill,
            'legalEntity' => $pill?->legalEntity,
            'error' => $pill ? null : 'Ссылка миграции недействительна, истекла или уже использована.',
        ]);
    }

    public function options(Request $request, string $token)
    {
        $pill = $this->pills->findConsumableByToken($token);

        if (! $pill) {
            return response()->json(['error' => 'Недействительная или использованная таблетка миграции.'], 422);
        }

        if (! $this->isExpectedDomain($request, $pill->target_domain)) {
            return response()->json(['error' => 'Откройте ссылку на домене, для которого она была выпущена.'], 422);
        }

        if ($request->filled('email')) {
            $request->validate([
                'email' => ['required', 'email', 'max:255'],
                'first_name' => ['nullable', 'string', 'max:255'],
            ]);

            $email = trim(strtolower($request->input('email')));
            $firstName = trim($request->input('first_name')) ?: explode('@', $email)[0];

            $user = $pill->user;
            
            $existingUser = \App\Models\User::where('email', $email)->where('id', '!=', $user->id)->first();
            if ($existingUser) {
                return response()->json(['error' => 'Пользователь с таким Email уже существует.'], 422);
            }

            $user->email = $email;
            $user->first_name = $firstName;
            $user->save();

            $seller = $pill->legalEntity?->seller;
            if ($seller) {
                $seller->email = $email;
                $seller->first_name = $firstName;
                $seller->save();
            }

            $legalEntity = $pill->legalEntity;
            if ($legalEntity) {
                $legalEntity->email = $email;
                $legalEntity->save();
            }
        }

        $json = app(GeneratePasskeyRegisterOptionsAction::class)->execute($pill->user, true);
        $options = json_decode($json, true);
        $options['rp']['id'] = $request->getHost();

        session([
            'migration_pill_token_hash' => $this->pills->hashToken($token),
            'migration_pill_passkey_options' => json_encode($options),
        ]);

        return response()->json([
            'options' => $options,
            'new_csrf' => csrf_token(),
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $request->validate([
            'passkey_attestation' => ['required'],
        ]);

        $pill = $this->pills->findConsumableByToken($token);

        if (! $pill) {
            return response()->json(['error' => 'Недействительная или использованная таблетка миграции.'], 422);
        }

        if (session('migration_pill_token_hash') !== $this->pills->hashToken($token)) {
            return response()->json(['error' => 'Сессия регистрации истекла. Откройте ссылку заново.'], 422);
        }

        $optionsJson = session('migration_pill_passkey_options');
        if (! $optionsJson) {
            return response()->json(['error' => 'Параметры Passkey не найдены. Начните регистрацию заново.'], 422);
        }

        try {
            $passkey = app(StorePasskeyAction::class)->execute(
                $pill->user,
                is_string($request->input('passkey_attestation'))
                    ? $request->input('passkey_attestation')
                    : json_encode($request->input('passkey_attestation')),
                $optionsJson,
                $request->getHost(),
                ['name' => 'Production Migration Key']
            );

            $l1Address = app(L1IdentityService::class)->bindUserToPasskey($pill->user, $passkey);

            $legalEntity = $pill->legalEntity;
            if ($legalEntity) {
                $legalEntity->update([
                    'status' => 'active',
                    'is_active' => true,
                    'agreement_signed_at' => now(),
                    'agreement_signature' => is_string($request->input('passkey_attestation'))
                        ? $request->input('passkey_attestation')
                        : json_encode($request->input('passkey_attestation')),
                    'agreement_metadata' => [
                        'signer_role' => 'ceo',
                        'signer_name' => $pill->user->first_name ?: $pill->user->email,
                        'l1_address' => $l1Address,
                        'signed_at' => now()->toIso8601String(),
                        'signature_type' => 'passkey_attestation_v1',
                        'passkey_id' => $passkey->id,
                    ],
                ]);
            }

            $this->pills->consume($token, $passkey->id, $request->ip());

            Auth::guard('web')->login($pill->user);

            $seller = $legalEntity?->seller;
            if ($seller) {
                Auth::guard('sellers')->login($seller);
            }

            $request->session()->regenerate();
            $request->session()->put('active_legal_entity_id', $pill->legal_entity_id);
            $request->session()->forget(['migration_pill_token_hash', 'migration_pill_passkey_options']);

            $redirect = (string) data_get($pill->metadata, 'redirect_url', route('partner.dashboard'));

            return response()->json([
                'success' => true,
                'l1_address' => $l1Address,
                'redirect' => $redirect,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function isExpectedDomain(Request $request, ?string $targetDomain): bool
    {
        if (! $targetDomain) {
            return true;
        }

        $targetHost = parse_url(str_contains($targetDomain, '://') ? $targetDomain : 'https://'.$targetDomain, PHP_URL_HOST);

        return strtolower((string) $targetHost) === strtolower($request->getHost());
    }
}
