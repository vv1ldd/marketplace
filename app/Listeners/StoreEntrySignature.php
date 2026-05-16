<?php

namespace App\Listeners;

use App\Models\EntrySignature;
use Spatie\LaravelPasskeys\Events\PasskeyUsedToAuthenticateEvent;

class StoreEntrySignature
{
    public function handle(PasskeyUsedToAuthenticateEvent $event): void
    {
        $request = $event->request;
        $passkey = $event->passkey;

        // 🛡️ Calculate L1 Address from Public Key stored in Passkey data
        $publicKey = $passkey->data->credentialPublicKey ?? '';
        $l1Address = 'sl1_' . substr(hash('sha256', $publicKey), 0, 40);

        EntrySignature::create([
            'user_id' => $passkey->authenticatable_id,
            'passkey_id' => $passkey->id,
            'l1_address' => $l1Address,
            'assertion' => json_decode($request->input('start_authentication_response'), true),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'signed_at' => now(),
        ]);

        // 🏛️ Sovereign Ledger: Record the Entry Intent
        $ledgerEntry = app(\App\Services\LedgerService::class)->record(
            shop: null,
            eventType: 'IDENTITY_ENTRY_INTENT',
            entity: $passkey,
            payload: [
                'intent' => 'SYSTEM_ACCESS',
                'l1_address' => $l1Address,
                'assertion_id' => $passkey->credential_id,
            ],
            legalEntity: $passkey->authenticatable->managedLegalEntities()->latest()->first(),
            triggerSource: "DID:PASSKEY:{$l1Address}",
            inputData: [
                'assertion' => json_decode($request->input('start_authentication_response'), true),
                'intent_entropy' => json_decode($request->input('intent_entropy'), true),
            ]
        );

        // ⚓ Issue Sovereign Mandate for this session
        session(['sovereign_mandate_id' => $ledgerEntry->id]);
        session(['sovereign_mandate_hash' => $ledgerEntry->fingerprint]);
    }
}
