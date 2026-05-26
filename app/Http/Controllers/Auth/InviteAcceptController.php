<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\StaffInviteEmail;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\LaravelPasskeys\Actions\GeneratePasskeyRegisterOptionsAction;
use Spatie\LaravelPasskeys\Actions\StorePasskeyAction;

class InviteAcceptController extends Controller
{
    /**
     * Show the invite acceptance page.
     */
    public function show(string $token)
    {
        $intent = Cache::get("intent:{$token}");

        if (!$intent || data_get($intent, 'type') !== 'workspace_invite') {
            return view('auth.accept-invite', [
                'error' => 'Ссылка приглашения недействительна или срок её действия истёк.',
                'token' => $token,
                'intent' => null,
            ]);
        }

        $roleLabel = match($intent['role'] ?? 'manager') {
            'admin'   => 'Администратор',
            'manager' => 'Менеджер',
            'viewer'  => 'Наблюдатель',
            'support' => 'Поддержка',
            default   => ucfirst($intent['role'] ?? 'Участник'),
        };

        return view('auth.accept-invite', [
            'error'      => null,
            'token'      => $token,
            'intent'     => $intent,
            'roleLabel'  => $roleLabel,
            'partnerName' => $intent['partner_name'] ?? 'Компания',
            'inviteeEmail' => $intent['invitee_email'] ?? null,
            'inviteeName' => $intent['invitee_name'] ?? null,
        ]);
    }

    /**
     * Step 1: Get passkey registration options for the invited user.
     */
    public function options(Request $request, string $token)
    {
        $intent = Cache::get("intent:{$token}");

        if (!$intent || data_get($intent, 'type') !== 'workspace_invite') {
            return response()->json(['error' => 'Недействительный или истёкший токен приглашения.'], 422);
        }

        $request->validate(['email' => 'required|email']);
        $email = $request->input('email');

        // If the intent had a specific email — enforce it
        if (!empty($intent['invitee_email']) && strtolower($intent['invitee_email']) !== strtolower($email)) {
            return response()->json(['error' => 'Email не совпадает с приглашением.'], 422);
        }

        $user = User::findByEmail($email) ?? User::create([
            'first_name'              => $request->input('name') ?: explode('@', $email)[0],
            'email'                   => $email,
            'password'                => Hash::make(Str::random(32)),
            'password_login_enabled'  => false,
        ]);

        Auth::login($user);

        $json = app(GeneratePasskeyRegisterOptionsAction::class)->execute($user, true);
        $optionsArray = json_decode($json, true);
        $optionsArray['rp']['id'] = $request->getHost();

        session(['passkey_options' => json_encode($optionsArray)]);
        session(['invite_token'    => $token]);
        session(['invite_email'    => $email]);

        return response()->json([
            'options'   => $optionsArray,
            'new_csrf'  => csrf_token(),
        ]);
    }

    /**
     * Step 2: Store passkey, anchor L1, attach user to workspace.
     */
    public function accept(Request $request, string $token)
    {
        $intent = Cache::get("intent:{$token}");

        if (!$intent || data_get($intent, 'type') !== 'workspace_invite') {
            return response()->json(['error' => 'Недействительный или истёкший токен приглашения.'], 422);
        }

        $user = Auth::user();
        $optionsJson = session('passkey_options');

        if (!$user || !$optionsJson) {
            return response()->json(['error' => 'Сессия истекла. Пожалуйста, начните заново.'], 422);
        }

        return DB::transaction(function () use ($user, $request, $optionsJson, $intent, $token) {
            try {
                // 1. Store passkey
                $passkey = app(StorePasskeyAction::class)->execute(
                    $user,
                    $request->input('passkey_attestation'),
                    $optionsJson,
                    $request->getHost(),
                    ['name' => 'Primary Sovereign Identity']
                );

                // 2. Anchor L1 address from the passkey public key
                $l1Address = app(\App\Services\L1IdentityService::class)->bindUserToPasskey($user, $passkey);

                $entityId = $intent['partner_id'];
                $role     = $intent['role'];

                // 3. Assign roles
                Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'web']);
                $sellerRole = Role::firstOrCreate(['name' => 'b2b_partner', 'guard_name' => 'sellers']);
                $user->assignRole('b2b_partner');

                // 4. Attach to LegalEntity managers
                $user->managedLegalEntities()->syncWithoutDetaching([
                    $entityId => ['role' => in_array($role, ['admin']) ? 'admin' : 'manager'],
                ]);

                // 5. Create or find Seller record
                $seller = Seller::findByEmail($user->email);
                if (!$seller) {
                    $seller = Seller::create([
                        'first_name' => $user->first_name ?: explode('@', $user->email)[0],
                        'last_name'  => $user->last_name ?: '',
                        'email'      => $user->email,
                        'password'   => $user->password,
                        'is_active'  => true,
                    ]);
                }
                $seller->assignRole($sellerRole);

                $seller->managedLegalEntities()->syncWithoutDetaching([
                    $entityId => ['role' => $role, 'user_id' => $user->id],
                ]);

                // 6. Clear invite intent
                Cache::forget("intent:{$token}");
                session()->forget(['passkey_options', 'invite_token', 'invite_email']);

                // 7. Log in both guards
                Auth::login($user);
                Auth::guard('sellers')->login($seller);

                // 8. Notify the partner (optional: send welcome notification)
                try {
                    $entity = \App\Models\LegalEntity::find($entityId);
                    if ($entity) {
                        app(\App\Services\LedgerService::class)->record(
                            shop: null,
                            eventType: 'STAFF_INVITE_ACCEPTED',
                            entity: $user,
                            payload: [
                                'invitee_email' => $user->email,
                                'role'          => $role,
                                'l1_address'    => $l1Address,
                                'partner_id'    => $entityId,
                            ],
                            legalEntity: $entity,
                            triggerSource: "DID:INVITE:{$l1Address}",
                            inputData: ['token' => $token]
                        );
                    }
                } catch (\Exception $e) {
                    \Log::warning('Ledger record failed on invite accept: ' . $e->getMessage());
                }

                return response()->json([
                    'success'  => true,
                    'redirect' => route('partner.dashboard'),
                ]);

            } catch (\Exception $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            }
        });
    }
}
