<div class="space-y-4 text-left" style="width: 100%; display: flex; flex-direction: column; gap: 1.25rem;">
    <style>
        .passkey-label {
            font-size: 11px;
            font-weight: 700;
            color: #8e8e93;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
            display: block;
        }
        .passkey-input-wrapper {
            position: relative;
            width: 100%;
            display: flex;
            align-items: center;
        }
        .passkey-input-icon {
            position: absolute;
            left: 14px;
            width: 16px;
            height: 16px;
            color: #8e8e93;
            pointer-events: none;
            display: inline-block;
        }
        .passkey-input {
            width: 100% !important;
            height: 44px !important;
            padding: 0 1rem 0 2.5rem !important;
            background-color: rgba(255, 255, 255, 0.02) !important;
            border: 1px solid rgba(255, 255, 255, 0.05) !important;
            border-radius: 10px !important;
            color: #ffffff !important;
            font-family: inherit !important;
            font-size: 13.5px !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }
        .passkey-input:focus {
            border-color: #f53003 !important;
            background-color: rgba(255, 255, 255, 0.04) !important;
            box-shadow: 0 0 0 1px #f53003 !important;
        }
        .passkey-submit-btn {
            width: 100% !important;
            height: 44px !important;
            background: linear-gradient(135deg, #f53003 0%, #ff7b00 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            color: #ffffff !important;
            font-size: 13.5px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 0.5rem !important;
            transition: all 0.2s ease !important;
        }
        .passkey-submit-btn:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 15px rgba(245, 48, 3, 0.4) !important;
        }
        .passkey-submit-btn:active {
            transform: translateY(1px) !important;
        }
        .saved-passkey-item {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1rem;
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .saved-passkey-item:hover {
            border-color: rgba(255, 255, 255, 0.12);
            background-color: rgba(255, 255, 255, 0.04);
        }

        /* --- Theme overrides: Partner --- */
        body[data-theme="partner"] .passkey-label {
            color: #9a9ab0;
        }
        body[data-theme="partner"] .passkey-input {
            background-color: rgba(255, 255, 255, 0.01) !important;
            border: 1px solid rgba(255, 255, 255, 0.04) !important;
        }
        body[data-theme="partner"] .passkey-input:focus {
            border-color: #ff9f0a !important;
            box-shadow: 0 0 0 1px #ff9f0a !important;
        }
        body[data-theme="partner"] .passkey-submit-btn {
            background: #ff9f0a !important;
            color: #000000 !important;
            box-shadow: 0 4px 15px rgba(255, 159, 10, 0.3) !important;
        }
        body[data-theme="partner"] .passkey-submit-btn:hover {
            box-shadow: 0 6px 20px rgba(255, 159, 10, 0.45) !important;
        }

        /* --- Theme overrides: Retro (Neobrutalism) --- */
        body[data-theme="retro"] .passkey-label {
            color: #000000;
            font-weight: 800;
        }
        body[data-theme="retro"] .passkey-input-icon {
            color: #000000;
        }
        body[data-theme="retro"] .passkey-input {
            background-color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            color: #000000 !important;
            font-weight: 700 !important;
        }
        body[data-theme="retro"] .passkey-input:focus {
            border-color: #7c3aed !important;
            box-shadow: 2px 2px 0px #000000 !important;
        }
        body[data-theme="retro"] .passkey-submit-btn {
            background: #7c3aed !important;
            color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 4px 4px 0px #000000 !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] .passkey-submit-btn:hover {
            transform: translate(-2px, -2px) !important;
            box-shadow: 6px 6px 0px #000000 !important;
        }
        body[data-theme="retro"] .passkey-submit-btn:active {
            transform: translate(0, 0) !important;
            box-shadow: 2px 2px 0px #000000 !important;
        }
        body[data-theme="retro"] .saved-passkey-item {
            background-color: #ffffff !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
            box-shadow: 3px 3px 0px #000000 !important;
        }
        body[data-theme="retro"] .saved-passkey-item h4 {
            color: #000000 !important;
            font-weight: 800 !important;
        }
        body[data-theme="retro"] .saved-passkey-item p {
            color: #4e4e5e !important;
            font-weight: 600 !important;
        }
        body[data-theme="retro"] .saved-passkey-icon-box {
            background-color: rgba(124, 58, 237, 0.1) !important;
            border: 2px solid #000000 !important;
            border-radius: 0px !important;
        }
        body[data-theme="retro"] .qr-box {
            background: #ffffff !important;
            border: 2px solid #000000 !important;
            box-shadow: 4px 4px 0px #000000 !important;
            border-radius: 0px !important;
        }
    </style>

    <!-- Form to Create Passkey (Only shown if no passkeys exist yet) -->
    @if($passkeys->isEmpty())
        <form id="passkeyForm" wire:submit="validatePasskeyProperties" style="width: 100%; display: flex; flex-direction: column; gap: 1.25rem; margin: 0;">
            <div style="width: 100%;">
                <label for="name" class="passkey-label">
                    Название ключа
                </label>
                <div class="passkey-input-wrapper">
                    <svg class="passkey-input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <input autocomplete="off" type="text" wire:model="name" placeholder="Название ключа (например: MacBook Pro)" class="passkey-input">
                </div>
                @error('name')
                    <span style="font-size: 12px; color: #ef4444; margin-top: 0.25rem; display: block;">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="passkey-submit-btn">
                <svg style="width: 16px; height: 16px; display: inline-block; stroke-width: 2.5;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                Создать ключ доступа
            </button>
        </form>
    @endif

    <!-- List of Existing Passkeys -->
    @if($passkeys->isNotEmpty())
        <div style="border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 1.25rem; width: 100%;">
            <h4 class="passkey-label" style="margin-bottom: 0.75rem;">
                Сохраненные ключи
            </h4>
            <div style="display: flex; flex-direction: column; gap: 0.75rem; width: 100%;">
                @foreach($passkeys as $passkey)
                    <div class="saved-passkey-item">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div class="saved-passkey-icon-box" style="background-color: rgba(34, 197, 94, 0.08); width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <svg style="width: 16px; height: 16px; display: inline-block; color: #4ade80;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div style="text-align: left;">
                                <h4 style="font-size: 13px; font-weight: 600; color: #ffffff; margin: 0;">{{ $passkey->name }}</h4>
                                <p style="font-size: 10px; color: #666666; margin: 0.125rem 0 0 0;">
                                    Активен • {{ $passkey->last_used_at?->diffForHumans() ?? 'Еще не использовался' }}
                                </p>
                            </div>
                        </div>
                        <button wire:click="deletePasskey({{ $passkey->id }})" 
                            style="background: transparent; border: none; cursor: pointer; border-radius: 6px; padding: 6px; color: #4b5563; display: flex; align-items: center; justify-content: center; transition: background-color 0.2s, color 0.2s;"
                            onmouseover="this.style.backgroundColor='rgba(239, 68, 68, 0.1)'; this.style.color='#ef4444';"
                            onmouseout="this.style.backgroundColor='transparent'; this.style.color='#4b5563';"
                            title="Удалить ключ">
                            <svg style="width: 16px; height: 16px; display: inline-block;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- QR Code for Mobile Linkage -->
    <div style="margin-top: 0.5rem; padding-top: 1.25rem; border-top: 1px dashed rgba(255, 255, 255, 0.08); text-align: center; display: flex; flex-direction: column; align-items: center; width: 100%;">
        <div style="display: flex; align-items: center; gap: 0.35rem; margin-bottom: 0.75rem;">
            <svg style="width: 14px; height: 14px; color: #666666; display: inline-block;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <span class="passkey-label" style="margin-bottom: 0;">Связать с телефоном</span>
        </div>
        
        <div class="qr-box" style="background: #ffffff; padding: 8px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4); cursor: pointer; transition: transform 0.2s;" 
             onmouseover="this.style.transform='scale(1.03)';"
             onmouseout="this.style.transform='none';">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&color=050505&data={{ urlencode(request()->fullUrl()) }}" 
                 style="width: 120px; height: 120px; display: block; border-radius: 4px;" 
                 alt="QR для мобильного">
        </div>
        
        <span style="font-size: 10px; color: #555555; margin-top: 0.65rem; max-width: 190px; line-height: 1.4; display: block;">
            Сканируйте камерой телефона для входа и добавления Touch ID / Face ID
        </span>
    </div>
</div>

@include('passkeys::livewire.partials.createScript')

<script>
    // Bulletproof native script to auto-detect and pre-fill input
    (function() {
        function fillPasskeyName() {
            const input = document.querySelector('input[wire\\:model="name"]');
            if (input && !input.value) {
                const ua = navigator.userAgent;
                let browser = "Браузер";
                if (ua.indexOf("Chrome") > -1) browser = "Chrome";
                else if (ua.indexOf("Safari") > -1) browser = "Safari";
                else if (ua.indexOf("Firefox") > -1) browser = "Firefox";
                else if (ua.indexOf("Edge") > -1) browser = "Edge";
 
                let os = "Устройство";
                if (ua.indexOf("Mac") > -1) os = "macOS";
                else if (ua.indexOf("Win") > -1) os = "Windows";
                else if (ua.indexOf("Linux") > -1) os = "Linux";
                else if (ua.indexOf("Android") > -1) os = "Android";
                else if (ua.indexOf("iPhone") > -1 || ua.indexOf("iPad") > -1) os = "iOS";
 
                const defaultName = `${os} (${browser}) - ${new Date().toLocaleDateString('ru-RU')}`;
                input.value = defaultName;
                input.dispatchEvent(new Event('input'));
            }
        }
 
        // Run immediately, on DOM load, and on Livewire updates
        setTimeout(fillPasskeyName, 100);
        document.addEventListener('DOMContentLoaded', fillPasskeyName);
        document.addEventListener('livewire:init', fillPasskeyName);
        document.addEventListener('livewire:navigated', fillPasskeyName);
        window.addEventListener('load', fillPasskeyName);
    })();
</script>
