<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --amber: #f59e0b;
            --amber-light: #fcd34d;
            --bg: #080b10;
            --bg-card: #0f1420;
            --border: rgba(255,255,255,0.07);
            --text: #f1f5f9;
            --muted: #64748b;
            --muted2: #94a3b8;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        nav {
            padding: 1.5rem 2rem;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(8, 11, 16, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }
        .logo { font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px; color: var(--amber); text-decoration: none; }
        .logo span { color: var(--text); }

        .auth-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 1.5rem;
            position: relative;
        }

        .auth-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 2.5rem;
            border-radius: 24px;
            width: 100%;
            max-width: 440px;
            z-index: 1;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        .auth-header { text-align: center; margin-bottom: 2rem; }
        .auth-header h1 { font-size: 1.75rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 0.5rem; }
        .auth-header p { color: var(--muted2); font-size: 0.9rem; }

        .form-group { margin-bottom: 1.25rem; }
        .form-label { 
            display: block; font-size: 0.85rem; font-weight: 600; 
            color: var(--muted2); margin-bottom: 0.5rem; 
        }
        .form-input {
            width: 100%;
            height: 52px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: var(--text);
            padding: 0 1rem;
            font-family: inherit;
            font-size: 1rem;
            transition: all 0.2s;
            outline: none;
        }
        .form-input:focus {
            border-color: var(--amber);
            background: rgba(255,255,255,0.06);
            box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
        }

        .btn-submit {
            width: 100%;
            height: 52px;
            background: var(--amber);
            color: #000;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 1rem;
        }
        .btn-submit:hover {
            background: var(--amber-light);
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(245,158,11,0.3);
        }

        .auth-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--muted2);
        }
        .auth-footer a { color: var(--amber); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

<nav>
    <a href="/" class="logo">Mean<span>ly</span></a>
</nav>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <h1>Регистрация бизнеса</h1>
            @if($brand)
                <div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); padding: 4px 12px; border-radius: 20px; margin-top: 1rem; margin-bottom: 0.5rem;">
                    <div style="width: 8px; height: 8px; background: #10b981; border-radius: 50%; box-shadow: 0 0 8px #10b981;"></div>
                    <span style="font-size: 0.7rem; font-weight: 800; color: #10b981; letter-spacing: 0.5px;">{{ strtoupper($brand->name) }} COMPLIANCE DOMAIN</span>
                </div>
            @endif
            <p id="perimeter-desc">Определите вашу юрисдикцию для входа в легальный периметр.</p>
        </div>

        @if($errors->any())
            <div style="color: #f87171; font-size: 0.85rem; margin-bottom: 1rem; text-align: center;">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('partner.register.submit') }}" method="POST" id="registration-form">
            @csrf
            @if($brand)
                <input type="hidden" name="brand_id" value="{{ $brand->id }}">
            @endif
            
            <!-- 🌍 Jurisdiction Selection -->
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 0.5rem;">
                    <label class="form-label" style="margin-bottom: 0;">Юрисдикция / Jurisdiction</label>
                    @if(isset($detectedCountryName) && $detectedCountry !== 'RU')
                        <span style="font-size: 0.65rem; color: var(--amber); font-weight: 600;">📍 Вы в: {{ $detectedCountryName }}</span>
                    @endif
                </div>
                <select name="jurisdiction" id="jurisdiction" class="form-input" style="height: 44px; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto;">
                    @php $dc = $detectedCountry ?? 'RU'; @endphp
                    @if(!$supportedJurisdictions || in_array('RU', $supportedJurisdictions))
                        <option value="RU" {{ $dc === 'RU' ? 'selected' : '' }}>🇷🇺 Россия (ИНН)</option>
                    @endif
                    @if(!$supportedJurisdictions || in_array('KZ', $supportedJurisdictions))
                        <option value="KZ" {{ $dc === 'KZ' ? 'selected' : '' }}>🇰🇿 Казахстан (БИН)</option>
                    @endif
                    @if(!$supportedJurisdictions || in_array('BY', $supportedJurisdictions))
                        <option value="BY" {{ $dc === 'BY' ? 'selected' : '' }}>🇧🇾 Беларусь (УНП)</option>
                    @endif
                    @if(!$supportedJurisdictions || in_array('UZ', $supportedJurisdictions))
                        <option value="UZ" {{ $dc === 'UZ' ? 'selected' : '' }}>🇺🇿 Узбекистан (ИНН)</option>
                    @endif
                    @if(!$supportedJurisdictions || in_array('AM', $supportedJurisdictions))
                        <option value="AM" {{ $dc === 'AM' ? 'selected' : '' }}>🇦🇲 Армения (ИНН/ՀՎՀՀ)</option>
                    @endif
                    @if(!$supportedJurisdictions || in_array('KG', $supportedJurisdictions))
                        <option value="KG" {{ $dc === 'KG' ? 'selected' : '' }}>🇰🇬 Кыргызстан (ИНН/ИН)</option>
                    @endif
                    @if(!$supportedJurisdictions || in_array('TM', $supportedJurisdictions))
                        <option value="TM" {{ $dc === 'TM' ? 'selected' : '' }}>🇹🇲 Туркменистан (ИНН/TIN)</option>
                    @endif
                </select>
                @if($brand)
                    <p class="mt-1 text-xs text-gray-500">Показаны регионы, поддерживаемые брендом {{ $brand->name }}</p>
                    <div id="compliance-info" style="margin-top: 8px; font-size: 0.7rem; color: var(--muted); padding: 8px; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); display: none;">
                        <!-- Dynamic compliance details -->
                    </div>
                @endif
            </div>

            <!-- PHASE 1: INN SEARCH -->
            <div id="phase-search">
                <div class="form-group">
                    <label id="inn-label" class="form-label">ИНН организации</label>
                    <div style="position: relative;">
                        <input type="text" name="inn" id="inn-field" class="form-input" placeholder="7700123456" required value="{{ old('inn') }}" autocomplete="off" style="padding-right: 50px;">
                        <button type="button" onclick="searchINN()" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: var(--amber); color: #000; border: none; width: 34px; height: 34px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        </button>
                    </div>
                </div>

                <div class="form-group" id="name-container" style="display: none; transition: all 0.3s ease; opacity: 0; margin-top: 1.5rem;">
                    <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 16px; padding: 1.5rem; text-align: center;">
                        <label class="form-label" style="color: #10b981; margin-bottom: 0.5rem; display: block;">Найдена организация:</label>
                        <input type="text" name="legal_name" id="name-field" class="form-input" readonly style="background: transparent; border: none; color: #fff; font-weight: 800; text-align: center; font-size: 1.2rem; padding: 0;">
                        
                        <div style="font-size: 0.75rem; color: #10b981; margin-top: 1rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px; margin-bottom: 1.5rem;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            VERIFIED BY DADATA
                        </div>

                        <button type="button" id="confirm-org-btn" class="btn-submit" style="background: #10b981; color: #fff;">
                            Да, это моя организация ✅
                        </button>
                    </div>
                </div>
            </div>

            <!-- PHASE 2: DETAILS (Hidden initially) -->
            <div id="phase-details" style="display: none; animation: slideDown 0.5s ease forwards;">
                <div class="form-group" style="margin-top: 1.5rem;">
                    <label class="form-label">Рабочий Email</label>
                    <input type="email" name="email" class="form-input" placeholder="ivan@company.com" required value="{{ old('email') }}">
                </div>

                <!-- 💰 Tax System -->
                <div id="tax-section" style="margin-top: 1.5rem;">
                    <label class="form-label">Система налогообложения</label>
                    <select name="tax_system" id="tax_system" class="form-input" style="height: 44px; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto;">
                        <option value="OSN">ОСНО (Общая система)</option>
                        <option value="USN">УСН (Упрощенная система)</option>
                        <option value="AUSN">АУСН (Автоматизированная)</option>
                        <option value="USN_INCOME">УСН Доходы</option>
                        <option value="NPD">НПД (Самозанятый)</option>
                    </select>
                </div>

                <!-- Fallback/IP Fields -->
                <div id="fallback-fields" style="display: none; margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                    <p id="fallback-message" style="font-size: 0.75rem; color: var(--amber); margin-bottom: 1rem;"></p>
                    <div id="manual-name-group" class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">Полное название организации</label>
                        <input type="text" name="legal_name" id="manual_legal_name" class="form-input">
                    </div>
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label">ОГРН</label>
                        <input type="text" name="ogrn" id="manual_ogrn" class="form-input">
                    </div>
                    <div class="form-group">
                        <label id="address-label" class="form-label">Юридический адрес</label>
                        <textarea name="address" id="manual_address" class="form-input" style="height: 60px;"></textarea>
                    </div>
                </div>

                <!-- 👤 Signer Authority -->
                <div class="form-section" style="margin-top: 1.5rem; padding: 1.25rem; background: rgba(255,255,255,0.03); border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <h3 style="font-size: 0.8rem; margin-bottom: 1rem; color: var(--amber); display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 1px; font-weight: 800;">
                        Полномочия подписанта
                    </h3>

                    <div class="radio-group" style="display: flex; flex-direction: column; gap: 8px;">
                        <label class="radio-option" style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; padding: 10px; border-radius: 8px; transition: background 0.2s; border: 1px solid rgba(255,255,255,0.05);">
                            <input type="radio" name="signer_role" value="ceo" checked style="margin-top: 4px;" onclick="togglePoA(false)">
                            <div>
                                <div style="font-weight: 700; font-size: 0.85rem;">Я — Руководитель компании</div>
                                <div style="font-size: 0.7rem; color: var(--muted2);">Действую на основании Устава (первое лицо)</div>
                            </div>
                        </label>

                        <label class="radio-option" style="display: flex; align-items: flex-start; gap: 12px; cursor: pointer; padding: 10px; border-radius: 8px; transition: background 0.2s; border: 1px solid rgba(255,255,255,0.05);">
                            <input type="radio" name="signer_role" value="representative" style="margin-top: 4px;" onclick="togglePoA(true)">
                            <div>
                                <div style="font-weight: 700; font-size: 0.85rem;">Действую по доверенности</div>
                                <div style="font-size: 0.7rem; color: var(--muted2);">Уполномоченный представитель организации</div>
                            </div>
                        </label>
                    </div>

                    <div id="poa-fields" style="display: none; margin-top: 1.25rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1.25rem;">
                        <div class="form-group">
                            <label class="form-label">ФИО представителя</label>
                            <input type="text" name="signer_name" id="signer_name" class="form-input" placeholder="Иванов Иван Иванович">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                            <div class="form-group">
                                <label class="form-label">Номер доверенности</label>
                                <input type="text" name="poa_number" id="poa_number" class="form-input" placeholder="№ 123/2026">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Дата выдачи</label>
                                <input type="text" name="poa_date" id="poa_date" class="form-input" placeholder="{{ date('d/m/Y') }}">
                            </div>
                        </div>
                        <div class="form-group" style="margin-top: 10px;">
                            <label class="form-label">Контактный телефон</label>
                            <input type="tel" name="signer_phone" id="signer_phone" class="form-input" placeholder="+7 (999) 000-00-00">
                        </div>
                        <div class="form-group" style="margin-top: 10px;">
                            <label class="form-label" style="color: var(--amber);">Скан-копия доверенности (PDF/JPG)</label>
                            <input type="file" name="poa_file" class="form-input" style="padding: 10px; font-size: 0.75rem; border: 1px dashed var(--amber);">
                        </div>
                    </div>
                </div>

                <div id="background-data"></div>

                <button type="submit" id="submit-btn" class="btn-submit" style="margin-top: 1.5rem; width: 100%;">
                    Продолжить вход в периметр 🛡️
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@simplewebauthn/browser@7.2.0/dist/bundle/index.umd.min.js"></script>
<script>
    const { startRegistration } = SimpleWebAuthnBrowser;
    const registrationForm = document.getElementById('registration-form');
    const submitBtn = document.getElementById('submit-btn');

    registrationForm.addEventListener('submit', async (e) => {
        // 🛑 Stop multiple submissions
        if (registrationForm.dataset.submitting === 'true') return;
        e.preventDefault();
        
        const emailInput = registrationForm.querySelector('input[name="email"]');
        const email = emailInput ? emailInput.value.trim() : '';
        
        if (!email) {
            alert('Пожалуйста, введите рабочий Email');
            return;
        }

        console.log("Starting Identity Activation for:", email);
        submitBtn.disabled = true;
        submitBtn.innerText = "Активация личности... 🛡️";

        try {
            // 1. Get Passkey Options
            const optionsRes = await fetch('/partner/register/options', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}' 
                },
                body: JSON.stringify({ email: email })
            });
            
            if (!optionsRes.ok) {
                const errorData = await optionsRes.json();
                throw new Error(errorData.error || 'Server error');
            }

            const data = await optionsRes.json();
            const options = data.options;
            const newCsrf = data.new_csrf;
            
            console.log("Options received:", options);

            // 2. Trigger Biometric Registration
            const attestationResponse = await startRegistration(options);
            console.log("Attestation received:", attestationResponse);

            // 3. Update CSRF Token in the form to avoid 419 error
            if (newCsrf) {
                const csrfInput = registrationForm.querySelector('input[name="_token"]');
                if (csrfInput) csrfInput.value = newCsrf;
            }

            // 4. Manually create the hidden input and submit
            const attestationInput = document.createElement('input');
            attestationInput.type = 'hidden';
            attestationInput.name = 'passkey_attestation';
            attestationInput.value = JSON.stringify(attestationResponse);
            registrationForm.appendChild(attestationInput);

            console.log("Submitting form with attestation...");
            registrationForm.dataset.submitting = 'true';
            registrationForm.submit();

        } catch (err) {
            console.error("Identity Error:", err);
            alert("Ошибка активации личности: " + err.message);
            submitBtn.disabled = false;
            submitBtn.innerText = "Продолжить вход в периметр 🛡️";
        }
    });

    const innInput = document.getElementById('inn-field');
    const innLabel = document.getElementById('inn-label');
    const jurisdictionSelect = document.getElementById('jurisdiction');
    const fallbackFields = document.getElementById('fallback-fields');
    const taxSection = document.getElementById('tax-section');
    const bgData = document.getElementById('background-data');
    const nameField = document.getElementById('name-field');
    const nameContainer = document.getElementById('name-container');
    const phaseSearch = document.getElementById('phase-search');
    const phaseDetails = document.getElementById('phase-details');
    const confirmBtn = document.getElementById('confirm-org-btn');
    
    let typingTimer;

    const complianceConfig = @json($complianceConfig);
    const complianceInfo = document.getElementById('compliance-info');

    const updateLabels = () => {
        const jurisdiction = jurisdictionSelect.value;
        if (jurisdiction === 'KZ') {
            innLabel.innerText = "БИН организации";
            innInput.placeholder = "123456789012";
        } else if (jurisdiction === 'BY') {
            innLabel.innerText = "УНП организации";
            innInput.placeholder = "123456789";
        } else if (jurisdiction === 'UZ' || jurisdiction === 'AM' || jurisdiction === 'KG' || jurisdiction === 'TM') {
            innLabel.innerText = "ИНН (или TIN)";
            innInput.placeholder = "12345678";
        } else {
            innLabel.innerText = "ИНН организации";
            innInput.placeholder = "7700123456";
        }

        // 🛡️ Compliance Info
        if (complianceConfig && complianceConfig[jurisdiction]) {
            const config = complianceConfig[jurisdiction];
            complianceInfo.style.display = 'block';
            
            if (config.blocked) {
                complianceInfo.style.background = 'rgba(248, 113, 113, 0.05)';
                complianceInfo.style.borderColor = 'rgba(248, 113, 113, 0.2)';
                complianceInfo.innerHTML = `
                    <div style="color: #f87171; font-weight: 800; font-size: 0.75rem; margin-bottom: 4px;">⛔ OUT OF PERIMETER</div>
                    <div style="font-size: 0.65rem;">${config.reason || 'Not supported in this domain.'}</div>
                `;
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
            } else {
                complianceInfo.style.background = 'rgba(255, 255, 255, 0.02)';
                complianceInfo.style.borderColor = 'rgba(255, 255, 255, 0.05)';
                complianceInfo.innerHTML = `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <span>Risk Level:</span>
                        <span style="color: ${config.risk === 'high' ? '#f87171' : '#fbbf24'}; font-weight: 700;">${config.risk ? config.risk.toUpperCase() : 'MEDIUM'}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>KYC Provider:</span>
                        <span style="color: #fff; font-weight: 600;">${config.kyc_provider || 'Standard'}</span>
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            }
        } else {
            if (complianceInfo) complianceInfo.style.display = 'none';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
        }
    };

    jurisdictionSelect.addEventListener('change', updateLabels);
    updateLabels();

    const handleInput = () => {
        clearTimeout(typingTimer);
        const inn = innInput.value.trim();
        console.log("INN Input changed:", inn, "length:", inn.length);
        if (inn.length === 10 || inn.length === 12) {
            console.log("Triggering auto-search for INN...");
            searchINN();
        }
    };

    innInput.addEventListener('input', handleInput);
    innInput.addEventListener('paste', () => setTimeout(handleInput, 100));

    confirmBtn.addEventListener('click', () => {
        console.log("Organization confirmed. Moving to Phase 2.");
        phaseSearch.style.opacity = '0.3';
        phaseSearch.style.pointerEvents = 'none';
        phaseDetails.style.display = 'block';
    });

    function togglePoA(show) {
        const poaFields = document.getElementById('poa-fields');
        poaFields.style.display = show ? 'block' : 'none';
        
        if (show) {
            poaFields.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    async function searchINN() {
        const inn = innInput.value.trim();
        if (!inn) return;

        nameContainer.style.display = 'block';
        nameContainer.style.opacity = '0.5';
        nameField.value = "Загрузка...";
        
        try {
            const res = await fetch('/api/b2b/search', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ inn: inn })
            });
            
            if (!res.ok) throw new Error('API Error: ' + res.status);
            
            const data = await res.json();
            bgData.innerHTML = ''; // Clear previous

            if (data.suggestions && data.suggestions.length > 0) {
                const org = data.suggestions[0];
                
                nameContainer.style.opacity = '1';
                nameField.value = org.name;
                
                // Populate Background Data
                addHidden('legal_name', org.name);
                addHidden('ogrn', org.ogrn);
                addHidden('kpp', org.kpp || '');
                addHidden('address', org.address || '');
                addHidden('director_name', org.management ? org.management.name : '');

                // 💰 Tax System
                document.getElementById('tax_system').value = org.tax_system || 'OSN';

                if (org.is_ip) {
                    fallbackFields.classList.add('fallback-active');
                    document.getElementById('manual-name-group').style.display = 'none';
                    document.getElementById('fallback-message').textContent = 'Для ИП и самозанятых необходимо подтвердить адрес регистрации:';
                    document.getElementById('address-label').textContent = 'Адрес регистрации';
                    document.getElementById('manual_address').value = org.address || '';
                    document.getElementById('manual_ogrn').value = org.ogrn;
                } else {
                    fallbackFields.classList.remove('fallback-active');
                    fallbackFields.style.display = 'none';
                }
            } else if (data.fallback) {
                nameField.value = "ИНН не найден";
                nameContainer.style.opacity = '1';
                confirmBtn.innerText = "Ввести данные вручную ✍️";
            }
        } catch (e) {
            console.error('Search failed:', e);
            nameField.value = "Ошибка поиска";
            nameContainer.style.opacity = '1';
        }
    }

    function addHidden(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        bgData.appendChild(input);
    }
</script>

</body>
</html>
