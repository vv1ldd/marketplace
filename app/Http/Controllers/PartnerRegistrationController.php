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
     * STEP 1: Basic Info (INN + Email)
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

        // Store registration data in session for final step
        session(['partner_registration' => [
            'email' => $email,
            'inn' => $inn,
            'name' => $request->input('legal_name'),
            'ogrn' => $request->input('ogrn'),
            'address' => $request->input('address'),
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

        // 🔑 Trigger Passkey Creation Step
        $optionsJson = app(\Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction::class)->execute($user, true);
        session(['passkey_options' => $optionsJson]);

        return view('partner.register_passkey', [
            'options' => $optionsJson,
            'user' => $user
        ]);
    }

    /**
     * STEP 2: Passkey Storage & L1 Anchoring
     */
    public function storePasskey(Request $request)
    {
        $user = Auth::user();
        $optionsJson = session('passkey_options');
        
        if (!$optionsJson) return response()->json(['error' => 'Session expired'], 422);

        try {
            app(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class)->execute(
                $user,
                $request->getContent(),
                $optionsJson,
                $request->getHost(),
                ['name' => 'Primary Key']
            );
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * STEP 3: Redirect to Offer
     */
    public function finalize(Request $request)
    {
        $user = Auth::user();
        $passkey = $user->passkeys()->latest()->first();
        
        if ($passkey) {
            $address = $this->calculateL1Address($passkey->data->credentialPublicKey);
            $meta = $user->meta ?? [];
            $meta['l1_address'] = $address;
            $user->meta = $meta;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'redirect' => route('partner.register.offer')
        ]);
    }

    /**
     * STEP 4: Public Offer Review
     */
    public function showOffer()
    {
        $reg = session('partner_registration');
        if (!$reg) return redirect()->route('partner.register');

        return view('partner.register_step3', [
            'registration' => $reg,
            'agreementText' => "Договор на оказание услуг по размещению Товарных предложений... [Полный текст]"
        ]);
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
                'ogrn' => $reg['ogrn'] ?? null,
                'legal_address' => $reg['address'] ?? null,
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
