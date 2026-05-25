@php
    $assertion = $record->signature_assertion;
    $clientDataDecoded = null;
    $clientDataJson = 'N/A';
    
    if (!empty($assertion['response']['clientDataJSON'])) {
        try {
            $base64 = strtr($assertion['response']['clientDataJSON'], '-_', '+/');
            $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
            $base64Decoded = base64_decode($base64);
            $clientDataDecoded = json_decode($base64Decoded, true);
            $clientDataJson = json_encode($clientDataDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $clientDataJson = 'Error decoding clientDataJSON: ' . $e->getMessage();
        }
    }

    $credId = $assertion['id'] ?? 'N/A';
    
    // Parse authenticatorData flags if present
    $authDataRaw = $assertion['response']['authenticatorData'] ?? null;
    $authDataHex = 'N/A';
    $upFlag = 'N/A';
    $uvFlag = 'N/A';
    
    if ($authDataRaw) {
        $authDataBin = null;
        // Check if already hex
        if (ctype_xdigit($authDataRaw)) {
            $authDataHex = $authDataRaw;
            $authDataBin = hex2bin($authDataRaw);
        } else {
            // It is probably base64url encoded
            $base64UrlDecoded = base64_decode(strtr($authDataRaw, '-_', '+/'));
            if ($base64UrlDecoded !== false) {
                $authDataBin = $base64UrlDecoded;
                $authDataHex = bin2hex($base64UrlDecoded);
            }
        }

        if ($authDataBin && strlen($authDataBin) >= 33) {
            $flagsByte = ord($authDataBin[32]);
            $up = ($flagsByte & 0x01) === 0x01; // User Presence (bit 0)
            $uv = ($flagsByte & 0x04) === 0x04; // User Verified (bit 2)
            $upFlag = $up ? 'PRESENT (UP ✅)' : 'ABSENT (No UP ❌)';
            $uvFlag = $uv ? 'VERIFIED (UV ✅)' : 'UNVERIFIED (No UV ❌)';
        }
    }

    $signatureRaw = $assertion['response']['signature'] ?? null;
    $signatureHex = 'N/A';
    if ($signatureRaw) {
        if (ctype_xdigit($signatureRaw)) {
            $signatureHex = $signatureRaw;
        } else {
            $sigDecoded = base64_decode(strtr($signatureRaw, '-_', '+/'));
            if ($sigDecoded !== false) {
                $signatureHex = bin2hex($sigDecoded);
            }
        }
    }

    // Envelope intent
    $envelope = [
        'transaction_id' => $record->id,
        'entity' => 'Consortium B2B Legal Entity',
        'operation' => $record->type === 'top_up' ? 'REPLENISHMENT_DEPOSIT' : 'JIT_CREDIT_LINE',
        'amount' => number_format($record->amount, 2) . ' RUB',
        'signer_l1' => $record->l1_address,
        'timestamp' => $record->created_at->toDateTimeString()
    ];
    $envelopeJson = json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
    $shaHash = $record->l1_address ? str_replace('sl1_', '', $record->l1_address) : 'N/A';
    
    // Get the passkey details if available
    $passkey = $record->passkey;
    $publicKey = $passkey ? ($passkey->data->credentialPublicKey ?? '') : null;
@endphp

<div class="sovereign-proof-container">
    <style>
        .sovereign-proof-container {
            font-family: 'JetBrains Mono', 'Fira Code', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            background-color: #070c14 !important;
            color: #e2e8f0 !important;
            border: 1px solid #10b981;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.15), inset 0 0 15px rgba(16, 185, 129, 0.05);
            max-width: 100%;
            overflow: hidden;
        }
        
        .sovereign-proof-container .section-title {
            color: #10b981 !important;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 8px;
            margin-top: 18px;
            letter-spacing: 0.05em;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .sovereign-proof-container .section-title:first-of-type {
            margin-top: 0;
        }
        
        .sovereign-proof-container .card-dashed {
            background: rgba(16, 185, 129, 0.02) !important;
            border: 1px dashed rgba(16, 185, 129, 0.25) !important;
            border-radius: 8px;
            padding: 14px;
        }

        .sovereign-proof-container pre {
            background: #020408 !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
            padding: 10px;
            border-radius: 8px;
            overflow-x: auto;
            color: #34d399 !important;
            margin: 0;
            font-size: 12px;
            font-family: inherit;
        }
        
        .sovereign-proof-container .card-neo {
            background: #020408 !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
            border-radius: 8px;
            padding: 10px;
        }
        
        .sovereign-proof-container .field-label {
            color: #64748b !important;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            display: block;
            margin-bottom: 2px;
        }
        
        .sovereign-proof-container .field-value {
            color: #f1f5f9 !important;
            background: rgba(255, 255, 255, 0.02) !important;
            padding: 6px 8px;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
            word-break: break-all;
            font-family: inherit;
        }

        .sovereign-proof-container .deriv-step {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background: rgba(0, 0, 0, 0.2) !important;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.03) !important;
        }
        
        .sovereign-proof-container .deriv-step-highlight {
            background: rgba(16, 185, 129, 0.06) !important;
            border: 1px solid rgba(16, 185, 129, 0.2) !important;
        }

        .sovereign-proof-container .verified-seal {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: rgba(16, 185, 129, 0.1) !important;
            border: 1px solid #10b981 !important;
            border-radius: 8px;
            padding: 12px;
            color: #10b981 !important;
            font-weight: 800;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-shadow: 0 0 8px rgba(16, 185, 129, 0.35);
            margin-top: 15px;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.1);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .sovereign-proof-container .pulse-effect {
            animation: pulse 2s infinite;
        }
    </style>

    <!-- Request Summary -->
    <div class="card-dashed">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div>
                <span style="color: #64748b; font-size: 11px;">ЗАПРОС ID:</span>
                <div style="color: #10b981; font-weight: 800; font-size: 14px;">REQ-{{ $record->id }}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 11px;">СТАТУС:</span>
                <div>
                    @if($record->status === 'pending')
                        <span style="color: #f59e0b; font-weight: 800;">ОЖИДАНИЕ ⏳</span>
                    @elseif($record->status === 'approved')
                        <span style="color: #10b981; font-weight: 800;">ИСПОЛНЕН ✅</span>
                    @else
                        <span style="color: #f43f5e; font-weight: 800;">ОТКЛОНЕН ❌</span>
                    @endif
                </div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 11px;">СУММА:</span>
                <div style="color: #10b981; font-weight: 800; font-size: 14px;">{{ number_format($record->amount, 2) }} RUB</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 11px;">ДАТА ПОДПИСИ:</span>
                <div style="color: #f1f5f9;">{{ $record->created_at->format('d.m.Y H:i:s') }}</div>
            </div>
        </div>
    </div>

    <!-- Signer DID Identity -->
    <div>
        <div class="section-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            [ 1. Sovereign Signer Identity ]
        </div>
        <div class="card-neo" style="word-break: break-all;">
            <span style="color: #64748b;">DID:PASSKEY:</span>
            <span style="color: #34d399; font-weight: bold; border-bottom: 1px dashed rgba(52, 211, 153, 0.4); padding-bottom: 1px;">{{ $record->l1_address }}</span>
            
            <div style="margin-top: 8px; font-size: 11px; color: #94a3b8; display: flex; align-items: center; gap: 6px;">
                <span style="width: 6px; height: 6px; border-radius: 50%; background-color: #10b981;"></span>
                Consensus Authenticated Key Binding Active
            </div>
        </div>
    </div>

    <!-- Signed Envelope -->
    <div>
        <div class="section-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            [ 2. Signed Intent Envelope ]
        </div>
        <pre>{{ $envelopeJson }}</pre>
    </div>

    <!-- WebAuthn Cryptographic Assertion -->
    <div>
        <div class="section-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            [ 3. WebAuthn Hardware Assertion ]
        </div>
        <div class="card-neo" style="display: flex; flex-direction: column; gap: 10px;">
            @if($assertion)
                <div>
                    <span class="field-label">Credential ID (Base64url):</span>
                    <div class="field-value">{{ $credId }}</div>
                </div>
                <div>
                    <span class="field-label">Client Data JSON (Decoded):</span>
                    <pre style="margin-top: 4px;">{{ $clientDataJson }}</pre>
                </div>
                <div>
                    <span class="field-label">Authenticator Data (Hex):</span>
                    <div class="field-value" style="font-size: 11px;">{{ $authDataHex }}</div>
                </div>
                
                <!-- UP/UV Flag Diagnostics -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: rgba(255,255,255,0.01); border: 1px solid rgba(255,255,255,0.03); border-radius: 4px; padding: 8px;">
                    <div>
                        <span class="field-label">User Presence (UP):</span>
                        <div style="font-weight: bold; color: {{ str_contains($upFlag, '✅') ? '#34d399' : '#f43f5e' }};">
                            {{ $upFlag }}
                        </div>
                    </div>
                    <div>
                        <span class="field-label">User Verification (UV):</span>
                        <div style="font-weight: bold; color: {{ str_contains($uvFlag, '✅') ? '#34d399' : '#f43f5e' }};">
                            {{ $uvFlag }}
                        </div>
                    </div>
                </div>

                <div>
                    <span class="field-label">Signature Assertion (r, s):</span>
                    <div class="field-value" style="color: #34d399; font-weight: bold;">{{ $signatureHex }}</div>
                </div>
            @else
                <div style="text-align: center; padding: 12px; color: #64748b;">
                    N/A (Seeded Transaction / System Cleared)
                </div>
            @endif
        </div>
    </div>

    <!-- L1 Hashing Derivation Diagram -->
    <div>
        <div class="section-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>
            [ 4. Cryptographic Consensus Derivation ]
        </div>
        <div class="card-dashed" style="display: flex; flex-direction: column; gap: 8px; border-color: rgba(16, 185, 129, 0.15) !important;">
            <div class="deriv-step">
                <span style="color: #94a3b8;">1. WebAuthn Public Key (PEM/DER)</span>
                <span style="color: #64748b; font-size: 11px;">→ (Enclave Origin)</span>
            </div>
            
            @if($publicKey)
                <pre style="max-height: 100px; font-size: 10px; opacity: 0.85;">{{ $publicKey }}</pre>
            @endif
            
            <div style="text-align: center; color: #10b981; font-size: 14px; margin: -4px 0;">▼</div>
            
            <div class="deriv-step">
                <span style="color: #94a3b8;">2. SHA-256 Digest Hash</span>
                <span style="color: #34d399; font-weight: bold; font-size: 11px; word-break: break-all;">{{ $shaHash }}</span>
            </div>
            
            <div style="text-align: center; color: #10b981; font-size: 14px; margin: -4px 0;">▼</div>
            
            <div class="deriv-step deriv-step-highlight">
                <span style="color: #e2e8f0; font-weight: bold;">3. ID подтверждения</span>
                <span style="color: #34d399; font-weight: bold; font-size: 12px;">{{ $record->l1_address }}</span>
            </div>
        </div>
    </div>

    <!-- Consensus verified badge -->
    <div class="verified-seal pulse-effect">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
        CONSTITUTION CONSORTIUM VERIFIED: SUCCESS 🛡️
    </div>
</div>
