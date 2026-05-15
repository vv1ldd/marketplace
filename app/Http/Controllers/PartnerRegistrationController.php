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

    public function register(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->input('email');
        $user = User::findByEmail($email);

        if ($user && $user->passkeys()->exists()) {
            return back()->withErrors(['email' => 'Этот email уже зарегистрирован. Пожалуйста, войдите.']);
        }

        if (!$user) {
            $user = DB::transaction(function () use ($request, $email) {
                $user = User::create([
                    'first_name' => explode('@', $email)[0], // Use email prefix as default name
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                ]);

                $user->assignRole('b2b_partner');
                
                return $user;
            });
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

    public function storePasskey(Request $request)
    {
        $user = Auth::user();
        $optionsJson = session('passkey_options');
        
        if (!$optionsJson) {
            return response()->json(['error' => 'Session expired'], 422);
        }

        try {
            // Force RP ID to current host if it's different from APP_URL host
            // Note: Spatie doesn't make it easy to override RP ID on the fly without a custom Action.
            // But we can verify it in the session or log it.
            
            app(\Spatie\LaravelPasskeys\Actions\StorePasskeyAction::class)->execute(
                $user,
                $request->getContent(), // The raw credential JSON
                $optionsJson,
                $request->getHost(),
                ['name' => 'Primary Key']
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('Passkey storage failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function finalize(Request $request)
    {
        $user = Auth::user();
        
        $passkey = $user->passkeys()->latest()->first();
        
        if ($passkey) {
            // 🧬 Simple-L1: Calculate Address from Public Key
            // $passkey->data is a PublicKeyCredentialSource object
            $publicKey = $passkey->data->credentialPublicKey;
            $address = $this->calculateL1Address($publicKey);
            
            $meta = $user->meta ?? [];
            $meta['l1_address'] = $address;
            $user->meta = $meta;
            $user->save();

            // 🚀 Sync with Simple-L1 Node (Production Cloud)
            try {
                \Illuminate\Support\Facades\Http::timeout(3)->post('https://l1.wildflow.dev/accounts', [
                    'address' => $address,
                    'publicKey' => bin2hex($publicKey),
                    'credentialId' => $passkey->credential_id,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Simple-L1 Sync failed: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'redirect' => route('partner.register.step2')]);
    }

    public function showStep2()
    {
        return view('partner.register_step2');
    }

    public function storeStep2(Request $request)
    {
        $data = $request->validate([
            'legal_name' => 'required|string|max:255',
            'inn' => 'required|string|max:20',
        ]);

        // Check if this INN is already registered (using Blind Index for encrypted search)
        $bidx = app(\App\Services\VaultTransitService::class)->computeBlindIndex($data['inn']);
        $existing = LegalEntity::where('inn_bidx', $bidx)->first();
        
        if ($existing) {
            return back()->withErrors(['inn' => 'Организация с таким ИНН уже зарегистрирована.'])->withInput();
        }

        $user = Auth::user();

        $legalEntity = LegalEntity::create([
            'user_id' => $user->id,
            'name' => $data['legal_name'],
            'inn' => $data['inn'],
            'is_active' => true,
            'currency' => 'RUB',
        ]);

        return redirect('/partner');
    }

    private function calculateL1Address(string $publicKey): string
    {
        // Based on Simple-L1 RFC: SHA256 of compressed EC P-256 public key
        // Note: Spatie stores public_key in some format, we need to extract X, Y
        // This is a placeholder for the logic from demo.js translated to PHP
        
        // For now, let's just generate a hash-based address as a placeholder
        // until we precisely map the Spatie public_key format.
        return 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);
    }
}
