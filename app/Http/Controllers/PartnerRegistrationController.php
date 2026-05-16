<?php

namespace App\Http\Controllers;

use App\Models\LegalEntity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PartnerRegistrationController extends Controller
{
    public function show(Request $request)
    {
        $brand = null;
        if ($request->has('brand_id')) {
            $brand = \App\Models\Brand::find($request->brand_id);
        }

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

        return view('partner.register', [
            'brand' => $brand,
            'detectedCountry' => $detectedCountry,
            'detectedCountryName' => $detectedCountryName,
            'supportedJurisdictions' => $brand && $brand->compliance_config ? array_keys($brand->compliance_config) : null,
            'complianceConfig' => $brand ? $brand->compliance_config : null
        ]);
    }

    /**
     * AJAX: Generate options for immediate Passkey registration
     */
    public function options(Request $request)
    {
        $email = $request->input('email');
        if (!$email) return response()->json(['error' => 'Email required'], 422);

        $user = User::findByEmail($email) ?? User::create([
            'first_name' => explode('@', $email)[0],
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ]);
        
        Auth::login($user);

        $json = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction::class)->execute($user, true);
        $optionsArray = json_decode($json, true);
        $optionsArray['rp']['id'] = 'meanly.test';
        
        // Save for verification in the next step
        session(['passkey_options' => json_encode($optionsArray)]);

        // 🛡️ IMPORTANT: Laravel rotates the session on login, invalidating the old CSRF token.
        // We must provide the new one to the frontend.
        return response()->json([
            'options' => $optionsArray,
            'new_csrf' => csrf_token()
        ]);
    }

    /**
     * STEP 1 POST: Atomic User + Entity + Identity Creation
     */
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'inn' => 'required|string',
            'passkey_attestation' => 'required' // The identity anchor
        ]);

        $user = Auth::user();
        $optionsJson = session('passkey_options');
        $reg = $request->all();
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

        return DB::transaction(function() use ($user, $request, $optionsJson, $reg) {
            try {
                // 1. Store Passkey
                $passkey = app(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class)->execute(
                    $user,
                    $request->input('passkey_attestation'),
                    $optionsJson,
                    $request->getHost(),
                    ['name' => 'Primary Sovereign Identity']
                );

                // 2. Anchor L1 Address
                $address = $this->calculateL1Address($passkey->data->credentialPublicKey);
                $user->meta = array_merge($user->meta ?? [], ['l1_address' => $address]);
                $user->save();

                // 3. Create LegalEntity
                $brand = \App\Models\Brand::find($request->input('brand_id')) ?? \App\Models\Brand::first();
                $entity = LegalEntity::create([
                    'brand_id' => $brand->id,
                    'user_id' => $user->id,
                    'name' => $reg['legal_name'] ?? 'Pending Entity',
                    'inn' => $reg['inn'],
                    'status' => 'pending_signature',
                    'agreement_metadata' => [
                        'signer_role' => $reg['signer_role'] ?? 'ceo',
                        'signer_name' => $reg['signer_name'] ?? ($reg['director_name'] ?? null),
                        'l1_address' => $address
                    ]
                ]);

                $user->managedLegalEntities()->attach($entity->id, ['role' => 'admin']);
                
                // Store registration context for Step 2 (Offer)
                session(['partner_registration' => array_merge($reg, ['director_name' => $reg['director_name'] ?? null])]);

                return redirect()->route('partner.register.offer');
            } catch (\Exception $e) {
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

        $agreement = \App\Models\Agreement::where('is_active', true)->latest('published_at')->first();
        $agreementText = $agreement ? $agreement->content : "Текст оферты не найден.";

        // 🔑 Since identity is established in Step 1, we now generate AUTHENTICATE options for the signature (Assertion)
        $json = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction::class)->execute($user);
        $optionsArray = json_decode($json, true);
        
        // 🛡️ Ensure RP ID stability for local dev
        $optionsArray['rpId'] = 'meanly.test';
        
        $signingOptions = json_encode($optionsArray);
        session(['signing_options' => $signingOptions]);

        return view('partner.register_step3', [
            'registration' => $reg,
            'agreementText' => $agreementText,
            'signingOptions' => $signingOptions
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

                // 2. Anchor L1 Address
                $address = $this->calculateL1Address($passkey->data->credentialPublicKey);
                $meta = $user->meta ?? [];
                $meta['l1_address'] = $address;
                $user->meta = $meta;
                $user->save();

                // 3. Create "Pending" LegalEntity
                $entity = LegalEntity::create([
                    'brand_id' => $reg['brand_id'] ?? null,
                    'user_id' => $user->id,
                    'legal_name' => $reg['name'] ?? 'Pending Entity',
                    'inn' => $reg['inn'],
                    'status' => 'pending_signature',
                    'agreement_metadata' => [
                        'identity_anchored_at' => now()->toIso8601String(),
                        'l1_address' => $address
                    ]
                ]);

                // 🏦 TRANSFORM USER TO SELLER EARLY
                $seller = \App\Models\Seller::findByEmail($user->email);
                if (!$seller) {
                    $seller = \App\Models\Seller::create([
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'middle_name' => $user->middle_name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'password' => $user->password,
                        'is_active' => true,
                    ]);
                }
                $seller->assignRole('b2b_partner');
                $user->assignRole('b2b_partner');

                // Link Seller to Entity through managers
                $entity->update(['seller_id' => $seller->id]);
                $seller->managedLegalEntities()->syncWithoutDetaching([
                    $entity->id => ['role' => 'owner', 'user_id' => $user->id]
                ]);

                // 🔐 Establish session immediately after identity anchoring
                Auth::login($user);
                Auth::guard('sellers')->login($seller);

                // Prepare for the NEXT step: Signing the offer
                $authOptions = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyAuthenticationOptionsAction::class)->execute($user);
                session(['signing_options' => $authOptions]);
                \Illuminate\Support\Facades\Session::save();

                \Illuminate\Support\Facades\Log::channel('daily')->info('SOVEREIGN_INTENT: Identity Anchoring', [
                    'user_id' => $user->id,
                    'l1_address' => $address,
                    'inn' => $reg['inn'],
                    'ts' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => true,
                    'signing_options' => json_decode($authOptions)
                ]);
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
        try {
            $user = Auth::user();
            $signingOptions = session('signing_options');
            
            if (!$signingOptions) {
                throw new \Exception('Signing context lost. Please refresh the page.');
            }

            // 🔑 VERIFY THE CRYPTOGRAPHIC SIGNATURE
            $payload = $request->json()->all();
            $assertion = json_encode($payload['assertion'] ?? $payload);
            $entropy = $payload['intent_entropy'] ?? [];

            $passkey = app(\Spatie\LaravelPasskeys\Actions\FindPasskeyToAuthenticateAction::class)->execute(
                $assertion, // The assertion response
                $signingOptions
            );

            if (!$passkey || $passkey->authenticatable_id !== $user->id) {
                throw new \Exception('Invalid or unauthorized signature.');
            }

            // ✍️ MARK ENTITY AS SIGNED
            $entity = $user->managedLegalEntities()->where('status', 'pending_signature')->latest()->first();

            if (!$entity) {
                // Check if it was ALREADY activated in a previous partially failed attempt
                $alreadyActive = $user->managedLegalEntities()->where('status', 'active')->latest()->first();
                if ($alreadyActive) {
                    return response()->json([
                        'success' => true,
                        'redirect' => route('filament.partner.pages.dashboard', ['tenant' => $alreadyActive->id])
                    ]);
                }
                throw new \Exception('No pending institutional brick found for signing.');
            }

            $reg = session('partner_registration') ?? [];
            
            $entity->update([
                'status' => 'active',
                'is_active' => true,
                'agreement_signed_at' => now(),
                'agreement_signature' => $request->getContent(), // Store raw assertion as audit trail
                'agreement_metadata' => array_merge($entity->agreement_metadata ?? [], [
                    'signed_at' => now()->toIso8601String(),
                    'signer_role' => $reg['signer_role'] ?? 'ceo',
                    'signer_name' => $reg['signer_name'] ?? ($reg['director_name'] ?? $user->getFullName()),
                    'signature_type' => 'passkey_assertion_v1',
                    'passkey_id' => $passkey->id
                ])
            ]);

            // 🏦 TRANSFORM USER TO SELLER & LINK
            $seller = \App\Models\Seller::findByEmail($user->email);
            if (!$seller) {
                $seller = \App\Models\Seller::create([
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'middle_name' => $user->middle_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'password' => $user->password, // Sync credentials
                    'is_active' => true,
                ]);
            }

            $seller->assignRole('b2b_partner');
            $user->assignRole('b2b_partner');

            // Link Seller to Entity through managers
            $entity->update(['seller_id' => $seller->id]);
            $seller->managedLegalEntities()->syncWithoutDetaching([
                $entity->id => ['role' => 'owner', 'user_id' => $user->id]
            ]);

            session()->forget(['partner_registration', 'passkey_options', 'signing_options']);

            // 🔐 Seamless Sovereign Access: Ensure session is solid
            Auth::login($user);
            $seller = \App\Models\Seller::findByEmail($user->email);
            if ($seller) {
                Auth::guard('sellers')->login($seller);
            }

            return response()->json([
                'success' => true,
                'redirect' => route('filament.partner.pages.dashboard', ['tenant' => $entity->id])
            ]);

        } catch (\Exception $e) {
            \Log::error("Signature Finalization Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    private function calculateL1Address(string $publicKey): string
    {
        return 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);
    }
}
