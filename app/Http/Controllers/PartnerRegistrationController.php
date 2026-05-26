<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

class PartnerRegistrationController extends Controller
{
    public function show(Request $request)
    {
        return $this->showRegistration($request, 'legal_entity');
    }

    public function showLegalEntity(Request $request)
    {
        return $this->showRegistration($request, 'legal_entity');
    }

    private function showRegistration(Request $request, string $registrationTarget)
    {
        $brand = null;
        if ($request->has('brand_id')) {
            $brand = \App\Models\Brand::find($request->brand_id);
        }

        session(['registration_target' => $registrationTarget]);

        // 🌍 Smart GeoIP Detection
        $ip = $request->ip();
        $detectedCountry = 'RU';
        $detectedCountryName = 'Россия';
        
        try {
            $geoData = @json_decode(file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode,country"), true);
            if (isset($geoData['countryCode'])) {
                $detectedCountry = $geoData['countryCode'];
                $detectedCountryName = $geoData['country'] ?? 'Неизвестно';
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // Fetch B2B Agreement
        $agreement = \App\Models\Agreement::where('type', 'b2b')->where('is_active', true)->latest('published_at')->first();
        $agreementText = $agreement ? $agreement->content : "Текст оферты не найден.";

        // Resolve Invite Intent
        $inviteIntent = null;
        if ($request->filled('invite')) {
            $inviteIntent = \Illuminate\Support\Facades\Cache::get("intent:{$request->invite}");
            if ($inviteIntent && data_get($inviteIntent, 'type') === 'workspace_invite') {
                // Ensure B2B flag is solid
                session(['redirect_to_offer' => true]);
            } else {
                $inviteIntent = null;
            }
        }

        $user = Auth::user();
        if ($user) {
            $entity = $user->legalEntities()->first();
            if ($entity) {
                if ($entity->status === 'pending_signature') {
                    return redirect()->route('partner.register.offer');
                }
                if ($entity->status === 'pending_moderation' || ! $entity->is_active) {
                    return redirect()->route('partner.onboarding');
                }
                return redirect()->route('partner.dashboard');
            }
        }

        // If user is already logged in, generate signing (Assertion) options for instant TouchID signature!
        $signingOptions = null;
        if ($user && $user->passkeys()->exists()) {
            // Use our custom method that populates allowCredentials with the user's specific passkey IDs.
            // This prevents Safari from picking an old/wrong passkey from iCloud Keychain.
            $signingOptions = $this->generateSigningOptionsForUser($user);
            session(['signing_options' => $signingOptions]);
        }

        $businessVerifiedEmail = session('business_registration_verified_email');

        return view('partner.register', [
            'brand' => $brand,
            'registrationTarget' => $registrationTarget,
            'registrationSubmitRoute' => route('business.register.submit'),
            'registrationOptionsRoute' => route('business.register.options'),
            'businessEmailSendRoute' => route('business.register.email.send'),
            'businessEmailVerifyRoute' => route('business.register.email.verify'),
            'businessVerifiedEmail' => $businessVerifiedEmail,
            'detectedCountry' => $detectedCountry,
            'detectedCountryName' => $detectedCountryName,
            'supportedJurisdictions' => $brand && $brand->compliance_config ? array_keys($brand->compliance_config) : null,
            'complianceConfig' => $brand ? $brand->compliance_config : null,
            'agreementText' => $agreementText,
            'signingOptions' => $signingOptions,
            'inviteIntent' => $inviteIntent,
            'inviteToken' => $request->invite,
        ]);
    }

    public function sendBusinessEmailCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $code = (string) random_int(100000, 999999);

        Cache::put($this->businessEmailVerificationKey($request), [
            'email' => $email,
            'code' => $code,
        ], now()->addMinutes(20));

        session(['business_registration_email_challenge' => [
            'email' => $email,
            'code' => $code,
        ]]);
        session()->forget('business_registration_verified_email');
        Mail::to($email)->send(new VerificationCodeMail($code));

        return response()->json([
            'success' => true,
            'message' => 'Код подтверждения отправлен на email.',
        ]);
    }

    public function verifyBusinessEmailCode(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'code' => 'required|string|max:20',
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $payload = session('business_registration_email_challenge')
            ?: Cache::get($this->businessEmailVerificationKey($request));
        $localBypass = ! app()->environment('production')
            && trim((string) config('app.redeem_local_verification_code')) !== ''
            && trim((string) $validated['code']) === trim((string) config('app.redeem_local_verification_code'));

        if (! $payload || ($payload['email'] ?? null) !== $email || (! $localBypass && (string) ($payload['code'] ?? '') !== trim((string) $validated['code']))) {
            return response()->json([
                'success' => false,
                'error' => 'Неверный код подтверждения.',
            ], 422);
        }

        session(['business_registration_verified_email' => $email]);
        session()->forget('business_registration_email_challenge');

        return response()->json([
            'success' => true,
            'email' => $email,
            'message' => 'Email подтвержден. Теперь можно проверить ИНН.',
        ]);
    }

    private function businessEmailVerificationKey(Request $request): string
    {
        return 'business_registration_email:'.$request->session()->getId();
    }
 
    public function verifyIntent(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            abort(400, 'Отсутствует токен интента активации.');
        }

        $blueprint = \Illuminate\Support\Facades\Cache::get("intent:{$token}");
        if (!$blueprint) {
            return redirect('/cabinet/register')->withErrors(['email' => 'Срок действия интента активации истек. Пожалуйста, отправьте запрос повторно.']);
        }

        $email = $blueprint['email'];
        $isB2b = $blueprint['is_b2b'] ?? false;

        // Locate or instantiate the User record
        $user = User::findByEmail($email) ?? User::create([
            'first_name' => strstr($email, '@', true) ?: $email,
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
            'password_login_enabled' => false,
        ]);

        $user->assignRole('customer');

        // Log them in temporarily so that Spatie's WebAuthn can compile the registration options
        Auth::login($user);

        // Store registration session context for L1 anchoring (Step 2)
        session(['partner_registration' => [
            'email' => $user->email,
            'name' => $user->first_name,
            'is_b2b' => $isB2b,
        ]]);

        // Format raw JSON blueprint for visual rendering on the page
        $rawBlueprintJson = json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return view('partner.verify_handshake', [
            'email' => $email,
            'blueprint' => $blueprint,
            'rawBlueprintJson' => $rawBlueprintJson,
        ]);
    }
 
    public function showEnroll(Request $request)
    {
        if ($request->has('token')) {
            $userId = \Illuminate\Support\Facades\Cache::get('enroll_token_' . $request->token);
            if ($userId) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    Auth::login($user);
                    // Populate registration session on mobile
                    session(['partner_registration' => [
                        'email' => $user->email,
                        'name' => $user->first_name,
                        'is_b2b' => $user->hasRole('b2b_partner') || session('redirect_to_offer') === true,
                    ]]);
                }
            }
        }

        $user = Auth::user();
        if (!$user) return redirect()->route('partner.register');
 
        $token = \Illuminate\Support\Str::random(32);
        \Illuminate\Support\Facades\Cache::put('enroll_token_' . $token, $user->id, 600);
 
        $qrUrl = route('partner.register.enroll', ['token' => $token]);
 
        return view('partner.register_step2_enroll', [
            'qrUrl' => $qrUrl
        ]);
    }

    /**
     * AJAX: Generate options for immediate Passkey registration
     */
    public function options(Request $request)
    {
        $registrationTarget = $this->registrationTarget($request);
        session(['registration_target' => $registrationTarget]);

        $verifiedBusinessEmail = session('business_registration_verified_email');
        if ($registrationTarget === 'legal_entity') {
            $requestEmail = mb_strtolower(trim((string) $request->input('business_email', '')));

            if (! $verifiedBusinessEmail || $requestEmail !== $verifiedBusinessEmail) {
                return response()->json([
                    'error' => 'Сначала подтвердите рабочий email.',
                ], 422);
            }
        }

        $displayName = $this->normalizeDisplayName($request->input('display_name'));
        if (!$displayName) {
            return response()->json([
                'error' => 'Введите имя владельца профиля.',
            ], 422);
        }

        $user = $this->registrationUser(null, $displayName);
        
        Auth::login($user);

        $json = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction::class)->execute($user, true);
        $optionsArray = json_decode($json, true);
        $optionsArray['rp']['id'] = $request->getHost();
        
        // Save for verification in the next step
        session(['passkey_options' => json_encode($optionsArray)]);
        session(['passkey_registration_user_id' => $user->id]);

        // 🛡️ IMPORTANT: Laravel rotates the session on login, invalidating the old CSRF token.
        // We must provide the new one to the frontend.
        return response()->json([
            'options' => $optionsArray,
            'identity' => [
                'label' => $user->profileDisplayName(),
                'user_id' => $user->id,
            ],
            'new_csrf' => csrf_token()
        ]);
    }

    /**
     * STEP 1 POST: Atomic User + Entity + Identity Creation
     */
    public function register(Request $request)
    {
        $isUpgrade = Auth::check() && Auth::user()->passkeys()->exists();

        // 🪐 B2B Consortium Workspace Invite check!
        $inviteToken = $request->input('invite');
        $inviteIntent = null;
        if ($inviteToken) {
            $inviteIntent = \Illuminate\Support\Facades\Cache::get("intent:{$inviteToken}");
        }

        if ($inviteIntent && data_get($inviteIntent, 'type') === 'workspace_invite') {
            $rules = [
                'email' => 'required|email',
            ];
            if (!$isUpgrade) {
                $rules['passkey_attestation'] = 'required';
            }
            $request->validate($rules);

            $user = Auth::user();
            $optionsJson = session('passkey_options');

            return DB::transaction(function() use ($user, $request, $optionsJson, $inviteIntent, $inviteToken, $isUpgrade) {
                try {
                    if (!$isUpgrade) {
                        // 1. Store Passkey
                        $passkey = app(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class)->execute(
                            $user,
                            $request->input('passkey_attestation'),
                            $optionsJson,
                            $request->getHost(),
                            ['name' => 'Primary Sovereign Identity']
                        );

                        // 2. Anchor stable Simple L1 entity identity.
                        $identity = $this->anchorSimpleL1Identity($user, $passkey);
                        $address = $identity['entity_l1_address'];
                    }

                    $entityId = $inviteIntent['partner_id'];
                    $role = $inviteIntent['role'];

                    // Attach invited manager to the LegalEntity
                    $user->managedLegalEntities()->syncWithoutDetaching([
                        $entityId => ['role' => $role === 'admin' ? 'admin' : 'manager']
                    ]);

                    $seller = \App\Models\Seller::findByEmail($user->email);
                    if (!$seller) {
                        $seller = \App\Models\Seller::create([
                            'first_name' => $user->first_name ?: 'SL1',
                            'last_name' => $user->last_name ?: 'Partner',
                            'email' => $user->email,
                            'password' => $user->password,
                            'is_active' => true,
                        ]);
                    }

                    $this->assignB2BRoles($user, $seller);

                    $seller->managedLegalEntities()->syncWithoutDetaching([
                        $entityId => ['role' => $role, 'user_id' => $user->id]
                    ]);

                    // Clear invite intent
                    \Illuminate\Support\Facades\Cache::forget("intent:{$inviteToken}");
                    session()->forget(['partner_registration', 'passkey_options', 'signing_options']);

                    Auth::login($user);
                    Auth::guard('sellers')->login($seller);

                    return response()->json([
                        'success' => true,
                        'redirect' => route('partner.dashboard')
                    ]);
                } catch (\Exception $e) {
                    return response()->json(['error' => $e->getMessage()], 422);
                }
            });
        }
        // Phase 1: Guest is only registering their user account and passkey
        if (!$request->has('inn') && !$inviteIntent) {
            $rules = [
                'passkey_attestation' => 'required|string',
                'display_name' => 'required|string|max:80',
            ];

            $request->validate($rules);

            $user = Auth::user() ?? User::find((int) session('passkey_registration_user_id'));
            if (!$user) {
                return response()->json(['error' => 'Сессия создания Simple L1 профиля истекла. Обновите страницу и попробуйте снова.'], 422);
            }

            $user->assignRole('customer');
            $optionsJson = session('passkey_options');

            return DB::transaction(function() use ($user, $request, $optionsJson) {
                try {
                    if (!$optionsJson) {
                        throw new \Exception('Сессия Passkey истекла. Обновите страницу и попробуйте снова.');
                    }

                    // 1. Store Passkey
                    $passkey = app(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class)->execute(
                        $user,
                        $request->input('passkey_attestation'),
                        $optionsJson,
                        $request->getHost(),
                        ['name' => 'Primary Sovereign Identity']
                    );

                    // 2. Anchor stable Simple L1 entity identity independent of this device key.
                    $identity = $this->anchorSimpleL1Identity($user, $passkey);

                    Auth::login($user);
                    session()->forget(['passkey_options', 'passkey_registration_user_id']);

                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => true,
                            'identity' => $identity,
                            'redirect' => route($this->registrationRouteFor($request))
                        ]);
                    }

                    return redirect()->route($this->registrationRouteFor($request));
                } catch (\Exception $e) {
                    if ($request->expectsJson() || $request->ajax()) {
                        return response()->json(['error' => $e->getMessage()], 422);
                    }
                    return back()->withErrors(['error' => $e->getMessage()])->withInput();
                }
            });
        }

        // Phase 2: Logged-in user is registering their company
        $request->merge([
            'inn' => preg_replace('/\D+/', '', (string) $request->input('inn', '')),
        ]);
        $jurisdiction = strtoupper((string) $request->input('jurisdiction', 'RU'));
        $taxIdLengths = [
            'RU' => [10, 12],
            'KZ' => [12],
            'BY' => [9],
            'UZ' => [9, 14],
            'AM' => [8],
            'KG' => [14],
            'TM' => [8],
        ];
        $allowedTaxIdLengths = $taxIdLengths[$jurisdiction] ?? [10, 12];

        $rules = [
            'inn' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($allowedTaxIdLengths, $jurisdiction): void {
                    $value = (string) $value;
                    if (!ctype_digit($value) || !in_array(strlen($value), $allowedTaxIdLengths, true)) {
                        $lengths = implode(' или ', $allowedTaxIdLengths);
                        $fail($jurisdiction === 'RU'
                            ? "ИНН должен содержать 10 цифр для юрлица или 12 цифр для ИП/физлица."
                            : "Регистрационный номер должен содержать {$lengths} цифр.");
                    }
                },
            ],
            'signer_role' => 'nullable|string|in:ceo,representative',
            'signer_name' => 'nullable|string|max:160',
        ];

        if (!$isUpgrade) {
            $rules['passkey_attestation'] = 'required';
        }

        $request->validate($rules);

        $user = Auth::user();
        $businessEmail = mb_strtolower(trim((string) ($request->input('business_email') ?: session('business_registration_verified_email'))));
        if ($this->registrationTarget($request) === 'legal_entity' && (! $businessEmail || $businessEmail !== session('business_registration_verified_email'))) {
            return back()
                ->withErrors(['email' => 'Сначала подтвердите email компании.'])
                ->withInput();
        }

        if ($jurisdiction === 'RU') {
            $verifiedParty = app(\App\Services\DaDataService::class)->findByInn($request->input('inn'));
            $partyType = $verifiedParty['type'] ?? null;

            if (!$verifiedParty || !in_array($partyType, ['LEGAL', 'INDIVIDUAL'], true) || ($verifiedParty['inn'] ?? null) !== $request->input('inn')) {
                if ($request->input('registration_mode') === 'self_employed' && strlen((string) $request->input('inn')) === 12 && $user) {
                    $npd = app(\App\Services\NpdStatusService::class)->check($request->input('inn'));

                    if (($npd['status'] ?? false) === true) {
                        $displayName = trim(implode(' ', array_filter([
                            $user->last_name,
                            $user->first_name,
                            $user->middle_name,
                        ]))) ?: ($user->email ?? 'Самозанятый');

                        $request->merge([
                            'dadata_verified' => '1',
                            'dadata_party_type' => 'NPD',
                            'legal_name' => 'Самозанятый ' . $displayName,
                            'tax_system' => 'NPD',
                            'ogrn' => null,
                            'kpp' => null,
                            'address' => $request->input('address'),
                            'director_name' => $displayName,
                        ]);

                        $verifiedParty = ['inn' => $request->input('inn'), 'type' => 'NPD'];
                        $partyType = 'NPD';
                    }
                }

                if (($partyType ?? null) === 'NPD') {
                    // Continue into the standard legal entity onboarding path as an NPD seller.
                } elseif ($request->input('registration_mode') === 'profile' && strlen((string) $request->input('inn')) === 12 && $user) {
                    $meta = $user->meta ?? [];
                    $meta['personal_inn'] = $request->input('inn');
                    $meta['business_registration_status'] = 'individual_only';
                    $meta['business_registration_note'] = 'DaData did not find an active IP or legal entity for this tax ID.';
                    $user->meta = $meta;
                    $user->assignRole('customer');
                    $user->save();

                    return redirect()
                        ->route('cabinet.dashboard')
                        ->with('status', 'Профиль физлица активирован. Для продаж и B2B-инструментов откройте ИП или юрлицо и повторите проверку ИНН.');
                } else {
                    return back()
                        ->withErrors(['inn' => 'ИНН не подтвержден DaData или ФНС НПД. Нужна найденная компания, ИП или статус самозанятого.'])
                        ->withInput();
                }
            }

            if ($partyType !== 'NPD') {
                $verifiedPersonName = $this->personNameFromDadata($verifiedParty);
                $verifiedName = $verifiedParty['name']['short_with_opf']
                    ?? $verifiedParty['name']['full_with_opf']
                    ?? trim(implode(' ', array_filter([
                        $verifiedParty['fio']['surname'] ?? null,
                        $verifiedParty['fio']['name'] ?? null,
                        $verifiedParty['fio']['patronymic'] ?? null,
                    ])))
                    ?: null;

                $request->merge([
                    'dadata_verified' => '1',
                    'dadata_party_type' => $partyType,
                    'legal_name' => $verifiedName ?: $request->input('legal_name'),
                    'ogrn' => $verifiedParty['ogrn'] ?? $verifiedParty['ogrnip'] ?? $request->input('ogrn'),
                    'kpp' => $verifiedParty['kpp'] ?? $request->input('kpp'),
                    'address' => $verifiedParty['address']['value'] ?? $verifiedParty['address']['unrestricted_value'] ?? $request->input('address'),
                    'director_name' => $verifiedParty['management']['name'] ?? $verifiedPersonName ?? $request->input('director_name'),
                ]);
            }
        }

        $principalName = $request->input('director_name');
        $principalNameRequired = ($partyType ?? null) === 'INDIVIDUAL';
        if (($principalNameRequired || trim((string) $principalName) !== '') && ! $this->validPersonName($principalName)) {
            return back()
                ->withErrors(['director_name' => 'Не удалось подтвердить ФИО руководителя или ИП. Нужно минимум фамилия и имя без цифр.'])
                ->withInput();
        }

        if ($request->input('signer_role') === 'representative' && ! $this->validPersonName($request->input('signer_name'))) {
            return back()
                ->withErrors(['signer_name' => 'Укажите ФИО доверенного лица: минимум фамилия и имя без цифр.'])
                ->withInput();
        }

        $optionsJson = session('passkey_options');
        $reg = array_merge($request->all(), ['business_email' => $businessEmail]);
        $inn = $reg['inn'];

        // 🛡️ Check if this INN is already registered
        $bidx = app(\App\Services\VaultTransitService::class)->computeBlindIndex($inn);
        $existing = LegalEntity::where('inn_bidx', $bidx)->first();
        
        if ($existing) {
            // 🛠️ DEV OVERRIDE: Allow re-creation for test INNs
            if ($inn === '526216895584' || $inn === '325527500033290') {
                $existing->delete();
            } else {
                if ($existing->user_id === $user->id) {
                    // User is returning to complete registration
                    session(['partner_registration' => array_merge($reg, ['director_name' => $reg['director_name'] ?? null])]);
                    return redirect()->route('partner.register.offer');
                }
                return back()->withErrors(['error' => 'Организация с таким ИНН уже зарегистрирована в системе.'])->withInput();
            }
        }

        return DB::transaction(function() use ($user, $request, $optionsJson, $reg, $isUpgrade, $businessEmail) {
            try {
                // 🛡️ Preserve original B2C nickname/display name in user meta before we apply official legal names
                $meta = $user->meta ?? [];
                if (!isset($meta['nickname'])) {
                    $meta['nickname'] = $user->first_name
                        ?: ($user->email ? explode('@', $user->email)[0] : ('SL1 '.substr((string) ($user->meta['entity_l1_address'] ?? $user->meta['l1_address'] ?? $user->id), -8)));
                    $user->meta = $meta;
                    $user->save();
                }

                // 📝 Parse and dynamically update User's first, last, and middle name from Director or Representative details
                $signerRole = $reg['signer_role'] ?? 'ceo';
                $rawSignerName = ($signerRole === 'representative') 
                    ? ($reg['signer_name'] ?? '') 
                    : ($reg['director_name'] ?? '');

                if (!empty($rawSignerName)) {
                    $parts = array_filter(explode(' ', trim($rawSignerName)));
                    $parts = array_values($parts);
                    
                    $lastName = $parts[0] ?? '';
                    $firstName = $parts[1] ?? '';
                    $middleName = $parts[2] ?? '';

                    $user->update([
                        'first_name' => $firstName ?: $user->first_name,
                        'last_name' => $lastName ?: $user->last_name,
                        'middle_name' => $middleName ?: $user->middle_name,
                    ]);
                }

                if (!$isUpgrade) {
                    // 1. Store Passkey
                    $passkey = app(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class)->execute(
                        $user,
                        $request->input('passkey_attestation'),
                        $optionsJson,
                        $request->getHost(),
                        ['name' => 'Primary Sovereign Identity']
                    );

                    // 2. Anchor stable Simple L1 entity identity.
                    $identity = $this->anchorSimpleL1Identity($user, $passkey);
                    $address = $identity['entity_l1_address'];
                } else {
                    // Upgraded users already have their L1 Address and Passkeys
                    $address = $user->meta['entity_l1_address'] ?? $user->meta['l1_address'] ?? null;
                    if (!$address || !preg_match('/^sl1e_[a-f0-9]{39}$/i', (string) $address)) {
                        $primaryPasskey = $user->passkeys()->first();
                        if ($primaryPasskey) {
                            $identity = $this->anchorSimpleL1Identity($user, $primaryPasskey);
                            $address = $identity['entity_l1_address'];
                        }
                    }
                }

                // 3. Create LegalEntity (signed businesses still wait for moderation)
                $passkeyAssertion = $request->input('passkey_assertion');
                $isActive = false;
                $passkeyModel = null;

                if ($isUpgrade && $passkeyAssertion) {
                    $signingOptions = session('signing_options');
                    if (!$signingOptions) {
                        throw new \Exception('Сессия подписания истекла. Пожалуйста, обновите страницу.');
                    }

                    $assertionString = is_string($passkeyAssertion) ? $passkeyAssertion : json_encode($passkeyAssertion);
                    $passkeyModel = app(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class)->execute(
                        $assertionString,
                        $signingOptions
                    );

                    if (!$passkeyModel || $passkeyModel->authenticatable_id !== $user->id) {
                        throw new \Exception('Неверная криптографическая подпись.');
                    }
                    $isActive = true;
                }

                $brand = \App\Models\Brand::find($request->input('brand_id')) ?? \App\Models\Brand::first();
                $entity = LegalEntity::create([
                    'brand_id' => $brand->id,
                    'user_id' => $user->id,
                    'name' => $reg['legal_name'] ?? 'Pending Entity',
                    'inn' => $reg['inn'],
                    'email' => $businessEmail,
                    'status' => $isActive ? 'pending_moderation' : 'pending_signature',
                    'is_active' => false,
                    'agreement_signed_at' => $isActive ? now() : null,
                    'agreement_signature' => $isActive ? (is_string($passkeyAssertion) ? $passkeyAssertion : json_encode($passkeyAssertion)) : null,
                    'agreement_metadata' => [
                        'signer_role' => $reg['signer_role'] ?? 'ceo',
                        'signer_name' => $reg['signer_name'] ?? ($reg['director_name'] ?? null),
                        'agreement_type' => $this->agreementTypeForRegistration($reg),
                        'business_email' => $businessEmail,
                        'business_email_verified_at' => now()->toIso8601String(),
                        'party_type' => $reg['dadata_party_type'] ?? null,
                        'tax_system' => $reg['tax_system'] ?? null,
                        'l1_address' => $address,
                        'signed_at' => $isActive ? now()->toIso8601String() : null,
                        'signature_type' => $isActive ? 'passkey_assertion_v1' : null,
                        'passkey_id' => $isActive ? $passkeyModel->id : null,
                        'moderation_submitted_at' => $isActive ? now()->toIso8601String() : null,
                    ]
                ]);

                $user->managedLegalEntities()->attach($entity->id, ['role' => 'admin']);
                
                if ($isActive) {
                    // 🏦 TRANSFORM USER TO SELLER & LINK
                    $seller = \App\Models\Seller::findByEmail($businessEmail);
                    if (!$seller) {
                        $seller = \App\Models\Seller::create([
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'middle_name' => $user->middle_name,
                            'email' => $businessEmail,
                            'email_verified_at' => now(),
                            'phone' => $user->phone,
                            'password' => $user->password,
                            'is_active' => true,
                        ]);
                    } else {
                        $seller->update([
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'middle_name' => $user->middle_name,
                        ]);
                    }

                    $this->assignB2BRoles($user, $seller);

                    $entity->update(['seller_id' => $seller->id]);
                    $seller->managedLegalEntities()->syncWithoutDetaching([
                        $entity->id => ['role' => 'owner', 'user_id' => $user->id]
                    ]);

                    session()->forget(['partner_registration', 'passkey_options', 'signing_options']);
                    Auth::login($user);

                    return response()->json([
                        'success' => true,
                        'redirect' => route('partner.onboarding')
                    ]);
                }

                // Store registration context for Step 2 (Offer)
                session(['partner_registration' => array_merge($reg, ['director_name' => $reg['director_name'] ?? null])]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => true,
                        'redirect' => route('partner.register.offer')
                    ]);
                }

                return redirect()->route('partner.register.offer');
            } catch (\Exception $e) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json(['error' => $e->getMessage()], 422);
                }
                return back()->withErrors(['error' => $e->getMessage()])->withInput();
            }
        });
    }

    /**
     * STEP 2: Show Offer
     */
    public function showOffer()
    {
        $reg = session('partner_registration');
        $user = Auth::user();
        if (!$reg || !$user) return redirect()->route('partner.register');

        $type = $this->agreementTypeForRegistration($reg);
        $fallbackType = ($reg['is_b2b'] ?? true) ? 'b2b' : 'b2c';
        $agreement = \App\Models\Agreement::where('type', $type)->where('is_active', true)->latest('published_at')->first();
        if (!$agreement && in_array($type, ['b2b', 'b2c'], true)) {
            $agreement = \App\Models\Agreement::where('type', $fallbackType)->where('is_active', true)->latest('published_at')->first();
        }
        $agreementText = $agreement ? $agreement->content : $this->fallbackAgreementText($type);

        // 🔑 Generate AUTHENTICATE options for the signature (Assertion)
        // IMPORTANT: Use specific allowCredentials so the browser uses the right passkey.
        $signingOptions = $this->generateSigningOptionsForUser($user);
        session(['signing_options' => $signingOptions]);

        return view('partner.register_step3', [
            'registration' => $reg,
            'agreementType' => $type,
            'agreementTitle' => $this->agreementTitle($type),
            'agreementText' => $agreementText,
            'signingOptions' => $signingOptions
        ]);
    }

    public function showOnboarding(Request $request)
    {
        $user = Auth::user();
        abort_unless($user, 401);

        $entity = $user->managedLegalEntities()->latest()->first()
            ?? $user->legalEntities()->latest()->first();

        if (! $entity) {
            return redirect()->route('partner.register');
        }

        if ($entity->status === 'active' && $entity->is_active) {
            return redirect()->route('partner.dashboard');
        }

        return view('partner.onboarding', [
            'user' => $user,
            'legalEntity' => $entity,
            'submittedAt' => $entity?->agreement_signed_at,
        ]);
    }

    /**
     * ATOMIC STEP 1: Register Sovereign Identity (Passkey)
     */
    public function registerIdentity(Request $request)
    {
        $user = Auth::user();
        $optionsJson = session('passkey_options');
        $reg = session('partner_registration');
        
        if (!$optionsJson || !$reg) {
            return response()->json(['error' => 'Session expired'], 422);
        }

        return DB::transaction(function() use ($user, $request, $optionsJson, $reg) {
            try {
                // 1. Create Passkey
                $passkey = app(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class)->execute(
                    $user,
                    $request->getContent(),
                    $optionsJson,
                    $request->getHost(),
                    ['name' => 'Primary Sovereign Identity']
                );

                // 2. Anchor stable Simple L1 entity identity.
                $identity = $this->anchorSimpleL1Identity($user, $passkey);
                $address = $identity['entity_l1_address'];

                $isB2b = isset($reg['inn']) && ($reg['is_b2b'] ?? true);

                if ($isB2b) {
                    $businessEmail = mb_strtolower(trim((string) ($reg['business_email'] ?? session('business_registration_verified_email') ?? $user->email ?? '')));
                    // 3. Create or Update "Pending" LegalEntity
                    $entity = LegalEntity::findByInn($reg['inn']);
                    if ($entity) {
                        $entity->update([
                            'user_id' => $user->id,
                            'name' => $reg['legal_name'] ?? $entity->name ?? 'Pending Entity',
                            'email' => $businessEmail ?: $entity->email,
                            'status' => 'pending_signature',
                            'agreement_signed_at' => null,
                            'agreement_signature' => null,
                            'agreement_metadata' => array_merge($entity->agreement_metadata ?? [], [
                                'business_email' => $businessEmail ?: data_get($entity->agreement_metadata, 'business_email'),
                                'business_email_verified_at' => $businessEmail ? now()->toIso8601String() : data_get($entity->agreement_metadata, 'business_email_verified_at'),
                                'identity_anchored_at' => now()->toIso8601String(),
                                'l1_address' => $address,
                                'signed_at' => null,
                                'signature_type' => null,
                                'passkey_id' => null
                            ])
                        ]);
                    } else {
                        $entity = LegalEntity::create([
                            'brand_id' => $reg['brand_id'] ?? null,
                            'user_id' => $user->id,
                            'name' => $reg['name'] ?? $reg['legal_name'] ?? 'Pending Entity',
                            'inn' => $reg['inn'],
                            'email' => $businessEmail ?: null,
                            'status' => 'pending_signature',
                            'agreement_metadata' => [
                                'business_email' => $businessEmail ?: null,
                                'business_email_verified_at' => $businessEmail ? now()->toIso8601String() : null,
                                'identity_anchored_at' => now()->toIso8601String(),
                                'l1_address' => $address
                            ]
                        ]);
                    }

                    // 🏦 TRANSFORM USER TO SELLER EARLY
                    $seller = $businessEmail ? \App\Models\Seller::findByEmail($businessEmail) : null;
                    if (!$seller) {
                        $seller = \App\Models\Seller::create([
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'middle_name' => $user->middle_name,
                            'email' => $businessEmail ?: $user->email,
                            'email_verified_at' => $businessEmail ? now() : $user->email_verified_at,
                            'phone' => $user->phone,
                            'password' => $user->password,
                            'is_active' => true,
                        ]);
                    }
                    $this->assignB2BRoles($user, $seller);

                    // Link Seller to Entity through managers
                    $entity->update(['seller_id' => $seller->id]);
                    $seller->managedLegalEntities()->syncWithoutDetaching([
                        $entity->id => ['role' => 'owner', 'user_id' => $user->id]
                    ]);
                    $user->managedLegalEntities()->syncWithoutDetaching([
                        $entity->id => ['role' => 'admin']
                    ]);

                    // 🔐 Establish session immediately after identity anchoring
                    Auth::login($user);
                    Auth::guard('sellers')->login($seller);

                    // Prepare for the NEXT step: Signing the offer
                    // Use specific allowCredentials so the browser uses the right passkey.
                    $authOptions = $this->generateSigningOptionsForUser($user);
                    session(['signing_options' => $authOptions]);
                    \Illuminate\Support\Facades\Session::save();

                    \Illuminate\Support\Facades\Log::channel('daily')->info('SOVEREIGN_INTENT: Identity Anchoring B2B', [
                        'user_id' => $user->id,
                        'l1_address' => $address,
                        'inn' => $reg['inn'],
                        'ts' => now()->toIso8601String()
                    ]);

                    return response()->json([
                        'success' => true,
                        'signing_options' => json_decode($authOptions)
                    ]);
                } else {
                    // Personal User (Simple registration flow)
                    Auth::login($user);

                    \Illuminate\Support\Facades\Log::channel('daily')->info('SOVEREIGN_INTENT: Identity Anchoring Personal', [
                        'user_id' => $user->id,
                        'l1_address' => $address,
                        'ts' => now()->toIso8601String()
                    ]);

                    return response()->json([
                        'success' => true,
                        'redirect' => '/cabinet'
                    ]);
                }
            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        });
    }

    /**
     * ATOMIC STEP 2: Sign Agreement with Created Identity
     */
    public function signAgreement(Request $request)
    {
        file_put_contents('/tmp/debug.log', date('Y-m-d H:i:s') . " - signAgreement called\n", FILE_APPEND);
        try {
            $user = Auth::user();
            if (!$user) {
                file_put_contents('/tmp/debug.log', date('Y-m-d H:i:s') . " - signAgreement: Unauthorized\n", FILE_APPEND);
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $assertion = $request->input('assertion');
            if (is_array($assertion)) {
                $assertion = json_encode($assertion);
            }

            $signingOptions = session('signing_options');
            file_put_contents('/tmp/debug.log', date('Y-m-d H:i:s') . " - signAgreement options: " . print_r($signingOptions, true) . "\n", FILE_APPEND);

            if (!$signingOptions) {
                file_put_contents('/tmp/debug.log', date('Y-m-d H:i:s') . " - signAgreement: Signing context lost\n", FILE_APPEND);
                throw new \Exception('Signing context lost. Please refresh the page.');
            }

            $passkey = app(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class)->execute(
                $assertion, // The assertion response
                $signingOptions
            );

            if (!$passkey || $passkey->authenticatable_id !== $user->id) {
                $debugInfo = "Passkey null? " . ($passkey ? 'No' : 'Yes') . 
                             " | passkey->auth_id: " . ($passkey ? $passkey->authenticatable_id : 'N/A') .
                             " | user->id: " . $user->id;
                file_put_contents('/tmp/debug.log', date('Y-m-d H:i:s') . " - signAgreement failed: " . $debugInfo . "\n", FILE_APPEND);
                throw new \Exception('Invalid or unauthorized signature. DEBUG: ' . $debugInfo);
            }

            $reg = session('partner_registration') ?? [];
            $isB2b = $reg['is_b2b'] ?? true;

            if (!$isB2b) {
                // Personal User: Save agreement signature audit log in user meta
                $meta = $user->meta ?? [];
                $meta['b2c_agreement_signed_at'] = now()->toIso8601String();
                $meta['b2c_agreement_signature'] = $request->getContent();
                $user->meta = $meta;
                $user->save();

                app(\App\Services\IntentLedgerService::class)->record(
                    eventType: 'AGREEMENT_SIGN_INTENT',
                    intentType: 'agreement.sign',
                    entity: $user,
                    payload: [
                        'agreement_type' => 'b2c',
                        'signature_hash' => hash('sha256', $request->getContent()),
                        'signed_at' => $meta['b2c_agreement_signed_at'],
                    ],
                    request: $request,
                    passkey: $passkey,
                    user: $user,
                    scope: 'agreement',
                    resource: 'b2c',
                );

                session()->forget(['partner_registration', 'passkey_options', 'signing_options']);
                Auth::login($user);

                return response()->json([
                    'success' => true,
                    'redirect' => '/cabinet'
                ]);
            }

            // ✍️ MARK ENTITY AS SIGNED
            $entity = $user->managedLegalEntities()->where('status', 'pending_signature')->latest()->first();

            if (!$entity) {
                // Check if it was ALREADY activated in a previous partially failed attempt
                $alreadyPendingModeration = $user->managedLegalEntities()->where('status', 'pending_moderation')->latest()->first();
                $alreadyActive = $user->managedLegalEntities()->where('status', 'active')->latest()->first();
                if ($alreadyPendingModeration || $alreadyActive) {
                    return response()->json([
                        'success' => true,
                        'redirect' => $alreadyActive ? route('partner.dashboard') : route('partner.onboarding')
                    ]);
                }
                throw new \Exception('No pending institutional brick found for signing.');
            }

            $reg = session('partner_registration') ?? [];
            $businessEmail = mb_strtolower(trim((string) ($reg['business_email'] ?? $entity->email ?? session('business_registration_verified_email') ?? $user->email ?? '')));
            
            $entity->update([
                'email' => $businessEmail ?: $entity->email,
                'status' => 'pending_moderation',
                'is_active' => false,
                'agreement_signed_at' => now(),
                'agreement_signature' => $request->getContent(), // Store raw assertion as audit trail
                'agreement_metadata' => array_merge($entity->agreement_metadata ?? [], [
                    'signed_at' => now()->toIso8601String(),
                    'business_email' => $businessEmail ?: data_get($entity->agreement_metadata, 'business_email'),
                    'business_email_verified_at' => $businessEmail ? now()->toIso8601String() : data_get($entity->agreement_metadata, 'business_email_verified_at'),
                    'signer_role' => $reg['signer_role'] ?? 'ceo',
                    'signer_name' => $reg['signer_name'] ?? ($reg['director_name'] ?? $user->getFullName()),
                    'agreement_type' => $this->agreementTypeForRegistration($reg),
                    'party_type' => $reg['dadata_party_type'] ?? null,
                    'tax_system' => $reg['tax_system'] ?? null,
                    'signature_type' => 'passkey_assertion_v1',
                    'passkey_id' => $passkey->id,
                    'moderation_submitted_at' => now()->toIso8601String(),
                ])
            ]);

            app(\App\Services\IntentLedgerService::class)->record(
                eventType: 'AGREEMENT_SIGN_INTENT',
                intentType: 'agreement.sign',
                entity: $entity,
                payload: [
                    'agreement_type' => $this->agreementTypeForRegistration($reg),
                    'signature_hash' => hash('sha256', $request->getContent()),
                    'signed_at' => now()->toIso8601String(),
                ],
                request: $request,
                passkey: $passkey,
                user: $user,
                scope: 'agreement',
                resource: 'legal_entity:'.$entity->id,
                legalEntity: $entity,
            );

            // 🏦 TRANSFORM USER TO SELLER & LINK
            $seller = $businessEmail ? \App\Models\Seller::findByEmail($businessEmail) : null;
            if (!$seller) {
                $seller = \App\Models\Seller::create([
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'middle_name' => $user->middle_name,
                    'email' => $businessEmail ?: $user->email,
                    'email_verified_at' => $businessEmail ? now() : $user->email_verified_at,
                    'phone' => $user->phone,
                    'password' => $user->password, // Sync credentials
                    'is_active' => true,
                ]);
            } else {
                $seller->update([
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'middle_name' => $user->middle_name,
                ]);
            }

            $this->assignB2BRoles($user, $seller);

            // Link Seller to Entity through managers
            $entity->update(['seller_id' => $seller->id]);
            $seller->managedLegalEntities()->syncWithoutDetaching([
                $entity->id => ['role' => 'owner', 'user_id' => $user->id]
            ]);

            app(\App\Services\IntentLedgerService::class)->record(
                eventType: 'LEGAL_ENTITY_BIND_OWNER_INTENT',
                intentType: 'legal_entity.bind_owner',
                entity: $entity,
                payload: [
                    'seller_id' => $seller->id,
                    'owner_user_id' => $user->id,
                    'bound_at' => now()->toIso8601String(),
                ],
                request: $request,
                passkey: $passkey,
                user: $user,
                scope: 'legal_entity.owner',
                resource: 'legal_entity:'.$entity->id,
                legalEntity: $entity,
            );

            session()->forget(['partner_registration', 'passkey_options', 'signing_options']);

            // 🏛️ Issue Sovereign Mandate for this session
            $l1Address = app(\App\Services\L1IdentityService::class)->addressFromPasskey($passkey);

            $ledgerEntry = app(\App\Services\LedgerService::class)->record(
                shop: null,
                eventType: 'IDENTITY_ENTRY_INTENT',
                entity: $passkey,
                payload: [
                    'intent' => 'SYSTEM_ACCESS',
                    'l1_address' => $l1Address,
                    'assertion_id' => $passkey->credential_id,
                ],
                legalEntity: $entity,
                triggerSource: "DID:PASSKEY:{$l1Address}",
                inputData: [
                    'assertion' => is_string($assertion) ? json_decode($assertion, true) : $assertion,
                    'intent_entropy' => null,
                ]
            );

            session(['sovereign_mandate_id' => $ledgerEntry->id]);
            session(['sovereign_mandate_hash' => $ledgerEntry->fingerprint]);

            // 🔐 Seamless Sovereign Access: Ensure session is solid
            Auth::login($user);
            $seller = $businessEmail ? \App\Models\Seller::findByEmail($businessEmail) : null;
            if ($seller) {
                Auth::guard('sellers')->login($seller);
            }

            return response()->json([
                'success' => true,
                'redirect' => route('partner.onboarding')
            ]);

        } catch (\Exception $e) {
            \Log::error("Signature Finalization Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function registrationUser(?string $email, ?string $displayName = null, ?string $phone = null): User
    {
        if ($email) {
            $user = User::findByEmail($email) ?? User::create([
                'first_name' => strstr($email, '@', true) ?: $email,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make(Str::random(32)),
                'password_login_enabled' => false,
            ]);

            $this->applyRegistrationContacts($user, $email, $phone);

            return $this->applyRegistrationDisplayName($user, $displayName);
        }

        $pendingUserId = (int) session('passkey_registration_user_id');
        $pendingUser = $pendingUserId > 0 ? User::find($pendingUserId) : null;

        if ($pendingUser && ! $pendingUser->passkeys()->exists()) {
            $this->applyRegistrationContacts($pendingUser, null, $phone);

            return $this->applyRegistrationDisplayName($pendingUser, $displayName);
        }

        $suffix = $this->profileSuffix();
        $displayName = $displayName ?: "Meanly Profile {$suffix}";
        $user = User::create([
            'first_name' => $displayName,
            'last_name' => null,
            'email' => null,
            'phone' => $phone,
            'password' => Hash::make(Str::random(32)),
            'password_login_enabled' => false,
            'meta' => [
                'registration_source' => 'simple_l1_identity',
                'display_name' => $displayName,
                'profile_suffix' => $suffix,
            ],
        ]);

        session(['passkey_registration_user_id' => $user->id]);

        return $user;
    }

    private function applyRegistrationContacts(User $user, mixed $email = null, mixed $phone = null): User
    {
        $email = trim((string) $email);
        $phone = trim((string) $phone);
        $changed = false;

        if ($email !== '' && $user->email !== $email) {
            $user->email = $email;
            $changed = true;
        }

        if ($phone !== '' && $user->phone !== $phone) {
            $user->phone = $phone;
            $changed = true;
        }

        if ($changed) {
            $user->save();
        }

        return $user;
    }

    private function applyRegistrationDisplayName(User $user, ?string $displayName): User
    {
        $meta = $user->meta ?? [];

        if ($displayName) {
            $meta['display_name'] = $displayName;
            $user->first_name = $displayName;
            $user->last_name = null;
        } elseif (empty($meta['display_name'])) {
            $suffix = (string) ($meta['profile_suffix'] ?? $this->profileSuffix());
            $meta['profile_suffix'] = $suffix;
            $meta['display_name'] = $user->first_name ?: "Meanly Profile {$suffix}";
            $user->first_name = $meta['display_name'];
        }

        $user->meta = $meta;
        $user->save();

        return $user;
    }

    private function normalizeDisplayName(mixed $value): ?string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        if ($name === '') {
            return null;
        }

        $name = mb_substr($name, 0, 80);

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    }

    private function validPersonName(mixed $value): bool
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');
        if ($name === '') {
            return false;
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2 || count($parts) > 4) {
            return false;
        }

        foreach ($parts as $part) {
            if (! preg_match('/^[\p{L}][\p{L}\'-]*$/u', $part)) {
                return false;
            }
        }

        return true;
    }

    private function personNameFromDadata(array $party): ?string
    {
        $name = trim(implode(' ', array_filter([
            data_get($party, 'fio.surname'),
            data_get($party, 'fio.name'),
            data_get($party, 'fio.patronymic'),
        ])));

        return $name !== '' ? $name : null;
    }

    private function profileSuffix(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }

    /**
     * @return array{entity_l1_address: string, key_l1_address: string}
     */
    private function anchorSimpleL1Identity(User $user, \Spatie\LaravelPasskeys\Models\Passkey $passkey): array
    {
        $existingEntity = data_get($user->meta ?? [], 'entity_l1_address', data_get($user->meta ?? [], 'l1_address'));
        $hadEntity = is_string($existingEntity) && preg_match('/^sl1e_[a-f0-9]{39}$/i', $existingEntity) === 1;
        $identity = app(\App\Services\L1IdentityService::class)->bindUserToEntityIdentity($user, $passkey);
        $intentLedger = app(\App\Services\IntentLedgerService::class);

        if (! $hadEntity) {
            $intentLedger->record(
                eventType: 'IDENTITY_CREATE_INTENT',
                intentType: 'identity.create',
                entity: $user,
                payload: [
                    'entity_l1_address' => $identity['entity_l1_address'],
                    'created_at' => now()->toIso8601String(),
                ],
                request: request(),
                passkey: $passkey,
                user: $user,
                scope: 'identity.entity',
                resource: 'sl1e',
            );
        }

        $intentLedger->record(
            eventType: 'IDENTITY_BIND_DEVICE_INTENT',
            intentType: 'identity.bind_device',
            entity: $passkey,
            payload: [
                'entity_l1_address' => $identity['entity_l1_address'],
                'key_l1_address' => $identity['key_l1_address'],
                'bound_at' => now()->toIso8601String(),
            ],
            request: request(),
            passkey: $passkey,
            user: $user,
            scope: 'identity.devices',
            resource: 'passkey:'.$passkey->id,
        );

        return $identity;
    }

    private function assignB2BRoles(User $user, \App\Models\Seller $seller): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
        $sellerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'sellers']);

        if (! $user->hasRole('b2b_partner')) {
            $user->assignRole('b2b_partner');
        }

        if (! $seller->hasRole($sellerRole)) {
            $seller->assignRole($sellerRole);
        }
    }

    private function calculateL1Address(string $publicKey): string
    {
        return app(\App\Services\L1IdentityService::class)->keyAddressFromPublicKey($publicKey);
    }

    private function registrationTarget(Request $request): string
    {
        $target = $request->input('registration_target') ?: session('registration_target', 'legal_entity');

        return $target === 'legal_entity' ? 'legal_entity' : 'profile';
    }

    private function registrationRouteFor(Request $request): string
    {
        return $this->registrationTarget($request) === 'legal_entity'
            ? 'business.register'
            : 'partner.register';
    }

    /**
     * Generate WebAuthn authentication options with allowCredentials populated
     * from the user's actual passkeys in the DB. This prevents Safari/iCloud Keychain
     * from picking an old passkey for the domain that is not in our database.
     */
    private function generateSigningOptionsForUser(User $user): string
    {
        $passkeys = $user->passkeys()->get();
        $allowCredentials = $passkeys->map(function ($passkey) {
            return new \Webauthn\PublicKeyCredentialDescriptor(
                type: 'public-key',
                id: base64_decode($passkey->credential_id),
                transports: []
            );
        })->all();

        $options = [
            'challenge'        => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'rpId'             => request()->getHost(),
            'allowCredentials' => $allowCredentials,
            'timeout'          => 60000,
            'userVerification' => 'preferred',
        ];

        // Serialize to the same format as the Spatie action so FindPasskeyToAuthenticateAction can deserialize
        $serializer = \Spatie\LaravelPasskeys\Support\Serializer::make();
        $pkrOptions = new \Webauthn\PublicKeyCredentialRequestOptions(
            challenge: base64_decode(strtr($options['challenge'], '-_', '+/') . str_repeat('=', (4 - strlen($options['challenge']) % 4) % 4)),
            rpId: $options['rpId'],
            allowCredentials: $allowCredentials,
            userVerification: \Webauthn\AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            timeout: 60000,
        );

        $json = $serializer->toJson($pkrOptions);

        // Store for FindPasskeyToAuthenticateAction (it reads from this session key)
        session(['passkey-authentication-options' => $json]);

        return $json;
    }

    private function agreementTypeForRegistration(array $reg): string
    {
        if (!($reg['is_b2b'] ?? true)) {
            return 'b2c';
        }

        return match ($reg['dadata_party_type'] ?? null) {
            'NPD' => 'b2b_npd',
            'INDIVIDUAL' => 'b2b_ip',
            'LEGAL' => 'b2b_legal',
            default => 'b2b',
        };
    }

    private function agreementTitle(string $type): string
    {
        return match ($type) {
            'b2b_npd' => 'Оферта для самозанятых',
            'b2b_ip' => 'Оферта для ИП',
            'b2b_legal' => 'Оферта для юридических лиц',
            'b2c' => 'Пользовательское соглашение',
            default => 'Публичная оферта',
        };
    }

    private function fallbackAgreementText(string $type): string
    {
        return match ($type) {
            'b2b_npd' => "ДОГОВОР-ОФЕРТА ДЛЯ САМОЗАНЯТЫХ ПАРТНЕРОВ (НПД)\n\n"
                . "1. ПРЕДМЕТ ДОГОВОРА\n"
                . "1.1. Исполнитель, применяющий налог на профессиональный доход, подключается к Meanly для размещения допустимых цифровых предложений и получения выплат в рамках лимитов НПД.\n\n"
                . "2. ОГРАНИЧЕНИЯ НПД\n"
                . "2.1. Самозанятый самостоятельно соблюдает лимиты НПД, ограничения по найму сотрудников, видам деятельности и формированию чеков в приложении «Мой налог».\n\n"
                . "3. РАСШИРЕНИЕ ДО ИП\n"
                . "3.1. Для маркетплейс-интеграций, командного доступа, оптовых закупок, складов кодов, внешнего API и выплат на расчетный счет рекомендуется открыть ИП и пройти повторную проверку ИНН.",
            'b2b_ip' => "ДОГОВОР-ОФЕРТА ДЛЯ ИНДИВИДУАЛЬНЫХ ПРЕДПРИНИМАТЕЛЕЙ\n\n"
                . "1. ПРЕДМЕТ ДОГОВОРА\n"
                . "1.1. ИП подключается к Meanly для размещения цифровых товарных предложений, интеграций с витринами, учета заказов, API и взаиморасчетов.\n\n"
                . "2. ПОДТВЕРЖДЕНИЕ СТАТУСА\n"
                . "2.1. Статус ИП подтверждается по данным DaData/ФНС и суверенной Passkey-подписью владельца профиля.",
            'b2b_legal' => "ДОГОВОР-ОФЕРТА ДЛЯ ЮРИДИЧЕСКИХ ЛИЦ\n\n"
                . "1. ПРЕДМЕТ ДОГОВОРА\n"
                . "1.1. Юридическое лицо подключается к Meanly для размещения цифровых товарных предложений, интеграций с витринами, учета заказов, API и взаиморасчетов.\n\n"
                . "2. ПОЛНОМОЧИЯ\n"
                . "2.1. Подписант подтверждает полномочия действовать от имени организации или на основании доверенности.",
            default => "Текст оферты не найден.",
        };
    }
}
