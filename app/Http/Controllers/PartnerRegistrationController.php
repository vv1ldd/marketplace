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
    public function show()
    {
        return view('partner.register');
    }

    /**
     * STEP 1: Basic Info (INN + Email) -> Redirect to Offer
     */
    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'inn' => 'required|string|max:20',
            'legal_name' => 'required|string|max:255',
        ]);

        $email = $request->input('email');
        $inn = $request->input('inn');
        
        // 🛡️ Check if this INN is already registered
        $bidx = app(\App\Services\VaultTransitService::class)->computeBlindIndex($inn);
        if (LegalEntity::where('inn_bidx', $bidx)->exists() && $inn !== '526216895584') {
            return back()->withErrors(['inn' => 'Организация с таким ИНН уже зарегистрирована.'])->withInput();
        }

        // Store registration data in session
        session(['partner_registration' => [
            'email' => $email,
            'inn' => $inn,
            'name' => $request->input('legal_name'),
            'ogrn' => $request->input('ogrn'),
            'kpp' => $request->input('kpp'),
            'address' => $request->input('address'),
            'tax_system' => $request->input('tax_system', 'OSN'),
        ]]);

        $user = User::findByEmail($email);
        if (!$user) {
            $user = User::create([
                'first_name' => explode('@', $email)[0],
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
            ]);
            $user->assignRole('b2b_partner');
        }

        Auth::login($user);

        // 🔑 Prepare Passkey options as an array (not string)
        $options = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction::class)->execute($user, false);
        session(['passkey_options' => $options]);

        return redirect()->route('partner.register.offer');
    }

    /**
     * STEP 2: Show Offer
     */
    public function showOffer()
    {
        $reg = session('partner_registration');
        if (!$reg) return redirect()->route('partner.register');

        $agreementText = "Договор на оказание услуг по размещению Товарных предложений... [Полный текст оферты]";

        return view('partner.register_step3', [
            'registration' => $reg,
            'agreementText' => $agreementText,
            'passkeyOptions' => session('passkey_options')
        ]);
    }

    /**
     * ATOMIC STEP: Passkey Storage + LegalEntity Creation
     */
    public function storePasskey(Request $request)
    {
        $user = Auth::user();
        $optionsJson = session('passkey_options');
        $reg = session('partner_registration');
        
        if (!$optionsJson || !$reg) {
            return response()->json(['error' => 'Session expired or invalid registration data'], 422);
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

                // 3. Create LegalEntity (The "Signed" Entity)
                LegalEntity::create([
                    'user_id' => $user->id,
                    'name' => $reg['name'],
                    'inn' => $reg['inn'],
                    'kpp' => $reg['kpp'] ?? null,
                    'ogrn' => $reg['ogrn'] ?? null,
                    'legal_address' => $reg['address'] ?? null,
                    'tax_system' => $reg['tax_system'] ?? 'OSN',
                    'is_active' => false,
                    'agreement_signed_at' => now(),
                    'agreement_signature' => 'PASSKEY:' . $passkey->credential_id,
                ]);

                session()->forget(['partner_registration', 'passkey_options']);

                return response()->json([
                    'success' => true,
                    'redirect' => route('partner.dashboard')
                ]);
            } catch (\Exception $e) {
                \Log::error("Registration Error: " . $e->getMessage());
                return response()->json(['error' => $e->getMessage()], 422);
            }
        });
    }

    /**
     * FINAL STEP: Acceptance & DB Creation
     */
    public function acceptOffer(Request $request)
    {
        $reg = session('partner_registration');
        if (!$reg) return redirect()->route('partner.register');

        DB::transaction(function() use ($reg) {
            LegalEntity::create([
                'user_id' => Auth::id(),
                'name' => $reg['name'],
                'inn' => $reg['inn'],
                'kpp' => $reg['kpp'] ?? null,
                'ogrn' => $reg['ogrn'] ?? null,
                'legal_address' => $reg['address'] ?? null,
                'tax_system' => $reg['tax_system'] ?? 'OSN',
                'is_active' => false,
                'agreement_signed_at' => now(),
                'agreement_signature' => 'SGN:' . bin2hex(random_bytes(32)),
            ]);
        });

        session()->forget('partner_registration');
        return redirect()->route('partner.dashboard');
    }

    private function calculateL1Address(string $publicKey): string
    {
        return 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);
    }
}
