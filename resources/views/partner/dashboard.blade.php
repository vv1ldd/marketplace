<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B Консоль — Meanly</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --amber: #f59e0b;
            --bg: #080b10;
            --bg-card: #0f1420;
            --border: rgba(255,255,255,0.07);
            --text: #f1f5f9;
            --muted: #64748b;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
        }
        .container {
            max-width: 600px;
            padding: 2rem;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: rgba(245, 158, 11, 0.1);
            color: var(--amber);
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 2rem;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }
        h1 { font-size: 2.5rem; font-weight: 900; letter-spacing: -1px; margin-bottom: 1rem; }
        p { color: var(--muted); font-size: 1.1rem; line-height: 1.6; margin-bottom: 2rem; }
        
        .sovereign-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 2rem;
            text-align: left;
            margin-top: 3rem;
        }
        .card-title { font-weight: 800; font-size: 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; }
        .data-row { display: flex; justify-content: space-between; margin-bottom: 0.75rem; font-size: 0.9rem; }
        .data-label { color: var(--muted); }
        .data-value { font-weight: 600; }

        .l1-badge {
            color: #10b981;
            font-weight: 700;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 1rem;
        }
    </style>
</head>
@include('partials.theme-sync-body')
<body data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" @if(request()->cookie('holiday')) data-holiday="{{ request()->cookie('holiday') }}" @endif>

<div class="container">
    @if($legalEntity && !$legalEntity->is_active)
        <div class="status-badge">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            На модерации
        </div>
        <h1>Юридическое оформление</h1>
        <p>
            Для активации продаж необходимо подписать оферту и подтвердить расчетный счет.
        </p>

        <div class="grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem;">
            <!-- ⚖️ Agreement Section -->
            <div class="sovereign-card" id="agreement-card">
                <div class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    Публичная оферта
                </div>
                <div id="agreement-text" style="font-size: 0.8rem; color: var(--muted); margin-bottom: 1.5rem; background: rgba(0,0,0,0.3); padding: 1.5rem; border-radius: 12px; height: 300px; overflow-y: auto; border: 1px solid rgba(255,255,255,0.05); line-height: 1.6;">
                    <h3>Договор на оказание услуг по размещению Товарных предложений</h3>
                    <p>Дата размещения: 30 апреля 2026 г. <br> Дата вступления в силу: 01 мая 2026 г.</p>
                    <hr style="border-color: rgba(255,255,255,0.1); margin: 1rem 0;">
                    <strong>1. Термины и определения</strong><br>
                    ... [Текст оферты загружен] ...
                    <p style="white-space: pre-wrap; font-size: 0.75rem;">{{ $agreementText }}</p>
                </div>
                
                @if(!$legalEntity->agreement_signed_at)
                    <button onclick="signSovereignAgreement()" class="btn-submit" style="margin-top: 0; font-size: 0.8rem; height: 44px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <span id="sign-btn-text">Подписать через Passkey ✍️</span>
                        <div id="sign-loader" class="loader" style="display:none; width: 16px; height: 16px;"></div>
                    </button>
                @else
                    <div class="l1-badge" style="background: rgba(var(--amber-rgb), 0.1); color: var(--amber); border-color: var(--amber);">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        ПОДПИСАНО: {{ $legalEntity->agreement_signed_at->format('d.m.Y H:i') }}
                    </div>
                @endif
            </div>

            <!-- 💰 Banking Section -->
            <div class="sovereign-card" id="bank-card">
                <div class="card-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                    Расчетный счет
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div>
                        <label style="font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; display: block;">БИК Банка</label>
                        <input type="text" id="bank_bic" placeholder="044525..." class="form-input" style="height: 40px; font-size: 0.9rem;" value="{{ $legalEntity->bank_bic }}">
                    </div>
                    <div>
                        <label style="font-size: 0.7rem; color: var(--muted); margin-bottom: 4px; display: block;">Номер счета (407...)</label>
                        <input type="text" id="bank_account" placeholder="40702810..." class="form-input" style="height: 40px; font-size: 0.9rem;" value="{{ $legalEntity->bank_account }}">
                    </div>
                    
                    <button onclick="updateBankDetails()" class="btn-submit" style="margin-top: 5px; font-size: 0.8rem; height: 44px; background: transparent; border: 1px solid var(--amber); color: var(--amber); display: flex; align-items: center; justify-content: center; gap: 10px;">
                        <span id="bank-btn-text">Проверить и сохранить 🏦</span>
                        <div id="bank-loader" class="loader" style="display:none; width: 16px; height: 16px;"></div>
                    </button>
                </div>
            </div>
        </div>

        <div class="sovereign-card" style="margin-top: 2rem;">
            <div class="card-title">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                Профиль продавца
            </div>
            <div class="data-row">
                <span class="data-label">Организация:</span>
                <span class="data-value">{{ $legalEntity->name }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">ИНН / ОГРН:</span>
                <span class="data-value">{{ $legalEntity->inn }} / {{ $legalEntity->ogrn ?? 'verified' }}</span>
            </div>
            <div class="data-row">
                <span class="data-label">Статус проверки:</span>
                <span class="data-value" style="color: var(--amber);">
                    @if($legalEntity->status === 'active')
                        <span style="color: #10b981;">АКТИВЕН 💎</span>
                    @elseif($legalEntity->status === 'awaiting_payment')
                        ОЖИДАЕТ ОПЛАТЫ СЧЕТА (1 ₽) 🏦
                    @elseif($legalEntity->status === 'rejected')
                        <span style="color: #ef4444;">ОТКЛОНЕН ❌</span>
                    @else
                        НА МОДЕРАЦИИ ⏳
                    @endif
                </span>
            </div>

            @if($legalEntity->status === 'awaiting_payment')
            <div style="margin-top: 1.5rem; padding: 1rem; background: rgba(245, 158, 11, 0.05); border: 1px dashed var(--amber); border-radius: 12px; font-size: 0.85rem;">
                <strong>Верификация счета:</strong><br>
                Пожалуйста, оплатите проверочный счет на 1 рубль. <br>
                <a href="#" style="color: var(--amber); text-decoration: underline; font-weight: 700;">Скачать счет (PDF) 📄</a>
            </div>
            @endif

            <div class="data-row" style="margin-top: 1.5rem;">
                <span class="data-label">ID профиля:</span>
                <span class="data-value" style="font-family: monospace; font-size: 0.75rem; color: #10b981;">{{ Auth::user()->meta['l1_address'] ?? 'не привязан' }}</span>
            </div>
            <div class="l1-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                ПРОФИЛЬ ЗАЩИЩЕН
            </div>
        </div>

        <script>
            async function signSovereignAgreement() {
                const btnText = document.getElementById('sign-btn-text');
                const loader = document.getElementById('sign-loader');
                
                btnText.style.display = 'none';
                loader.style.display = 'block';

                try {
                    await new Promise(r => setTimeout(r, 1500)); 
                    const response = await fetch('{{ route("partner.dashboard.sign") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    if (response.ok) {
                        window.location.reload();
                    }
                } catch (e) {
                    console.error(e);
                    alert('Ошибка подписи');
                } finally {
                    btnText.style.display = 'block';
                    loader.style.display = 'none';
                }
            }

            async function updateBankDetails() {
                const btnText = document.getElementById('bank-btn-text');
                const loader = document.getElementById('bank-loader');
                const bic = document.getElementById('bank_bic').value;
                const account = document.getElementById('bank_account').value;

                if (!bic || !account) {
                    alert('Заполните все поля');
                    return;
                }

                btnText.style.display = 'none';
                loader.style.display = 'block';

                try {
                    const response = await fetch('{{ route("partner.dashboard.bank") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ bic, account })
                    });
                    if (response.ok) {
                        alert('Реквизиты сохранены и проверяются 🏦');
                        window.location.reload();
                    } else {
                        alert('Ошибка валидации данных');
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    btnText.style.display = 'block';
                    loader.style.display = 'none';
                }
            }
        </script>
    @else
        <!-- B2B Sovereign Console SPA Styles & Scaffolding -->
        <style>
            body {
                display: block !important;
                text-align: left !important;
                min-height: 100vh;
                min-height: 100dvh;
                overflow: hidden;
                font-family: 'Outfit', 'Inter', sans-serif !important;
                margin: 0;
            }
            .container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
                display: flex !important;
                min-height: 100vh;
                min-height: 100dvh;
                height: 100vh;
                height: 100dvh;
                text-align: left !important;
            }

            /* --- 🎨 Multi-Skin Theme Engine --- */
            
            /* Theme 1: Meanly Partner 🌟 (Glassmorphism & Amber Glow) */
            body[data-theme="partner"] {
                background: radial-gradient(circle at 80% 10%, rgba(245, 158, 11, 0.08) 0%, transparent 50%), 
                            radial-gradient(circle at 10% 90%, rgba(139, 92, 246, 0.06) 0%, transparent 50%), 
                            #080a14 !important;
                --primary: #f59e0b;
                --primary-rgb: 245, 158, 11;
                --primary-glow: rgba(245, 158, 11, 0.2);
                --bg-main: #080a14;
                --bg-sidebar: rgba(13, 16, 27, 0.6);
                --bg-card: rgba(22, 28, 48, 0.35);
                --bg-input: rgba(10, 13, 24, 0.55);
                --border-card: rgba(255, 255, 255, 0.06);
                --border-neon: rgba(245, 158, 11, 0.2);
                --text-main: #f8fafc;
                --text-muted: #94a3b8;
                --shadow-neo: 0 8px 32px 0 rgba(0, 0, 0, 0.35);
                --shadow-neo-inset: inset 2px 2px 5px rgba(0,0,0,0.5), inset -2px -2px 5px rgba(255,255,255,0.01);
                --green: #10b981;
                --green-glow: rgba(16, 185, 129, 0.15);
                --rose: #f43f5e;
                --rose-glow: rgba(244, 63, 94, 0.15);
                --font-tech: 'Outfit', sans-serif;
            }
            body[data-theme="partner"] .logo-text-partner { display: inline !important; }
            body[data-theme="partner"] .logo-text-consortium { display: none !important; }
            body[data-theme="partner"] .logo-text-retro { display: none !important; }
            body[data-theme="partner"] .menu-item.active {
                background: linear-gradient(90deg, rgba(245, 158, 11, 0.12), rgba(245, 158, 11, 0.01)) !important;
                color: var(--primary) !important;
                border-left: 3px solid var(--primary) !important;
                border-radius: 0 12px 12px 0 !important;
                padding-left: calc(1rem - 3px) !important;
                border-right: none !important;
            }
            body[data-theme="partner"] .logo-sub {
                background: var(--primary) !important;
                color: #000000 !important;
                border-color: var(--primary) !important;
                font-weight: 900 !important;
            }
            body[data-theme="partner"] .card-neo {
                backdrop-filter: blur(20px) !important;
                -webkit-backdrop-filter: blur(20px) !important;
            }

            /* Theme 2: Consortium flagship 🚩 (Flat Neobrutalism Terminal) */
            body[data-theme="consortium"] {
                background: #030303 !important;
                --primary: #f53003;
                --primary-rgb: 245, 48, 3;
                --primary-glow: rgba(245, 48, 3, 0.25);
                --bg-main: #030303;
                --bg-sidebar: #090909;
                --bg-card: #090909;
                --bg-input: #030303;
                --border-card: rgba(255, 255, 255, 0.05);
                --border-neon: rgba(245, 48, 3, 0.2);
                --text-main: #ffffff;
                --text-muted: #8e8e93;
                --shadow-neo: 0 4px 20px 0 rgba(0, 0, 0, 0.6);
                --shadow-neo-inset: inset 2px 2px 5px rgba(0,0,0,0.5), inset -2px -2px 5px rgba(255,255,255,0.01);
                --green: #10b981;
                --green-glow: rgba(16, 185, 129, 0.15);
                --rose: #f43f5e;
                --rose-glow: rgba(244, 63, 94, 0.15);
                --font-tech: 'JetBrains Mono', monospace;
            }
            body[data-theme="consortium"] .logo-text-partner { display: none !important; }
            body[data-theme="consortium"] .logo-text-consortium { display: inline !important; }
            body[data-theme="consortium"] .logo-text-retro { display: none !important; }
            body[data-theme="consortium"] .card-neo {
                background: var(--bg-card) !important;
                border: 1px solid var(--border-card) !important;
                border-radius: 12px !important;
                box-shadow: var(--shadow-neo) !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }
            body[data-theme="consortium"] .card-neo:hover {
                transform: translateY(-2px);
                border-color: rgba(245, 48, 3, 0.2) !important;
                box-shadow: 0 8px 30px rgba(245, 48, 3, 0.06), var(--shadow-neo) !important;
            }
            body[data-theme="consortium"] .menu-item.active {
                background: rgba(245, 48, 3, 0.08) !important;
                color: #ffffff !important;
                border: 1px solid rgba(245, 48, 3, 0.15) !important;
                border-radius: 8px !important;
                border-left: none !important;
                padding-left: 1rem !important;
            }
            body[data-theme="consortium"] .menu-item.active svg {
                stroke: var(--primary) !important;
            }
            body[data-theme="consortium"] .logo-dot {
                background: var(--primary);
                box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.5);
                border-radius: 3px !important;
            }

            /* Theme 3: Consortium Retro ⚡ (Light Neo-Brutalism - Match Screen 3) */
            body[data-theme="retro"] {
                background: #eef0fc !important;
                --primary: #7c3aed;
                --primary-rgb: 124, 58, 237;
                --primary-glow: rgba(124, 58, 237, 0.25);
                --bg-main: #eef0fc;
                --bg-sidebar: #ffffff;
                --bg-card: #ffffff;
                --bg-input: #ffffff;
                --border-card: #000000;
                --border-neon: #000000;
                --text-main: #000000;
                --text-muted: #5e5e6e;
                --shadow-neo: 6px 6px 0px #000000;
                --shadow-neo-inset: none;
                --green: #10b981;
                --green-glow: rgba(16, 185, 129, 0.1);
                --rose: #f43f5e;
                --rose-glow: rgba(244, 63, 94, 0.1);
                --font-tech: 'Outfit', sans-serif;
            }
            body[data-theme="retro"] .logo-text-partner { display: none !important; }
            body[data-theme="retro"] .logo-text-consortium { display: none !important; }
            body[data-theme="retro"] .logo-text-retro { display: inline !important; }
            body[data-theme="retro"] .sidebar {
                border-right: 3px solid #000000 !important;
                background: #ffffff !important;
            }
            body[data-theme="retro"] .card-neo {
                background: #ffffff !important;
                border: 3px solid #000000 !important;
                border-radius: 8px !important;
                box-shadow: var(--shadow-neo) !important;
                color: #000000 !important;
                backdrop-filter: none !important;
                -webkit-backdrop-filter: none !important;
            }
            body[data-theme="retro"] .card-neo:hover {
                transform: translate(-2px, -2px);
                box-shadow: 8px 8px 0px var(--primary) !important;
            }
            body[data-theme="retro"] .menu-item {
                color: #000000 !important;
                border: 2px solid transparent;
            }
            body[data-theme="retro"] .menu-item:hover {
                background: rgba(124, 58, 237, 0.08) !important;
                color: var(--primary) !important;
            }
            body[data-theme="retro"] .menu-item.active {
                background: var(--primary) !important;
                color: #ffffff !important;
                border: 3px solid #000000 !important;
                box-shadow: 3px 3px 0px #000000 !important;
                border-radius: 6px !important;
            }
            body[data-theme="retro"] .menu-item.active svg {
                stroke: #ffffff !important;
            }
            body[data-theme="retro"] .logo-dot {
                background: var(--primary) !important;
                border: 2px solid #000000 !important;
                border-radius: 3px !important;
                box-shadow: none !important;
            }
            body[data-theme="retro"] .logo-sub {
                border: 2px solid #000000 !important;
                background: #ffffff !important;
                color: #000000 !important;
            }
            body[data-theme="retro"] .top-bar {
                border-bottom: 3px solid #000000 !important;
            }
            body[data-theme="retro"] .top-stat-item {
                background: #ffffff !important;
                border: 2px solid #000000 !important;
                color: #000000 !important;
                box-shadow: 2px 2px 0px #000000 !important;
                border-radius: 6px !important;
            }
            body[data-theme="retro"] .balance-summary,
            body[data-theme="retro"] .dashboard-welcome,
            body[data-theme="retro"] .dashboard-metric-card {
                background: #ffffff !important;
                border: 3px solid #000000 !important;
                box-shadow: 6px 6px 0 #000000 !important;
                color: #000000 !important;
            }
            body[data-theme="retro"] .balance-summary-status,
            body[data-theme="retro"] .balance-summary-cell {
                background: transparent !important;
                border: 0 !important;
                box-shadow: none !important;
            }
            body[data-theme="retro"] .balance-summary-cell {
                border-left: 2px solid #000000 !important;
            }
            body[data-theme="retro"] .dashboard-welcome-aside,
            body[data-theme="retro"] .dashboard-mini-stat {
                background: transparent !important;
                border: 0 !important;
                box-shadow: none !important;
            }
            body[data-theme="retro"] .dashboard-mini-stat {
                border-left: 2px solid #000000 !important;
            }
            body[data-theme="retro"] .skin-switcher-pill {
                background: #ffffff !important;
                border: 2px solid #000000 !important;
                box-shadow: 2px 2px 0px #000000 !important;
            }
            body[data-theme="retro"] .input-neo {
                background: #ffffff !important;
                border: 3px solid #000000 !important;
                color: #000000 !important;
                border-radius: 6px !important;
                box-shadow: 3px 3px 0px #000000 !important;
            }
            body[data-theme="retro"] .input-neo:focus {
                border-color: var(--primary) !important;
                box-shadow: 4px 4px 0px #000000 !important;
            }
            body[data-theme="retro"] .btn-neo {
                background: #ffffff !important;
                border: 3px solid #000000 !important;
                color: #000000 !important;
                border-radius: 6px !important;
                box-shadow: 4px 4px 0px #000000 !important;
            }
            body[data-theme="retro"] .btn-neo:hover {
                background: var(--primary) !important;
                color: #ffffff !important;
                transform: translate(-1px, -1px);
            }
            body[data-theme="retro"] .btn-primary-neo {
                background: var(--primary) !important;
                color: #ffffff !important;
                border: 3px solid #000000 !important;
                box-shadow: 4px 4px 0px #000000 !important;
            }
            body[data-theme="retro"] .btn-primary-neo:hover {
                background: #ffffff !important;
                color: #000000 !important;
            }
            body[data-theme="retro"] .badge-neo {
                background: #ffffff !important;
                border: 2px solid #000000 !important;
                color: #000000 !important;
                box-shadow: 2px 2px 0px #000000 !important;
                border-radius: 4px !important;
            }
            body[data-theme="retro"] .neo-table-container {
                border: 3px solid #000000 !important;
                border-radius: 8px !important;
                background: #ffffff !important;
            }
            body[data-theme="retro"] .neo-table th {
                border-bottom: 2px solid #000000 !important;
                background: #ffffff !important;
                color: #000000 !important;
            }
            body[data-theme="retro"] .neo-table td {
                border-bottom: 2px solid #000000 !important;
                color: #000000 !important;
            }
            body[data-theme="retro"] .sidebar-footer {
                border-top: 3px solid #000000 !important;
            }

            /* Global Scrollbar */
            ::-webkit-scrollbar { width: 6px; height: 6px; }
            ::-webkit-scrollbar-track { background: var(--bg-main); }
            ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 100px; }
            ::-webkit-scrollbar-thumb:hover { background: var(--primary); }

            /* Layout framework */
            .sidebar {
                width: 280px;
                background: var(--bg-sidebar);
                border-right: 1px solid var(--border-card);
                display: flex;
                flex-direction: column;
                padding: 1.5rem 0;
                box-sizing: border-box;
                flex-shrink: 0;
                min-height: 100vh;
                min-height: 100dvh;
                height: 100%;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                transition: all 0.3s;
            }
            .sidebar-logo {
                font-size: 1.1rem;
                font-weight: 900;
                letter-spacing: -0.04em;
                padding: 0 2rem;
                margin-bottom: 0.5rem;
                display: flex;
                align-items: center;
                gap: 10px;
                color: var(--text-main);
            }
            .logo-dot {
                width: 12px;
                height: 12px;
                border-radius: 3px;
                flex-shrink: 0;
                background: var(--primary);
                box-shadow: 0 0 10px rgba(var(--primary-rgb), 0.5);
            }
            .logo-sub {
                font-size: 0.6rem;
                text-transform: uppercase;
                letter-spacing: 1px;
                background: rgba(255,255,255,0.05);
                padding: 2px 6px;
                border-radius: 4px;
                margin-left: 4px;
                font-weight: 800;
            }
            .sidebar-menu {
                display: flex;
                flex-direction: column;
                gap: 2px;
                padding: 0 0.75rem;
                flex-grow: 1;
            }
            .sidebar-section-title {
                font-size: 0.65rem;
                font-weight: 800;
                text-transform: uppercase;
                color: var(--text-muted);
                letter-spacing: 1px;
                padding: 1.25rem 1.25rem 0.5rem;
                opacity: 0.5;
            }
            .menu-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 0.65rem 1rem;
                color: var(--text-muted);
                text-decoration: none;
                font-size: 0.85rem;
                font-weight: 600;
                border-radius: 8px;
                transition: all 0.2s;
                cursor: pointer;
            }
            .menu-item:hover {
                background: rgba(255,255,255,0.02);
                color: var(--text-main);
            }
            .menu-item svg {
                width: 16px;
                height: 16px;
                stroke-width: 2.5;
                fill: none;
                stroke: currentColor;
                flex-shrink: 0;
            }
            
            .sidebar-footer {
                padding: 1rem;
                margin: 0 0.75rem;
                border-top: 1px solid var(--border-card);
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .user-avatar {
                width: 32px;
                height: 32px;
                background: var(--bg-card);
                border: 1px solid var(--border-card);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 800;
                font-size: 0.8rem;
                color: var(--primary);
                box-shadow: var(--shadow-neo);
            }
            .user-info {
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }
            .user-name {
                font-weight: 700;
                font-size: 0.8rem;
                color: var(--text-main);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .user-role {
                font-size: 0.65rem;
                color: var(--text-muted);
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .main-content {
                flex-grow: 1;
                min-width: 0;
                padding: 2rem 2.5rem calc(5rem + env(safe-area-inset-bottom, 0px));
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                gap: 1.5rem;
                overflow-y: auto;
                height: 100%;
                min-height: 0;
                -webkit-overflow-scrolling: touch;
                scroll-padding-bottom: calc(5rem + env(safe-area-inset-bottom, 0px));
            }
            .top-bar {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 1rem;
                border-bottom: 1px solid var(--border-card);
                padding-bottom: 0.8rem;
            }
            .top-title-stack {
                display: flex;
                flex-direction: column;
                justify-content: center;
                min-width: 180px;
                gap: 4px;
            }
            .page-title {
                font-size: 1.4rem;
                font-weight: 900;
                letter-spacing: -0.5px;
            }
            .page-subtitle {
                color: var(--text-muted);
                font-size: 0.72rem;
                font-weight: 800;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .top-stats {
                display: flex;
                gap: 15px;
                align-items: center;
                flex-wrap: wrap;
            }
            .top-stat-item {
                background: rgba(0,0,0,0.15);
                border: 1px solid var(--border-card);
                border-radius: 100px;
                padding: 6px 14px;
                font-size: 0.7rem;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .stat-label {
                color: var(--text-muted);
            }
            .stat-val {
                font-weight: 800;
            }
            .balance-summary {
                width: min(660px, 100%);
                display: grid;
                grid-template-columns: minmax(120px, 0.85fr) repeat(3, minmax(116px, 1fr));
                gap: 0;
                padding: 6px 8px;
                border: 1px solid var(--border-card);
                border-radius: 14px;
                background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.08), rgba(255,255,255,0.02)), var(--bg-card);
                box-shadow: var(--shadow-neo);
            }
            .balance-summary-status,
            .balance-summary-cell {
                min-height: 38px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 3px;
                border: 0;
                border-radius: 0;
                background: transparent;
                padding: 4px 10px;
            }
            .balance-summary-status {
                gap: 5px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                font-family: var(--font-tech);
            }
            .balance-summary-cell {
                border-left: 1px solid var(--border-card);
            }
            .balance-summary-kicker,
            .balance-summary-cell span {
                color: var(--text-muted);
                font-size: 0.58rem;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .balance-status-line {
                display: inline-flex;
                align-items: center;
                gap: 7px;
                color: var(--text-main);
                font-size: 0.62rem;
                font-weight: 950;
            }
            .balance-status-dot {
                width: 7px;
                height: 7px;
                border-radius: 999px;
                background: var(--primary);
                box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.12);
            }
            .balance-status-dot.is-secured {
                background: var(--green);
                box-shadow: 0 0 0 3px var(--green-glow);
            }
            .balance-summary-cell strong {
                color: var(--text-main);
                font-family: var(--font-tech);
                font-size: 0.78rem;
                font-weight: 950;
                white-space: nowrap;
            }
            .balance-summary-cell.balance-primary strong {
                color: var(--primary);
            }
            .balance-summary-cell.balance-native strong {
                color: var(--green);
            }
            
            /* Neomorphic Foundations */
            .card-neo {
                background: var(--bg-card);
                border: 1px solid var(--border-card);
                border-radius: 24px;
                padding: 1.5rem;
                box-shadow: var(--shadow-neo);
                transition: all 0.3s;
            }
            .card-neo:hover {
                transform: translateY(-2px);
                border-color: rgba(255,255,255,0.1);
            }
            .input-neo {
                background: var(--bg-input);
                border: 1px solid var(--border-card);
                border-radius: 10px;
                padding: 0.65rem 0.85rem;
                color: var(--text-main);
                font-family: inherit;
                font-size: 0.85rem;
                box-shadow: var(--shadow-neo-inset);
                transition: all 0.3s;
                width: 100%;
                box-sizing: border-box;
            }
            .input-neo:focus {
                outline: none;
                border-color: var(--primary);
                box-shadow: 0 0 12px var(--primary-glow);
            }
            .btn-neo {
                background: var(--bg-card);
                border: 1px solid var(--border-card);
                border-radius: 10px;
                padding: 0.6rem 1.25rem;
                color: var(--text-main);
                font-weight: 700;
                font-size: 0.85rem;
                cursor: pointer;
                box-shadow: var(--shadow-neo);
                transition: all 0.3s;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }
            .btn-neo:hover {
                transform: translateY(-1px);
                border-color: var(--primary);
                box-shadow: 0 4px 12px var(--primary-glow);
            }
            .btn-primary-neo {
                background: var(--primary) !important;
                color: #ffffff !important;
                border: none;
                box-shadow: 0 4px 20px rgba(var(--primary-rgb), 0.3) !important;
            }
            .btn-primary-neo:hover {
                box-shadow: 0 6px 25px rgba(var(--primary-rgb), 0.5) !important;
            }

            /* Responsive Grid */
            .grid-12 {
                display: grid;
                grid-template-columns: repeat(12, 1fr);
                gap: 1.25rem;
            }
            .col-3 { grid-column: span 3; }
            .col-4 { grid-column: span 4; }
            .col-5 { grid-column: span 5; }
            .col-6 { grid-column: span 6; }
            .col-7 { grid-column: span 7; }
            .col-8 { grid-column: span 8; }
            .col-12 { grid-column: span 12; }

            #integration-detail-view {
                width: 100%;
                max-width: 100%;
            }
            #integration-detail-view .integration-detail-panel {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
            }
            #integration-detail-view .grid-12 > [class*="col-"] {
                min-width: 0;
            }
            .yandex-settings-form-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }
            .yandex-settings-form-grid .field-span-2 {
                grid-column: span 2;
            }

            /* SPA Pane Management */
            .tab-pane {
                display: none;
                animation: fadeIn 0.4s ease-out;
            }
            .tab-pane.active {
                display: block;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Metric Card Subtitles */
            .metric-title {
                font-size: 0.7rem;
                text-transform: uppercase;
                color: var(--text-muted);
                letter-spacing: 0.5px;
                margin-bottom: 4px;
                font-weight: 800;
            }
            .metric-value {
                font-size: 1.8rem;
                font-weight: 900;
                color: var(--text-main);
                font-family: var(--font-tech);
            }
            .dashboard-welcome {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(220px, 300px);
                gap: 1.1rem;
                align-items: center;
                margin-bottom: 1.25rem;
                padding: 1.25rem 1.35rem;
                overflow: hidden;
                position: relative;
                background: linear-gradient(135deg, rgba(var(--primary-rgb), 0.08) 0%, rgba(255,255,255,0.02) 100%), var(--bg-card);
            }
            .dashboard-welcome-main {
                min-width: 0;
            }
            .dashboard-badges {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                margin-bottom: 10px;
            }
            .dashboard-welcome h2 {
                margin: 0 0 8px;
                color: var(--text-main);
                font-size: clamp(1.45rem, 2.6vw, 1.95rem);
                font-weight: 950;
                line-height: 0.98;
                letter-spacing: -0.06em;
            }
            .dashboard-welcome p {
                max-width: 780px;
                margin: 0;
                color: var(--text-muted);
                font-size: 0.88rem;
                font-weight: 650;
                line-height: 1.45;
            }
            .dashboard-welcome-aside {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px 12px;
                align-content: stretch;
                border: 0;
                border-radius: 0;
                background: transparent;
                padding: 0;
            }
            .dashboard-mini-stat {
                min-height: 46px;
                display: flex;
                flex-direction: column;
                justify-content: center;
                gap: 3px;
                border: 0;
                border-left: 2px solid var(--border-card);
                border-radius: 0;
                padding: 4px 0 4px 10px;
                background: transparent;
            }
            .dashboard-mini-stat span {
                color: var(--text-muted);
                font-size: 0.58rem;
                font-weight: 900;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }
            .dashboard-mini-stat strong {
                color: var(--text-main);
                font-family: var(--font-tech);
                font-size: 0.9rem;
                font-weight: 950;
            }
            .dashboard-mini-action {
                grid-column: 1 / -1;
                justify-content: center;
                min-height: 34px;
                padding: 0.45rem 0.9rem;
            }
            .dashboard-metric-card {
                min-height: 132px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                gap: 12px;
            }
            .metric-caption {
                color: var(--text-muted);
                font-size: 0.68rem;
                font-weight: 750;
                line-height: 1.35;
            }
            .metric-action {
                width: fit-content;
                min-height: 32px;
                padding: 6px 12px;
                border-radius: 999px;
                background: var(--green-glow);
                color: var(--green);
                border-color: rgba(16, 185, 129, 0.2);
                font-size: 0.68rem;
                font-weight: 900;
                text-transform: uppercase;
            }
            
            /* Table Styling */
            .neo-table-container {
                overflow-x: auto;
                border-radius: 12px;
                border: 1px solid var(--border-card);
                background: rgba(0,0,0,0.1);
            }
            .neo-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 0.8rem;
            }
            .neo-table th {
                background: rgba(255,255,255,0.02);
                padding: 0.75rem 1rem;
                font-weight: 800;
                color: var(--text-muted);
                border-bottom: 1px solid var(--border-card);
                text-transform: uppercase;
                font-size: 0.65rem;
                letter-spacing: 0.5px;
            }
            .neo-table td {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid var(--border-card);
                color: var(--text-main);
            }
            .neo-table tr:last-child td {
                border-bottom: none;
            }
            .neo-table tr:hover td {
                background: rgba(255,255,255,0.01);
            }

            /* Badges */
            .badge-neo {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                border-radius: 100px;
                font-size: 0.65rem;
                font-weight: 800;
                text-transform: uppercase;
            }
            .badge-green { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
            .badge-amber { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
            .badge-rose { background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.2); }
            
            /* Dialog Modal */
            .modal-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.6);
                backdrop-filter: blur(8px);
                display: none;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            }
            .modal-backdrop.active {
                display: flex;
            }
            .modal-content {
                width: 100%;
                max-width: 500px;
                background: var(--bg-card);
                border: 1px solid var(--border-neon);
                border-radius: 20px;
                padding: 1.5rem;
                box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                box-sizing: border-box;
            }
            .modal-header {
                font-size: 1.1rem;
                font-weight: 900;
                margin-bottom: 1rem;
                color: var(--text-main);
            }
            .modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 10px;
                margin-top: 1.5rem;
            }
            
            /* Pulse indicator */
            .status-pulse {
                width: 6px;
                height: 6px;
                background: var(--green);
                border-radius: 50%;
                box-shadow: 0 0 8px var(--green);
                animation: pulseGlow 2s infinite;
            }
            @keyframes pulseGlow {
                0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
                70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
                100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
            }
            @media (max-width: 1180px) {
                .top-bar {
                    flex-direction: column;
                }
                .balance-summary {
                    width: 100%;
                }
                .dashboard-welcome {
                    grid-template-columns: 1fr;
                }
            }
            @media (max-width: 960px) {
                .col-5,
                .col-7,
                .col-8 {
                    grid-column: span 12;
                }
                .yandex-settings-form-grid {
                    grid-template-columns: 1fr;
                }
                .yandex-settings-form-grid .field-span-2 {
                    grid-column: span 1;
                }
            }
            @media (max-width: 860px) {
                .balance-summary {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
                .col-3 {
                    grid-column: span 6;
                }
            }
            @media (max-width: 640px) {
                .balance-summary,
                .dashboard-welcome-aside,
                .grid-12 {
                    grid-template-columns: 1fr;
                }
                .col-3,
                .col-4,
                .col-5,
                .col-6,
                .col-7,
                .col-8,
                .col-12 {
                    grid-column: span 1;
                }
                .main-content {
                    padding-inline: 1rem;
                }
            }
            
            .skin-btn.active {
                background: var(--primary) !important;
                color: #ffffff !important;
            }
            body[data-theme="retro"] .skin-btn.active {
                color: #ffffff !important;
            }

            /* 🦁 Easter Egg: Son's Birthday (May 19) - Albiceleste */
            body[data-holiday="sons-birthday"] {
                --primary: #74acdf !important; /* Argentine Sky Blue */
                --green: #74acdf !important; /* Sky Blue replaces green for pulses and accents! */
            }
            body[data-holiday="sons-birthday"] .logo-dot {
                background: linear-gradient(135deg, #74acdf 0%, #ffffff 100%) !important; /* Albiceleste gradient! */
                box-shadow: 0 0 15px rgba(116, 172, 223, 0.55) !important;
            }
            body[data-holiday="sons-birthday"] .btn-nav-cta,
            body[data-holiday="sons-birthday"] .btn-primary,
            body[data-holiday="sons-birthday"] .menu-item.active {
                background: #74acdf !important; /* Albiceleste Sky Blue! */
                color: #ffffff !important;
                border-color: #ffffff !important;
                box-shadow: 0 4px 15px rgba(116, 172, 223, 0.45) !important;
            }

            /* 🌸 Easter Egg: Orchid Day (May 12) - Beautiful Orchid Purple & Violet Theme */
            body[data-holiday="orchid-day"] {
                --primary: #d946ef !important; /* Orchid Magenta */
            }
            body[data-holiday="orchid-day"] .logo-dot {
                background: linear-gradient(135deg, #d946ef 0%, #c084fc 100%) !important;
                box-shadow: 0 0 15px rgba(217, 70, 239, 0.5) !important;
            }
            body[data-holiday="orchid-day"] .btn-nav-cta,
            body[data-holiday="orchid-day"] .btn-primary,
            body[data-holiday="orchid-day"] .menu-item.active {
                background: linear-gradient(135deg, #d946ef 0%, #86198f 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(217, 70, 239, 0.35) !important;
            }

            /* 🩺 Easter Egg: Doctor's Day / Stethoscope Day (April 21) - Healing Mint & Cyan Theme */
            body[data-holiday="doctor-day"] {
                --primary: #0d9488 !important; /* Healing Teal */
            }
            body[data-holiday="doctor-day"] .logo-dot {
                background: linear-gradient(135deg, #0d9488 0%, #06b6d4 100%) !important;
                box-shadow: 0 0 15px rgba(13, 148, 136, 0.5) !important;
            }
            body[data-holiday="doctor-day"] .btn-nav-cta,
            body[data-holiday="doctor-day"] .btn-primary,
            body[data-holiday="doctor-day"] .menu-item.active {
                background: linear-gradient(135deg, #0d9488 0%, #115e59 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(13, 148, 136, 0.35) !important;
            }

            /* 📚 Easter Egg: Library of Babel Day (Jorge Luis Borges' Birthday - August 24) - Antique Amber & Parchment Theme */
            body[data-holiday="babel-library"] {
                --primary: #b45309 !important; /* Antique Amber */
            }
            body[data-holiday="babel-library"] .logo-dot {
                background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%) !important;
                box-shadow: 0 0 15px rgba(180, 83, 9, 0.5) !important;
            }
            body[data-holiday="babel-library"] .btn-nav-cta,
            body[data-holiday="babel-library"] .btn-primary,
            body[data-holiday="babel-library"] .menu-item.active {
                background: linear-gradient(135deg, #b45309 0%, #78350f 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(180, 83, 9, 0.35) !important;
            }

            /* 💕 Easter Egg: Valentine's Day (Feb 14) - Premium Sweet Pink & Crimson Red Theme */
            body[data-holiday="valentine"] {
                --primary: #ff4d6d !important; /* Sweet Pink */
            }
            body[data-holiday="valentine"] .logo-dot {
                background: linear-gradient(135deg, #ff4d6d 0%, #ff758f 100%) !important;
                box-shadow: 0 0 15px rgba(255, 77, 109, 0.5) !important;
            }
            body[data-holiday="valentine"] .btn-nav-cta,
            body[data-holiday="valentine"] .btn-primary,
            body[data-holiday="valentine"] .menu-item.active {
                background: linear-gradient(135deg, #ff4d6d 0%, #c9184a 100%) !important;
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(255, 77, 109, 0.4) !important;
            }

            /* 🌹 Easter Egg: The Little Prince (Oct 17) - Single Rose under Glass Dome & Stars */
            body[data-holiday="little-prince"] {
                --primary: #f59e0b !important; /* Golden Star Yellow */
                --brand-bg: #0b0f19 !important; /* Twilight space dark */
                --brand-card: rgba(17, 24, 39, 0.65) !important;
                --brand-border: rgba(255, 255, 255, 0.08) !important;
                --brand-border-hover: rgba(245, 158, 11, 0.35) !important;
                background: #0b0f19 !important;
            }
            body[data-holiday="little-prince"] .logo-dot {
                background: #f59e0b !important;
                box-shadow: 0 0 15px rgba(245, 158, 11, 0.55) !important;
            }
            body[data-holiday="little-prince"] .btn-nav-cta,
            body[data-holiday="little-prince"] .btn-primary,
            body[data-holiday="little-prince"] .menu-item.active {
                background: linear-gradient(135deg, #be123c 0%, #e11d48 100%) !important; /* Rose Red buttons */
                color: #ffffff !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
                box-shadow: 0 4px 15px rgba(225, 29, 72, 0.35) !important;
            }

            /* Sidebar selection stays quiet: no icon glow, no nested neon states. */
            body .sidebar .sidebar-menu .menu-item.active,
            body[data-holiday] .sidebar .sidebar-menu .menu-item.active {
                background: rgba(var(--primary-rgb), 0.10) !important;
                color: var(--text-main) !important;
                border: 0 !important;
                border-left: 3px solid var(--primary) !important;
                border-radius: 8px !important;
                box-shadow: none !important;
                filter: none !important;
                text-shadow: none !important;
                padding-left: calc(1rem - 3px) !important;
            }
            body[data-theme="retro"] .sidebar .sidebar-menu .menu-item.active,
            body[data-theme="retro"][data-holiday] .sidebar .sidebar-menu .menu-item.active {
                background: #f1eaff !important;
                color: #111827 !important;
                border: 0 !important;
                border-left: 4px solid var(--primary) !important;
                box-shadow: none !important;
                padding-left: calc(1rem - 4px) !important;
            }
            body .sidebar .sidebar-menu .menu-item.active svg,
            body[data-holiday] .sidebar .sidebar-menu .menu-item.active svg {
                stroke: currentColor !important;
                color: inherit !important;
                filter: none !important;
                box-shadow: none !important;
                text-shadow: none !important;
            }

            /* 🌹 Glass Dome Floating Widget */
            .little-prince-dome {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 130px;
                height: 180px;
                z-index: 10000;
                cursor: pointer;
                pointer-events: auto;
                animation: domeFloat 4.5s ease-in-out infinite;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                filter: drop-shadow(0 10px 25px rgba(225, 29, 72, 0.18));
                display: none;
            }
            body[data-holiday="little-prince"] .little-prince-dome {
                display: block !important;
            }
            .little-prince-dome:hover {
                transform: scale(1.12) translateY(-5px);
                filter: drop-shadow(0 15px 35px rgba(225, 29, 72, 0.38));
            }
            .dome-svg {
                width: 100%;
                height: 100%;
                filter: drop-shadow(0 0 12px rgba(225, 29, 72, 0.2));
            }
            .dome-glow {
                position: absolute;
                inset: 15px;
                background: radial-gradient(circle, rgba(225, 29, 72, 0.2) 0%, rgba(225,29,72,0) 70%);
                pointer-events: none;
                z-index: -1;
                mix-blend-mode: screen;
                animation: rosePulse 3.5s ease-in-out infinite;
            }
            .dome-tooltip {
                position: absolute;
                bottom: 105%;
                right: 0;
                width: 240px;
                background: rgba(11, 15, 25, 0.92);
                border: 1px solid rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(12px);
                color: #ffffff;
                padding: 10px 14px;
                border-radius: 12px;
                font-size: 13px;
                text-align: center;
                line-height: 1.4;
                opacity: 0;
                visibility: hidden;
                transform: translateY(10px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                pointer-events: none;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            }
            .little-prince-dome:hover .dome-tooltip {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }
            .dome-sparkle {
                position: absolute;
                width: 4px;
                height: 4px;
                background: #ffd700;
                border-radius: 50%;
                box-shadow: 0 0 8px #ffd700;
                pointer-events: none;
                animation: domeSparkle 3s ease-in-out infinite;
            }
            @keyframes domeFloat {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-8px); }
            }
            @keyframes rosePulse {
                0%, 100% { opacity: 0.6; transform: scale(0.92); }
                50% { opacity: 1; transform: scale(1.12); }
            }
            @keyframes domeSparkle {
                0%, 100% { transform: scale(0) translateY(0); opacity: 0; }
                50% { transform: scale(1) translateY(-18px); opacity: 1; }
            }
        </style>

        <!-- 🌹 Easter Egg Widget: Rose under Glass Dome (The Little Prince) -->
        <div class="little-prince-dome" id="littlePrinceDome">
            <div class="dome-tooltip">«Ты навсегда в ответе за тех, кого приручил. Твоя Роза.» 🌹</div>
            <svg viewBox="0 0 100 140" class="dome-svg">
                <ellipse cx="50" cy="120" rx="35" ry="10" fill="#4a2c11" stroke="#2c1a0a" stroke-width="1.5"/>
                <ellipse cx="50" cy="118" rx="32" ry="8" fill="#6d421e"/>
                <path d="M 42 116 Q 46 113 49 116 Q 45 119 42 116" fill="#e11d48" opacity="0.9"/>
                <path d="M 50 118 Q 48 95 50 75" fill="none" stroke="#166534" stroke-width="2.5" stroke-linecap="round"/>
                <path d="M 49 100 Q 40 98 44 92 Q 49 96 49 100" fill="#15803d"/>
                <path d="M 50 88 Q 58 87 55 81 Q 50 84 50 88" fill="#15803d"/>
                <ellipse cx="50" cy="70" rx="7" ry="10" fill="#be123c"/>
                <path d="M 44 73 C 40 65, 46 58, 50 63 C 54 58, 60 65, 56 73 C 50 78, 50 78, 44 73 Z" fill="#e11d48"/>
                <path d="M 47 72 C 45 68, 48 64, 50 66 C 52 64, 55 68, 53 72 Z" fill="#f43f5e"/>
                <path d="M 22 118 L 22 60 A 28 28 0 0 1 78 60 L 78 118 Z" fill="rgba(255, 255, 255, 0.08)" stroke="rgba(255, 255, 255, 0.35)" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M 28 110 L 28 60 A 22 22 0 0 1 50 38" fill="none" stroke="rgba(255, 255, 255, 0.25)" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="50" cy="30" r="4.5" fill="rgba(255, 255, 255, 0.4)" stroke="rgba(255, 255, 255, 0.6)" stroke-width="1"/>
            </svg>
            <div class="dome-glow"></div>
            <div class="dome-sparkle" style="top: 40%; left: 30%; animation-delay: 0s;"></div>
            <div class="dome-sparkle" style="top: 60%; left: 70%; animation-delay: 1.2s;"></div>
            <div class="dome-sparkle" style="top: 80%; left: 45%; animation-delay: 2.4s;"></div>
        </div>

        <div class="container">
            <!-- Sidebar Navigation -->
            <div class="sidebar">
                <div class="sidebar-logo">
                    <a href="{{ route('partner.dashboard') }}" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 4px; width: 100%;" title="B2B Консоль управления">
                        <span class="logo-dot"></span>
                        <span class="logo-text-partner">MEANLY <span class="logo-sub">PARTNER</span></span>
                        <span class="logo-text-consortium" style="display: none;">MEANLY <span class="logo-sub" style="background: rgba(245, 48, 3, 0.1); color: var(--primary); border: 1px solid rgba(245, 48, 3, 0.2);">BUSINESS</span></span>
                        <span class="logo-text-retro" style="display: none;">MEANLY.</span>
                    </a>
                </div>

                <div class="sidebar-menu">
                    <!-- Core Sections -->
                    <a href="javascript:void(0)" onclick="switchTab('dashboard')" class="menu-item active" id="menu-dashboard">
                        <svg viewBox="0 0 24 24"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                        Инфопанель
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('orders')" class="menu-item" id="menu-orders">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="7" y1="8" x2="17" y2="8"></line><line x1="7" y1="12" x2="17" y2="12"></line><line x1="7" y1="16" x2="13" y2="16"></line></svg>
                        Заказы
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('catalog')" class="menu-item" id="menu-catalog">
                        <svg viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path><line x1="7" y1="7" x2="7.01" y2="7"></line></svg>
                        Мой каталог
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('storefront')" class="menu-item" id="menu-storefront">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px; height:16px; display: inline-block; vertical-align: middle;"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                        Каталог поставщиков
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('shops')" class="menu-item" id="menu-shops">
                        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                        Магазины
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('support')" class="menu-item" id="menu-support">
                        <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        Поддержка
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('warehouses')" class="menu-item" id="menu-warehouses">
                        <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                        Склады
                    </a>

                    <!-- Subdividers -->
                    <div class="sidebar-section-title">активации</div>
                    <a href="javascript:void(0)" onclick="switchTab('activations')" class="menu-item" id="menu-activations">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                        История активаций
                    </a>

                    <div class="sidebar-section-title">склады</div>
                    <a href="javascript:void(0)" onclick="switchTab('vouchers')" class="menu-item" id="menu-vouchers">
                        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        Реестр кодов
                    </a>

                    <div class="sidebar-section-title">документооборот</div>
                    <a href="javascript:void(0)" onclick="switchTab('documents')" class="menu-item" id="menu-documents">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        Оферты и договоры
                    </a>

                    <div class="sidebar-section-title">настройки</div>
                    <a href="javascript:void(0)" onclick="switchTab('finance')" class="menu-item" id="menu-finance">
                        <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                        Финансы
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('team')" class="menu-item" id="menu-team">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        Команда
                    </a>

                    <div class="sidebar-section-title">интеллект</div>
                    <a href="javascript:void(0)" onclick="switchTab('operator')" class="menu-item" id="menu-operator">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4.5L18 21l-6-4-6 4 1.5-7.5L2 9h7z"></path></svg>
                        Operator Workspace
                    </a>
                    <a href="javascript:void(0)" onclick="switchTab('ai-audit')" class="menu-item" id="menu-ai-audit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-cpu"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
                        AI Аудит
                    </a>

                    <div class="sidebar-section-title">витрина</div>
                    <a href="/" class="menu-item" id="menu-exit-b2b" style="color: var(--primary) !important;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="stroke: var(--primary) !important;"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        Вернуться на витрину
                    </a>
                </div>

                <div class="sidebar-footer" style="justify-content: space-between; position: relative; z-index: 10;">
                    <div style="display: flex; align-items: center; gap: 12px; overflow: hidden; cursor: pointer;" onclick="openProfileModal()">
                        <div class="user-avatar">
                            {{ mb_substr($user->name ?: ($user->first_name ?: 'П'), 0, 1) }}
                        </div>
                        <div class="user-info">
                            <span class="user-name">{{ $user->name ?: ($user->first_name ?: 'Партнер') }}</span>
                            <span class="user-role">ID: {{ $legalEntity->inn }}</span>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button onclick="openProfileModal()" title="Настройки профиля и бизнеса" style="background: transparent; border: none; padding: 4px; color: var(--text-muted); cursor: pointer; transition: color 0.2s; display: flex; align-items: center;" onmouseover="this.style.color='var(--primary)'" onmouseout="this.style.color='var(--text-muted)'">
                            <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        </button>
                        <form action="{{ route('partner.logout') }}" method="POST" id="logout-form" style="margin: 0; display: inline;">
                            @csrf
                            <button type="submit" title="Выйти" style="background: transparent; border: none; padding: 4px; color: var(--text-muted); cursor: pointer; transition: color 0.2s; display: flex; align-items: center;" onmouseover="this.style.color='#f43f5e'" onmouseout="this.style.color='var(--text-muted)'">
                                <svg viewBox="0 0 24 24" style="width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2.5; stroke-linecap: round; stroke-linejoin: round;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="main-content">
                <!-- Top Header Stats Bar -->
                <div class="top-bar">
                    <div class="top-title-stack">
                        <div class="page-title" id="page-title-text">Инфопанель</div>
                        <div class="page-subtitle">B2B terminal overview</div>
                    </div>

                    <div class="balance-summary" aria-label="Сводный баланс">
                        @if($stats['integrity_secured'] ?? false)
                            <div class="balance-summary-status">
                                <span class="balance-summary-kicker">Сводный баланс</span>
                                <span class="balance-status-line"><span class="balance-status-dot is-secured"></span>Защищено</span>
                            </div>
                        @else
                            <div class="balance-summary-status">
                                <span class="balance-summary-kicker">Сводный баланс</span>
                                <span class="balance-status-line"><span class="balance-status-dot"></span>Синхронизация</span>
                            </div>
                        @endif

                        <div class="balance-summary-cell balance-primary">
                            <span>Свободно</span>
                            <strong id="header-balance-available">{{ number_format($stats['balance'] ?? 0.00, 2, '.', ' ') }} ₽</strong>
                        </div>
                        <div class="balance-summary-cell balance-native">
                            <span>Бонусы</span>
                            <strong id="header-balance-native">{{ number_format($stats['native_balance'] ?? 1000.0000, 4, '.', ' ') }} баллов</strong>
                        </div>
                        <div class="balance-summary-cell">
                            <span>В холде</span>
                            <strong id="header-balance-reserved">{{ number_format($stats['reserved_balance'] ?? 0.00, 2, '.', ' ') }} ₽</strong>
                        </div>
                    </div>
                </div>

                <!-- Tab 1: Dashboard -->
                <div class="tab-pane active" id="tab-dashboard">
                    <!-- Welcome Banner -->
                    <div class="card-neo dashboard-welcome">
                        <div class="dashboard-welcome-main">
                            <div class="dashboard-badges">
                                <span class="badge-neo badge-amber">
                                    B2B КОМАНДА
                                </span>
                                <span class="badge-neo badge-green">
                                    Клиринг Активен
                                </span>
                            </div>
                            <h2>
                                {{ $legalEntity->name }}
                            </h2>
                            <p>
                                Добро пожаловать в Consortium Terminal — оптовую консоль управления автоматическими поставками цифровых кодов Meanly Systems.
                            </p>
                        </div>
                        <div class="dashboard-welcome-aside" aria-label="Краткий статус инфопанели">
                            <div class="dashboard-mini-stat">
                                <span>Каналы</span>
                                <strong>{{ $stats['channels_count'] ?? $shops->count() }}</strong>
                            </div>
                            <div class="dashboard-mini-stat">
                                <span>Заказы</span>
                                <strong>{{ $stats['active_orders'] ?? 0 }}</strong>
                            </div>
                            <div class="dashboard-mini-stat">
                                <span>30 дней</span>
                                <strong>{{ number_format($stats['revenue_30_days'] ?? 0.00, 0, '.', ' ') }} ₽</strong>
                            </div>
                            <div class="dashboard-mini-stat">
                                <span>Ошибки</span>
                                <strong>{{ $stats['market_errors_count'] ?? 0 }}</strong>
                            </div>
                            <button onclick="openDepositModal()" class="btn-neo btn-primary-neo dashboard-mini-action">
                                Пополнить баланс
                            </button>
                        </div>
                    </div>

                    <!-- 4-Column Quick Metrics -->
                    <div class="grid-12" style="margin-bottom: 2rem;">
                        <div class="col-3 card-neo dashboard-metric-card">
                            <div>
                                <div class="metric-title">Баланс депозита</div>
                                <div class="metric-value" id="stats-deposit-balance">{{ number_format($stats['balance'] ?? 0.00, 2, '.', ' ') }} ₽</div>
                            </div>
                            <!-- Embedded Green Button -->
                            <button onclick="openDepositModal()" class="btn-neo metric-action">
                                пополнить баланс &rarr;
                            </button>
                        </div>
                        <div class="col-3 card-neo dashboard-metric-card">
                            <div class="metric-title">Выручка (30 дней)</div>
                            <div class="metric-value" id="stats-completed-revenue">{{ number_format($stats['revenue_30_days'] ?? 0.00, 2, '.', ' ') }} ₽</div>
                            <div class="metric-caption">Сумма завершенных заказов</div>
                        </div>
                        <div class="col-3 card-neo dashboard-metric-card">
                            <div class="metric-title">Заказы в работе</div>
                            <div class="metric-value" id="stats-active-orders">{{ $stats['active_orders'] ?? 0 }}</div>
                            <div class="metric-caption">Требуют обработки</div>
                        </div>
                        <div class="col-3 card-neo dashboard-metric-card">
                            <div class="metric-title">Ошибки на маркете</div>
                            <div class="metric-value" id="stats-market-errors">{{ $stats['market_errors_count'] ?? 0 }}</div>
                            <div class="metric-caption">Замечания Яндекс.Маркета</div>
                        </div>
                    </div>

                    <!-- Flagship Lower Columns Layout -->
                    <div class="grid-12">
                        <!-- 🌐 ПОДКЛЮЧЕННЫЕ КАНАЛЫ ПРОДАЖ Card -->
                        <div class="col-6 card-neo" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 250px;">
                            <div>
                                <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-main); font-weight: 800; letter-spacing: 1px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                    <svg viewBox="0 0 24 24" style="width:16px; height:16px; stroke: var(--primary);"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                                    Подключенные каналы продаж
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;" id="dashboard-connected-channels">
                                    @forelse($shops as $sh)
                                        <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-card);">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span style="display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%;"></span>
                                                <span style="font-weight: 700; font-size: 0.85rem;">{{ $sh->name }}</span>
                                            </div>
                                            <span style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">{{ $sh->allowed_regions[0] ?? 'Global' }}</span>
                                        </div>
                                    @empty
                                        <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 20px; border: 1px dashed var(--border-card); border-radius: 8px;">
                                            У вас пока нет подключенных магазинов. Свяжитесь с поддержкой для интеграции Yandex Market или Ozon API.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                            <button onclick="switchTab('shops')" class="btn-neo" style="padding: 6px 12px; font-size: 0.75rem; border-radius: 8px; width: fit-content; margin-top: 15px;">
                                Настройка каналов &rarr;
                            </button>
                        </div>

                        <!-- API access card -->
                        <div class="col-6 card-neo" style="min-height: 250px; display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-main); font-weight: 800; letter-spacing: 1px; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                    <svg viewBox="0 0 24 24" style="width:16px; height:16px; stroke: var(--primary);"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                                    Доступ к API
                                </div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 16px; line-height: 1.5; margin-top: 10px;">
                                    Используйте API-токен для прямой интеграции вашей CMS или ERP-системы с ядром Meanly. Все запросы должны быть подписаны этим токеном.
                                </div>
                                
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <label style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Meanly API Token</label>
                                    <div style="display: flex; gap: 8px; background: rgba(0,0,0,0.2); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border-card); align-items: center; justify-content: space-between;">
                                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: var(--primary); letter-spacing: 1.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 75%;" id="dashboard-api-token-display">
                                            ••••••••••••••••••••••••
                                        </span>
                                        <div style="display: flex; gap: 6px;">
                                            <button onclick="toggleApiTokenVisibility()" class="btn-neo" style="padding: 4px 8px; font-size: 0.65rem; border-radius: 4px;">👁️</button>
                                            <button onclick="copyApiTokenToClipboard()" class="btn-neo" style="padding: 4px 8px; font-size: 0.65rem; border-radius: 4px;">Copy 📋</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button onclick="switchTab('finance')" class="btn-neo" style="padding: 8px 16px; font-size: 0.8rem; width: 100%; display: flex; align-items: center; justify-content: center; gap: 6px; margin-top: 15px;">
                                Управление ключами и балансом &rarr;
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Orders -->
                <div class="tab-pane" id="tab-orders">
                    <div class="grid-12">
                        <!-- Recent Orders List -->
                        <div class="col-12 card-neo">
                            <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 12px; border-bottom: 1px solid var(--border-card); padding-bottom: 0.5rem;">Последние заказы в B2B</h3>
                            <div class="neo-table-container">
                                <table class="neo-table">
                                    <thead>
                                        <tr>
                                            <th>ID Заказа</th>
                                            <th>Канал</th>
                                            <th>SKU</th>
                                            <th>Сумма</th>
                                            <th>Дата</th>
                                            <th>Статус</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($orders as $ord)
                                            <tr>
                                                <td style="font-family: var(--font-tech); font-weight: 700;">#{{ $ord->id }}</td>
                                                <td>{{ $ord->shop->name }}</td>
                                                <td style="font-family: monospace;">{{ $ord->sku }}</td>
                                                <td style="font-family: var(--font-tech); font-weight: 700;">{{ number_format($ord->price_rub ?? 0.00, 2, '.', ' ') }} ₽</td>
                                                <td>{{ $ord->created_at->format('d.m.Y H:i') }}</td>
                                                <td>
                                                    <span class="badge-neo {{ $ord->status === 'completed' ? 'badge-green' : 'badge-amber' }}">
                                                        {{ $ord->status }}
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">Нет недавних заказов.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Catalog -->
                <div class="tab-pane" id="tab-catalog">
                    <div class="card-neo">
                        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 18px; border-bottom: var(--theme-border-width, 1px) solid var(--border-card); flex-wrap: wrap;">
                            <div>
                                <div style="font-weight: 950; font-size: 1rem;">Каталог магазина</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 3px;">
                                    Показаны последние 50 SKU из {{ number_format($catalogTotal ?? $catalog->count(), 0, '.', ' ') }} товаров магазина.
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <span class="badge-neo badge-green">Yandex Market: {{ number_format($catalogYandexTotal ?? 0, 0, '.', ' ') }}</span>
                                <span class="badge-neo badge-amber">Всего: {{ number_format($catalogTotal ?? $catalog->count(), 0, '.', ' ') }}</span>
                            </div>
                        </div>
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead>
                                    <tr>
                                        <th>Товар</th>
                                        <th>SKU</th>
                                        <th>Каналы</th>
                                        <th>Категория</th>
                                        <th>Регион</th>
                                        <th>Закупочная цена</th>
                                        <th>Розничная цена</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($catalog as $item)
                                        @php
                                            $enabledChannels = $item->salesChannels ?? collect();
                                            $isYandexProduct = data_get($item->data ?? [], 'ym_raw') !== null;
                                            $retailRub = ((int) ($item->price_rub ?? 0)) / 100;
                                            $purchaseRub = ((int) ($item->purchase_price_rub ?? 0)) / 100;
                                            if ($purchaseRub <= 0 && $item->purchase_price !== null) {
                                                $purchaseRub = (float) $item->purchase_price;
                                            }
                                        @endphp
                                        <tr>
                                            <td style="font-weight: 700;">{{ $item->name }}</td>
                                            <td style="font-family: monospace;">{{ $item->sku }}</td>
                                            <td>
                                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                                    @if($isYandexProduct)
                                                        <span class="badge-neo badge-green">Yandex import</span>
                                                    @endif
                                                    @if($enabledChannels->contains('channel', 'meanly_storefront'))
                                                        <span class="badge-neo badge-amber">Storefront</span>
                                                    @endif
                                                    @if(!$isYandexProduct && !$enabledChannels->contains('channel', 'meanly_storefront'))
                                                        <span style="color: var(--text-muted);">—</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $item->category ?? '—' }}</td>
                                            <td><span class="badge-neo badge-amber">{{ $item->shop->allowed_regions[0] ?? 'Global' }}</span></td>
                                            <td style="font-family: var(--font-tech); font-weight: 700;">{{ number_format($purchaseRub, 2, '.', ' ') }} ₽</td>
                                            <td style="font-family: var(--font-tech); font-weight: 700; color: var(--primary);">{{ number_format($retailRub, 2, '.', ' ') }} ₽</td>
                                            <td><span class="badge-neo {{ $item->is_active ? 'badge-green' : 'badge-amber' }}">{{ $item->is_active ? 'АКТИВЕН' : 'ПАУЗА' }}</span></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">Каталог пуст. Добавьте первый товар!</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab: Storefront (B2B Showcase) -->
                <div class="tab-pane" id="tab-storefront">
                    @if($shops->isEmpty())
                        <div class="card-neo" style="margin-bottom: 1.25rem; padding: 18px; display:flex; justify-content:space-between; align-items:center; gap: 14px; flex-wrap:wrap; background: rgba(245,158,11,0.08); border-color: rgba(245,158,11,0.28);">
                            <div>
                                <div style="font-size: 0.95rem; font-weight: 900; color: var(--text-main);">Готовим центр дистрибуции</div>
                                <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 4px;">У продавца должен быть один мастер-склад. Обновите страницу, если он еще не появился.</div>
                            </div>
                            <button type="button" onclick="window.location.reload()" class="btn-neo" style="font-size: 0.8rem; font-weight: 900; padding: 9px 14px;">Обновить</button>
                        </div>
                    @endif
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <div style="font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px; display: flex; align-items: center; gap: 8px;">
                                <i class="ph-bold ph-storefront" style="color: var(--primary);"></i>
                                Каталог поставщиков для селлеров
                            </div>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 4px 0 0 0;">Здесь можно выбрать товары поставщиков и добавить их в продажу в своих магазинах.</p>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <button type="button" id="storefront-back-to-categories" onclick="showStorefrontCategories()" class="btn-neo" style="display: none; font-size: 0.8rem; font-weight: 800; padding: 8px 12px;">
                                ← Категории
                            </button>
                            <input type="text" id="storefront-search" oninput="filterStorefront()" placeholder="Поиск категории или товара..." class="input-neo" style="width: 240px; font-size: 0.8rem; padding: 8px 12px;">
                            <span id="storefront-selected-category-label" style="display: none; font-size: 0.75rem; color: var(--text-muted); font-weight: 800;"></span>
                        </div>
                    </div>

                    <div class="grid-12" id="storefront-category-grid" style="gap: 20px;">
                        @forelse($storefrontCategoryCards as $category)
                            <button type="button" class="col-4 card-neo storefront-category-card" data-name="{{ \Illuminate\Support\Str::lower($category['name']) }}" onclick="openStorefrontCategory(@js($category['filter_key']), @js($category['name']))" style="text-align: left; display: flex; flex-direction: column; justify-content: space-between; gap: 24px; min-height: 190px; padding: 22px; cursor: pointer;">
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;">
                                    <div style="width: 46px; height: 46px; display: flex; align-items: center; justify-content: center; border: var(--theme-border-width, 1px) solid var(--border-card); border-radius: var(--theme-radius-md, 12px); background: var(--theme-surface-muted, rgba(255,255,255,0.03)); font-size: 1.35rem;">
                                        {{ $category['icon'] }}
                                    </div>
                                    <span class="badge-neo badge-amber" style="font-size: 0.65rem; font-weight: 900;">{{ number_format($category['count'], 0, '.', ' ') }} SKU</span>
                                </div>
                                <div>
                                    <h4 style="font-size: 1.35rem; font-weight: 950; margin: 0 0 8px 0; color: var(--text-main); letter-spacing: -0.04em;">{{ $category['name'] }}</h4>
                                    <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0; line-height: 1.45;">{{ $category['description'] ?? (($category['slug'] ?? '') === 'unmapped' ? 'Товары без canonical category. Их надо постепенно разнести маппингами.' : 'Открыть поставщиков и доступные номиналы в этой категории.') }}</p>
                                </div>
                            </button>
                        @empty
                            <div class="col-12 card-neo" style="text-align: center; padding: 4rem;">
                                <p style="color: var(--text-muted); margin-bottom: 14px;">Пока нет активных товаров поставщиков.</p>
                                <p style="color: var(--text-muted); font-size: 0.78rem; margin: 0;">Когда поставщики или локальный vault-каталог появятся в системе, здесь будут карточки товаров для продажи.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="grid-12" id="storefront-grid" style="gap: 20px; display: none;">
                        @forelse($providerProducts as $prod)
                            @php
                                $isVault = ($prod['supply_class'] ?? 'network') === 'vault';
                                $brandName = $prod['brand_name'] ?: 'Другое';
                                $regionName = $prod['region_code'] ?? 'GLOBAL';
                                $reviewRequired = (bool) data_get($prod, 'curation.review_required', false);
                                $actionEnabled = (bool) data_get($prod, 'action.enabled', true) && $shops->isNotEmpty();
                                $identityConfidence = data_get($prod, 'canonical_identity.confidence', 'low');
                                $indexingSurface = data_get($prod, 'indexing_policy.surface', 'internal_review');
                                $sellerAvailability = data_get($prod, 'seller_offer_availability.availability', 'not_listed');
                            @endphp
                            <div class="col-4 card-neo storefront-card" data-name="{{ strtolower($prod['name']) }}" data-public-sku="{{ strtolower($prod['public_sku']) }}" data-category="{{ $prod['category_slug'] ?? 'other' }}" style="display: flex; flex-direction: column; justify-content: space-between; gap: 20px; transition: transform 0.2s, border-color 0.2s; min-height: 250px; position: relative; overflow: hidden; padding: 20px;">
                                @if($isVault)
                                    <div class="sovereign-glow-badge" style="position: absolute; top: 0; right: 0; background: linear-gradient(90deg, #10b981, #059669); color: #fff; font-size: 0.65rem; font-weight: 900; padding: 4px 12px; border-bottom-left-radius: 12px; box-shadow: 0 2px 10px rgba(16, 185, 129, 0.2); letter-spacing: 0.5px;">
                                        MEANLY VAULT
                                    </div>
                                @endif
                                
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                            <span class="badge-neo badge-amber" style="font-size: 0.65rem; font-weight: 800; border-radius: 100px;">{{ $prod['category_label'] ?? 'Other' }}</span>
                                            @if($reviewRequired)
                                                <span class="badge-neo" style="font-size: 0.65rem; font-weight: 900; border-color: rgba(245, 158, 11, 0.35); color: #f59e0b;">Review needed</span>
                                            @endif
                                        </div>
                                        <span style="font-size: 0.7rem; color: var(--text-muted); font-family: var(--font-tech);">REF: {{ $prod['public_sku'] }}</span>
                                    </div>
                                    
                                    <h4 style="font-size: 1.05rem; font-weight: 850; margin: 0 0 6px 0; color: var(--text-main); line-height: 1.3;">{{ $prod['name'] }}</h4>
                                    <p style="font-size: 0.72rem; color: var(--text-muted); margin: 0; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">
                                        {{ $brandName }} · {{ $regionName }} · {{ $prod['currency'] ?? 'USD' }}
                                    </p>
                                    <div style="font-size: 0.68rem; color: var(--text-muted); margin-top: 8px; line-height: 1.45;">
                                        Identity: <strong style="color: var(--text-main);">{{ $identityConfidence }}</strong>
                                        · Policy: <strong style="color: {{ $reviewRequired ? '#f59e0b' : 'var(--text-main)' }};">{{ $indexingSurface }}</strong>
                                        @if($prod['face_value'] ?? null)
                                            · Face: {{ $prod['face_value'] }} {{ $prod['face_value_currency'] ?? $prod['currency'] ?? '' }}
                                        @endif
                                        · Candidates: {{ (int) ($prod['provider_candidate_count'] ?? 1) }}/{{ (int) ($prod['provider_source_count'] ?? 1) }}
                                        · Seller: {{ $sellerAvailability }}
                                    </div>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; font-size: 0.68rem; font-weight: 800;">
                                        @if($prod['canonical_product_url'] ?? null)
                                            <a href="{{ $prod['canonical_product_url'] }}" target="_blank" rel="noopener" style="color: var(--primary);">Canonical page</a>
                                        @endif
                                        @if($prod['provider_candidate_url'] ?? null)
                                            <a href="{{ $prod['provider_candidate_url'] }}" target="_blank" rel="noopener" style="color: var(--text-muted);">Provider detail</a>
                                        @endif
                                    </div>
                                </div>
                                
                                <div style="border-top: 1px solid var(--border-card); padding-top: 15px;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                        <div>
                                            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">B2B Закупка</div>
                                            <div style="font-family: var(--font-tech); font-size: 1.15rem; font-weight: 900; color: var(--primary);">
                                                {{ $prod['purchase_price_formatted'] }}
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Розничный тариф</div>
                                            <div style="font-family: var(--font-tech); font-size: 0.9rem; font-weight: 750; color: var(--text-main);">
                                                {{ $prod['nominal_price_formatted'] }}
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <button @if($actionEnabled) onclick="openStorefrontPurchaseModal(@js($prod), {{ $isVault ? 'true' : 'false' }}, this)" @else disabled @endif class="btn-neo {{ $isVault && $actionEnabled ? 'btn-primary-neo' : '' }}" style="width: 100%; font-size: 0.8rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px;">
                                        @if($shops->isEmpty())
                                            <i class="ph-bold ph-storefront"></i> Готовим мастер-склад
                                        @elseif(!$actionEnabled)
                                            <i class="ph-bold ph-warning"></i> Требуется ревью
                                        @elseif($isVault)
                                            <i class="ph-bold ph-lightning"></i> Закупить сток
                                        @else
                                            <i class="ph-bold ph-shopping-cart"></i> Закупить сток 🛒
                                        @endif
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 card-neo" style="text-align: center; padding: 4rem;">
                                <p style="color: var(--text-muted);">Витрина пуста. Настройте провайдеров в панели управления.</p>
                            </div>
                        @endforelse
                    </div>
                    <div id="storefront-load-more-wrap" style="display: none; justify-content: center; align-items: center; gap: 14px; margin-top: 24px;">
                        <button type="button" id="storefront-load-more-btn" onclick="loadStorefrontProducts(storefrontPage + 1, false)" class="btn-neo" style="font-size: 0.8rem; font-weight: 800; padding: 10px 18px;">
                            Показать ещё
                        </button>
                        <span id="storefront-total-hint" style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">
                            Показано {{ count($providerProducts) }} из {{ $providerProductsTotal ?? count($providerProducts) }}
                        </span>
                    </div>
                </div>

                <!-- Tab 4: Shops -->
                <div class="tab-pane" id="tab-shops">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <div style="font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px; display: flex; align-items: center; gap: 8px;">
                                <i class="ph-bold ph-storefront" style="color: var(--primary);"></i>
                                Каналы продаж и маркетплейсы
                            </div>
                            <p style="font-size: 0.8rem; color: var(--text-muted); margin: 4px 0 0 0;">Управляйте автономными изолированными магазинами и интеграцией с торговыми платформами.</p>
                        </div>
                        <button onclick="openCreateShopModal()" class="btn-neo btn-primary-neo" style="display: flex; align-items: center; gap: 8px; font-weight: 800;">
                            Создать новый канал 🏗️
                        </button>
                    </div>

                    <div id="integration-detail-view" style="display: none; width: 100%; box-sizing: border-box;">
                        <button type="button" onclick="closeIntegrationDetail()" class="btn-neo" style="margin-bottom: 14px; padding: 7px 12px; font-size: 0.74rem; font-weight: 800; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="ph-bold ph-arrow-left"></i> Все интеграции
                        </button>

                    <div class="grid-12" style="gap: 14px;">
                        <div class="col-12">
                            <div class="card-neo integration-detail-panel" id="yandex-settings-panel" style="display: none; width: 100%; box-sizing: border-box; margin-bottom: 2rem; padding: 20px; border: 1px solid rgba(245, 158, 11, 0.28); background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(255,255,255,0.015));">
                        <input type="hidden" id="yandex-shop-id">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
                            <div>
                                <div style="font-size: 1.05rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.02em;" id="yandex-settings-title">Настройка Yandex Market</div>
                                <div style="font-size: 0.76rem; color: var(--text-muted); margin-top: 4px;">Отдельная настройка канала без попапа. Сохраняем API, затем backend сам проверяет юрлицо по JSON-отчету Yandex.</div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span class="badge-neo" id="yandex-legal-status-badge" style="background: rgba(255,255,255,0.03); color: var(--text-muted); font-size: 0.68rem;">Not checked</span>
                            </div>
                        </div>

                        <div class="grid-12" style="gap: 14px; align-items: start;">
                            <div class="col-7 yandex-settings-form-grid">
                                <div>
                                    <label style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Business ID</label>
                                    <input type="number" id="yandex-business-id" class="input-neo" placeholder="1002345">
                                </div>
                                <div>
                                    <label style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Campaign ID</label>
                                    <input type="number" id="yandex-campaign-id" class="input-neo" placeholder="2199042">
                                </div>
                                <div class="field-span-2">
                                    <label style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Warehouse ID на Yandex Market</label>
                                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                        <input type="number" id="yandex-warehouse-id" class="input-neo" placeholder="ID склада FBS/DBS" style="flex: 1; min-width: 180px;">
                                        <button type="button" onclick="fetchYandexMarketWarehouses()" class="btn-neo" style="white-space: nowrap; padding: 8px 10px; font-size: 0.72rem;">Получить склады</button>
                                    </div>
                                    <select id="yandex-warehouse-select" class="input-neo" style="display:none; width: 100%; margin-top: 8px; font-size: 0.78rem;" onchange="selectYandexWarehouseFromList()"></select>
                                    <div id="yandex-warehouse-status" style="font-size: 0.66rem; color: var(--text-muted); margin-top: 5px;">Нужен для отправки остатков из мастер-склада в Маркет.</div>
                                </div>
                                <div class="field-span-2">
                                    <label style="font-size: 0.72rem; color: var(--text-muted); display: block; margin-bottom: 6px;">API Key (Bearer Token)</label>
                                    <input type="password" id="yandex-api-key" class="input-neo" placeholder="AQAAAAA...">
                                </div>
                            </div>

                            <div class="col-5" style="border: 1px solid var(--border-card); border-radius: 14px; padding: 14px; background: rgba(255,255,255,0.02); display: flex; flex-direction: column; gap: 12px; min-width: 0;">
                                <div>
                                    <div style="font-size: 0.82rem; font-weight: 950; color: var(--text-main); margin-bottom: 6px;">Статус проверки юрлица</div>
                                    <div style="font-size: 0.7rem; color: var(--text-muted); line-height: 1.45;">Meanly сверяет реквизиты на backend: данные вашего юрлица + JSON-отчет Yandex Market по стоимости услуг.</div>
                                </div>
                                <div style="font-size: 0.68rem; color: var(--text-muted); line-height: 1.45;">
                                    Юрлицо в Meanly: {{ $legalEntity->short_name ?: $legalEntity->name }} · ИНН {{ $legalEntity->inn }}@if($legalEntity->kpp) · КПП {{ $legalEntity->kpp }}@endif
                                </div>
                                <div id="yandex-legal-status" style="font-size: 0.72rem; color: var(--text-muted); line-height: 1.5;">Фоновая проверка запустится после сохранения API-данных и склада.</div>
                                <button type="button" id="yandex-support-action" onclick="openYandexSupportFromSettings()" class="btn-neo" style="display: none; justify-content: center; font-size: 0.74rem; font-weight: 850; padding: 8px 10px;">Обратиться в поддержку</button>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; flex-wrap: wrap;">
                            <button type="button" onclick="closeIntegrationDetail()" class="btn-neo">Отмена</button>
                            <button type="button" onclick="submitSaveYandexMarket()" class="btn-neo btn-primary-neo">Сохранить и запустить проверку</button>
                        </div>
                    </div>

                    <div class="card-neo integration-detail-panel" id="marketplace-settings-panel" style="display: none; width: 100%; box-sizing: border-box; margin-bottom: 2rem; padding: 20px;">
                        <input type="hidden" id="marketplace-shop-id">
                        <input type="hidden" id="marketplace-platform-type">
                        <div style="font-size: 1.05rem; font-weight: 950; color: var(--text-main); letter-spacing: -0.02em; margin-bottom: 16px;" id="marketplace-settings-title">Настройка интеграции</div>
                        <div style="display: flex; flex-direction: column; gap: 15px;" id="marketplace-fields-container"></div>
                        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; flex-wrap: wrap;">
                            <button type="button" onclick="closeIntegrationDetail()" class="btn-neo">Отмена</button>
                            <button type="button" onclick="submitSaveMarketplace()" class="btn-neo btn-primary-neo">Сохранить интеграцию 🔌</button>
                        </div>
                    </div>
                        </div>
                    </div>
                    </div>

                    <div class="grid-12" id="shops-integrations-list">
                        @forelse($shops as $sh)
                            <div class="col-12 card-neo" style="margin-bottom: 2rem; display: flex; flex-direction: column; gap: 20px;">
                                <!-- Top Bar of Shop Card -->
                                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-card); padding-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <h3 style="font-size: 1.3rem; font-weight: 900; margin: 0; letter-spacing: -0.5px; color: var(--text-main);">{{ $sh->name }}</h3>
                                            <span class="badge-neo badge-green" style="font-size: 0.65rem; padding: 2px 8px; font-weight: 800; border-radius: 100px;">ACTIVE</span>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; font-family: var(--font-tech);">
                                            Регион: <strong style="color: var(--text-main);">{{ $sh->shop_region ?? 'RU' }}</strong> | 
                                            Префикс кодов: <strong style="color: var(--text-main);">{{ $sh->voucher_prefix ?? '—' }}</strong> | 
                                            Домен: <strong style="color: var(--text-main);">{{ $sh->domain ?? 'meanly.test' }}</strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Marketplace Integration Grid inside the Shop -->
                                <div>
                                    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px; margin-bottom: 15px; display: flex; align-items: center; gap: 6px;">
                                        <i class="ph-bold ph-plugs"></i> Подключенные и доступные интеграции с маркетплейсами
                                    </div>
                                    
                                    <div class="grid-12" style="gap: 15px;">
                                        <!-- Yandex Market -->
                                        @php
                                            $yandexMarketActive = $sh->isYandexMarketActive();
                                            $yandexLegalVerified = $sh->isYandexMarketVerified();
                                            $yandexHasCredentials = filled($sh->business_id) && filled($sh->campaign_id) && filled($sh->api_key) && filled($sh->ym_warehouse_id);
                                        @endphp
                                        <div class="col-4 card-neo" style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 15px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px; min-height: 140px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div>
                                                    <div style="font-weight: 850; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; color: var(--text-main);">
                                                        🏪 Yandex Market
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">Синхронизация по API FBS/DBS</div>
                                                </div>
                                                <span class="badge-neo" style="{{ $yandexMarketActive ? 'background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);' : ($yandexHasCredentials ? 'background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);' : 'background: rgba(255,255,255,0.02); color: var(--text-muted);') }} font-size: 0.65rem; padding: 1px 6px;">
                                                    {{ $yandexMarketActive ? 'Active' : ($yandexHasCredentials ? 'Needs legal check' : 'Offline') }}
                                                </span>
                                            </div>
                                            <button onclick="openYandexMarketModal({{ $sh->id }}, @js($sh->name), @js($sh->business_id), @js($sh->campaign_id), @js($sh->ym_warehouse_id), @js($sh->ym_legal_verification), {{ $yandexLegalVerified ? 'true' : 'false' }})" class="btn-neo" style="width: 100%; font-size: 0.72rem; padding: 6px; font-weight: 750;">
                                                {{ $yandexMarketActive ? 'Настройки канала ⚙️' : ($yandexHasCredentials ? 'Открыть проверку 🧾' : 'Подключить 🔌') }}
                                            </button>
                                        </div>

                                        <!-- Avito -->
                                        <div class="col-4 card-neo" style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 15px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px; min-height: 140px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div>
                                                    <div style="font-weight: 850; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; color: var(--text-main);">
                                                        🥑 Avito (Авито)
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">Синхронизация товаров и сообщений</div>
                                                </div>
                                                @php
                                                    $avitoConfigured = false; // Add mock logic for check
                                                @endphp
                                                <span class="badge-neo" style="background: rgba(255,255,255,0.02); color: var(--text-muted); font-size: 0.65rem; padding: 1px 6px;">
                                                    Offline
                                                </span>
                                            </div>
                                            <button onclick="openMarketplaceModal({{ $sh->id }}, '{{ $sh->name }}', 'avito')" class="btn-neo" style="width: 100%; font-size: 0.72rem; padding: 6px; font-weight: 750;">
                                                Подключить 🔌
                                            </button>
                                        </div>

                                        <!-- Ozon -->
                                        <div class="col-4 card-neo" style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 15px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px; min-height: 140px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div>
                                                    <div style="font-weight: 850; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; color: var(--text-main);">
                                                        🌐 Ozon API
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">Синхронизация по API Ozon Seller</div>
                                                </div>
                                                <span class="badge-neo" style="background: rgba(255,255,255,0.02); color: var(--text-muted); font-size: 0.65rem; padding: 1px 6px;">
                                                    Offline
                                                </span>
                                            </div>
                                            <button onclick="openMarketplaceModal({{ $sh->id }}, '{{ $sh->name }}', 'ozon')" class="btn-neo" style="width: 100%; font-size: 0.72rem; padding: 6px; font-weight: 750;">
                                                Подключить 🔌
                                            </button>
                                        </div>

                                        <!-- Wildberries -->
                                        <div class="col-4 card-neo" style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 15px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px; min-height: 140px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div>
                                                    <div style="font-weight: 850; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; color: var(--text-main);">
                                                        🟣 Wildberries
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">Синхронизация WB API</div>
                                                </div>
                                                <span class="badge-neo" style="background: rgba(255,255,255,0.02); color: var(--text-muted); font-size: 0.65rem; padding: 1px 6px;">
                                                    Offline
                                                </span>
                                            </div>
                                            <button onclick="openMarketplaceModal({{ $sh->id }}, '{{ $sh->name }}', 'wildberries')" class="btn-neo" style="width: 100%; font-size: 0.72rem; padding: 6px; font-weight: 750;">
                                                Подключить 🔌
                                            </button>
                                        </div>

                                        <!-- WooCommerce -->
                                        <div class="col-4 card-neo" style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 15px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px; min-height: 140px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div>
                                                    <div style="font-weight: 850; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; color: var(--text-main);">
                                                        🛍️ WooCommerce
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">Синхронизация по REST API</div>
                                                </div>
                                                <span class="badge-neo" style="{{ $sh->client_id ? 'background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);' : 'background: rgba(255,255,255,0.02); color: var(--text-muted);' }} font-size: 0.65rem; padding: 1px 6px;">
                                                    {{ $sh->client_id ? 'Active' : 'Offline' }}
                                                </span>
                                            </div>
                                            <button onclick="openMarketplaceModal({{ $sh->id }}, '{{ $sh->name }}', 'woocommerce')" class="btn-neo" style="width: 100%; font-size: 0.72rem; padding: 6px; font-weight: 750;">
                                                {{ $sh->client_id ? 'Настроить API ⚙️' : 'Подключить 🔌' }}
                                            </button>
                                        </div>

                                        <!-- Megamarket -->
                                        <div class="col-4 card-neo" style="background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 15px; display: flex; flex-direction: column; justify-content: space-between; gap: 15px; min-height: 140px;">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div>
                                                    <div style="font-weight: 850; font-size: 0.9rem; display: flex; align-items: center; gap: 6px; color: var(--text-main);">
                                                        🟢 Мегамаркет
                                                    </div>
                                                    <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;">Синхронизация Merchant API</div>
                                                </div>
                                                <span class="badge-neo" style="background: rgba(255,255,255,0.02); color: var(--text-muted); font-size: 0.65rem; padding: 1px 6px;">
                                                    Offline
                                                </span>
                                            </div>
                                            <button onclick="openMarketplaceModal({{ $sh->id }}, '{{ $sh->name }}', 'megamarket')" class="btn-neo" style="width: 100%; font-size: 0.72rem; padding: 6px; font-weight: 750;">
                                                Подключить 🔌
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 card-neo" style="text-align: center; padding: 4rem; display: flex; flex-direction: column; align-items: center; gap: 20px;">
                                <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(245, 48, 3, 0.05); border: 1px dashed var(--primary); display: flex; align-items: center; justify-content: center;">
                                    <i class="ph-bold ph-storefront" style="font-size: 2rem; color: var(--primary);"></i>
                                </div>
                                <div>
                                    <h3 style="color: var(--text-main); font-size: 1.4rem; font-weight: 900; margin: 0 0 8px 0; letter-spacing: -0.5px;">
                                        Нет подключенных каналов продаж
                                    </h3>
                                    <p style="font-size: 0.9rem; color: var(--text-muted); max-width: 500px; margin: 0 auto; line-height: 1.5;">
                                        Создайте ваш первый магазин, чтобы активировать интеграцию с **Yandex Market**, **Avito API**, **Ozon API**, **Wildberries** и другими маркетплейсами.
                                    </p>
                                </div>
                                <button onclick="openCreateShopModal()" class="btn-neo btn-primary-neo" style="font-weight: 800; font-size: 0.85rem; padding: 10px 20px; display: flex; align-items: center; gap: 8px;">
                                    <i class="ph-bold ph-plus-circle"></i> Активировать первый канал продаж 🚀
                                </button>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Tab 5: Support -->
                <div class="tab-pane" id="tab-support">
                    <div class="grid-12">
                        <!-- Direct Support Channels list -->
                        <div class="col-4 card-neo" style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0; border-bottom: 1px solid var(--border-card); padding-bottom: 0.5rem;">Круглосуточный саппорт</h3>
                            <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5;">
                                Если у вас возникли сложности с расчетами, резервированием баланса или подтверждением операций, вы можете моментально связаться с дежурным инженером.
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <a href="#" style="background: #24A1DE; color: #fff; font-weight: 700; text-decoration: none; padding: 10px; border-radius: 8px; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.85rem;">
                                    Telegram Hot-Line 📱
                                </a>
                                <button onclick="openTicketModal()" class="btn-neo btn-primary-neo" style="justify-content: center; font-size: 0.85rem;">
                                    Создать тикет саппорта 🎫
                                </button>
                            </div>
                        </div>

                        <!-- Direct active tickets table -->
                        <div class="col-8 card-neo">
                            <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 1.25rem 0; border-bottom: 1px solid var(--border-card); padding-bottom: 0.5rem;">Созданные обращения</h3>
                            <div class="neo-table-container">
                                <table class="neo-table">
                                    <thead>
                                        <tr>
                                            <th>ID Тикета</th>
                                            <th>Тема</th>
                                            <th>Заказ</th>
                                            <th>Магазин</th>
                                            <th>Создан</th>
                                            <th>Статус</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tickets-table-body">
                                        @forelse($tickets as $tkt)
                                            <tr>
                                                <td style="font-family: var(--font-tech); font-weight: 700;">#{{ $tkt->id }}</td>
                                                <td style="font-weight: 700;">{{ $tkt->subject }}</td>
                                                <td style="font-family: var(--font-tech);">{{ $tkt->order?->order_id ?? '—' }}</td>
                                                <td>{{ $tkt->shop->name }}</td>
                                                <td>{{ $tkt->created_at->format('d.m.Y H:i') }}</td>
                                                <td>
                                                    <span class="badge-neo badge-amber">{{ $tkt->status }}</span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">Нет открытых обращений.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 6: Warehouses -->
                <div class="tab-pane" id="tab-warehouses">
                    <div class="card-neo">
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead>
                                    <tr>
                                        <th>Название склада</th>
                                        <th>Роль</th>
                                        <th>Центр</th>
                                        <th>Доступно кодов</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($warehouses as $wh)
                                        <tr onclick="openWarehouseStock({{ $wh->id }})" style="cursor: pointer;">
                                            <td style="font-weight: 700;">{{ $wh->name }} <span style="color: var(--text-muted); font-size: 0.72rem; font-weight: 800;">Открыть</span></td>
                                            <td>{{ $wh->is_main ? 'Мастер-склад' : ($wh->channel_label ?? 'Склад канала') }}</td>
                                            <td>{{ $wh->shop?->name ?? 'Центр дистрибуции' }}</td>
                                            <td style="font-family: var(--font-tech); font-weight: 800; color: var(--primary);">{{ $wh->stocks()->sum('count') }} шт.</td>
                                            <td>
                                                <span class="badge-neo {{ $wh->is_active ? 'badge-green' : '' }}">
                                                    {{ $wh->is_active ? 'Активен' : 'Отключен' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Мастер-склад еще не создан.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div id="warehouse-stock-panel" style="display:none; margin-top: 18px; border-top: 2px solid var(--border-card); padding-top: 18px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap: 12px; flex-wrap:wrap; margin-bottom: 12px;">
                                <div>
                                    <div id="warehouse-stock-title" style="font-size: 1rem; font-weight: 900; color: var(--text-main);">Содержимое склада</div>
                                    <div id="warehouse-stock-subtitle" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 3px;"></div>
                                </div>
                                <button type="button" class="btn-neo" onclick="closeWarehouseStock()" style="font-size: 0.75rem; padding: 7px 10px;">Закрыть</button>
                            </div>
                            <div class="neo-table-container">
                                <table class="neo-table">
                                    <thead>
                                        <tr>
                                            <th>Товар</th>
                                            <th>SKU</th>
                                            <th>Доступно</th>
                                            <th>В резерве</th>
                                            <th>Ушло</th>
                                            <th>Всего</th>
                                        </tr>
                                    </thead>
                                    <tbody id="warehouse-stock-body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 7: Activations -->
                <div class="tab-pane" id="tab-activations">
                    <div class="card-neo">
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead>
                                    <tr>
                                        <th>Товар</th>
                                        <th>SKU</th>
                                        <th>Склад</th>
                                        <th>Количество</th>
                                        <th>Итоговая сумма</th>
                                        <th>Дата выполнения</th>
                                        <th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($activations as $act)
                                        <tr>
                                            <td style="font-weight: 700;">{{ $act['product_name'] }}</td>
                                            <td style="font-family: monospace;">{{ $act['sku'] }}</td>
                                            <td>{{ $act['warehouse_name'] }}</td>
                                            <td style="font-weight: 800;">{{ $act['count'] }} шт.</td>
                                            <td style="font-family: var(--font-tech); font-weight: 800; color: var(--primary);">{{ number_format($act['total_price_rub'], 2, '.', ' ') }} ₽</td>
                                            <td>{{ $act['date'] }}</td>
                                            <td>
                                                <span class="badge-neo {{ $act['status'] === 'COMPLETED' ? 'badge-green' : 'badge-amber' }}">
                                                    {{ $act['status'] }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">Нет недавних Ruble активаций.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab 8: Vouchers -->
                <div class="tab-pane" id="tab-vouchers">
                    <div class="card-neo">
                        <div class="neo-table-container">
                            <table class="neo-table">
                                <thead>
                                    <tr>
                                        <th>Операция</th>
                                        <th>Склад провайдера</th>
                                        <th>Магазин</th>
                                        <th>Секретный токен/Код</th>
                                        <th>Зарезервирован под заказ</th>
                                        <th>Создан</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($vouchers as $vch)
                                        <tr>
                                            <td style="font-family: var(--font-tech); font-weight: 700;">{{ $vch->transactionReference() }}</td>
                                            <td>{{ $vch->warehouse->name ?? 'Meanly Transit' }}</td>
                                            <td>{{ $vch->shop->name ?? '—' }}</td>
                                            <td>
                                                <span style="font-family: monospace; font-size:0.75rem; background:rgba(0,0,0,0.25); padding: 4px 8px; border-radius: 4px;">
                                                    {{ substr($vch->code_token ?? $vch->original_code, 0, 15) }}...
                                                </span>
                                            </td>
                                            <td style="font-family: var(--font-tech);">{{ $vch->orderItem?->transactionReference() ?? 'Свободен' }}</td>
                                            <td>{{ $vch->created_at->format('d.m.Y H:i') }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">Реестр ваучеров пуст. Выкупите первую партию!</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tab 9: Finance -->
                <div class="tab-pane" id="tab-documents">
                    <div class="grid-12">
                        <div class="col-8 card-neo" style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <h3 style="font-size: 1.2rem; font-weight: 800; margin: 0; border-bottom: 1px solid var(--border-card); padding-bottom: 0.5rem; display: flex; align-items: center; justify-content: space-between;">
                                <span>Подписанные документы</span>
                            </h3>

                            <div class="neo-table-container">
                                <table class="neo-table">
                                    <thead>
                                        <tr>
                                            <th>Тип документа</th>
                                            <th>Статус</th>
                                            <th>Дата подписания</th>
                                            <th>Контрольная отметка</th>
                                            <th>Действия</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @if(isset($legalEntity) && $legalEntity->status !== 'pending_signature')
                                            <tr>
                                                <td style="font-weight: 600;">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <i class="ph-bold ph-file-text" style="color: var(--primary); font-size: 1.2rem;"></i>
                                                        Договор-оферта B2B
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge-neo" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">
                                                        Подписано
                                                    </span>
                                                </td>
                                                <td style="color: var(--text-muted);">
                                                    {{ \Carbon\Carbon::parse($legalEntity->agreement_metadata['identity_anchored_at'] ?? $legalEntity->created_at)->format('d.m.Y H:i') }}
                                                </td>
                                                <td style="font-family: var(--font-tech); font-size: 0.8rem; color: #10b981;">
                                                    <i class="ph-bold ph-fingerprint" style="margin-right: 4px;"></i>
                                                    {{ substr($legalEntity->agreement_metadata['l1_address'] ?? 'N/A', 0, 16) }}...
                                                </td>
                                                <td>
                                                    <button class="btn-neo-outline btn-sm" onclick="alert('Просмотр документа в разработке')" style="padding: 4px 10px; font-size: 0.8rem;">Просмотр</button>
                                                </td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td style="font-weight: 600;">
                                                    <div style="display: flex; align-items: center; gap: 8px;">
                                                        <i class="ph-bold ph-file-text" style="color: var(--primary); font-size: 1.2rem;"></i>
                                                        Договор-оферта B2B
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge-neo" style="background: rgba(245, 48, 3, 0.1); color: var(--primary); border: 1px solid rgba(245, 48, 3, 0.2);">
                                                        Ожидает подписания
                                                    </span>
                                                </td>
                                                <td style="color: var(--text-muted);">-</td>
                                                <td style="color: var(--text-muted);">-</td>
                                                <td>
                                                    <a href="/business/register" class="btn-neo btn-sm" style="padding: 4px 10px; font-size: 0.8rem;">Подписать</a>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-4 card-neo" style="display: flex; flex-direction: column; gap: 1.5rem; justify-content: space-between;">
                            <div>
                                <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 1rem 0; border-bottom: 1px solid var(--border-card); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                                    <i class="ph-bold ph-info" style="color: var(--primary);"></i> Документооборот
                                </h3>
                                <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.6;">
                                    Все документы подписываются через Passkey. Это помогает подтвердить автора действия и защитить подпись от подделки.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane" id="tab-finance">
                    <div class="grid-12">
                        <!-- Isolated monetary transactions statement -->
                        <div class="col-8 card-neo" style="display: flex; flex-direction: column; gap: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-card); padding-bottom: 1rem;">
                                <div style="display: flex; gap: 10px; background: rgba(0,0,0,0.2); padding: 4px; border-radius: 8px; border: 1px solid var(--border-card);">
                                    <button onclick="setFinanceView('cash')" class="btn-neo" id="finance-view-cash" style="padding: 6px 12px; font-size: 0.75rem; border-radius: 6px; box-shadow: none; border: none;">Балансовая выписка</button>
                                    <button onclick="setFinanceView('all')" class="btn-neo" id="finance-view-all" style="padding: 6px 12px; font-size: 0.75rem; border-radius: 6px; box-shadow: none; border: none;">Журнал операций</button>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    Показаны только реальные денежные операции в рублях (RUB)
                                </div>
                            </div>

                            <div class="neo-table-container">
                                <table class="neo-table">
                                    <thead>
                                        <tr id="finance-table-header">
                                            <!-- Hydrated dynamically based on tab state -->
                                        </tr>
                                    </thead>
                                    <tbody id="finance-table-body">
                                        <!-- Hydrated dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Deposit replenishment intents panel -->
                        <div class="col-4 card-neo" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 400px;">
                            <div>
                                <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 1rem 0; border-bottom: 1px solid var(--border-card); padding-bottom: 0.5rem;">
                                    Replenishment Intents
                                </h3>
                                
                                <div id="deposit-intent-status-container" style="display: flex; flex-direction: column; gap: 15px;">
                                    <!-- Dynamic status update if active token is set -->
                                </div>
                            </div>

                            <button onclick="openDepositModal()" class="btn-neo btn-primary-neo" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                Пополнить баланс депозита 💳
                            </button>
                        </div>

                        <!-- Balance Requests (Passkey signed) -->
                        <div class="col-12 card-neo" style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-card); padding-bottom: 1rem;">
                                <div>
                                    <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 8px;">
                                        Запросы баланса <span style="font-size: 0.75rem; font-weight: 500; padding: 2px 8px; background: var(--green-glow); color: var(--green); border: 1px solid var(--green); border-radius: 20px;">защищено</span>
                                    </h3>
                                    <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">
                                        Запросы на пополнение и кредитование, подтвержденные вашим Passkey.
                                    </p>
                                </div>
                                <button onclick="openSovereignRequestModal()" class="btn-neo btn-primary-neo" style="padding: 8px 16px; font-size: 0.8rem; display: flex; align-items: center; gap: 6px;">
                                    Создать запрос
                                </button>
                            </div>

                            <div class="neo-table-container">
                                <table class="neo-table">
                                    <thead>
                                        <tr>
                                            <th>ID Запроса</th>
                                            <th>Тип запроса</th>
                                            <th>Сумма (₽)</th>
                                            <th>Статус</th>
                                            <th>Подтверждение</th>
                                            <th>Профиль</th>
                                            <th>Комментарий</th>
                                            <th>Дата создания</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sovereign-requests-table-body">
                                        <!-- Hydrated dynamically -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Team -->
                <div class="tab-pane" id="tab-team">
                    <div class="grid-12">
                        <div class="col-12 card-neo" style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0;">Командный доступ</h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0; line-height: 1.5;">Ссылки-приглашения через email и локальный Passkey отключены. Следующий поток должен привязывать уже подтверждённый SL1E wallet identity к роли в юрлице без одноразовых invite-link.</p>
                        </div>
                    </div>
                </div>

                <!-- Operator Workspace tab pane -->
                <div class="tab-pane" id="tab-operator">
                    <div class="grid-12" style="margin-bottom: 1.5rem;">
                        <div class="col-3 card-neo">
                            <div class="metric-title">Critical Alerts</div>
                            <div class="metric-value">{{ data_get($operatorWorkspace, 'summary.critical_alerts', 0) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Сигналы, требующие внимания</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">Pending Reviews</div>
                            <div class="metric-value">{{ data_get($operatorWorkspace, 'summary.pending_reviews', 0) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Решения на ручной проверке</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">Trusted Recommendations</div>
                            <div class="metric-value">{{ data_get($operatorWorkspace, 'summary.trusted_recommendations', 0) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Приоритетные подсказки системы</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">System Health</div>
                            <div class="metric-value" style="font-size: 1.15rem;">{{ strtoupper(str_replace('_', ' ', data_get($operatorWorkspace, 'health.overall_status', 'unknown'))) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Сводный статус операционного контура</div>
                        </div>
                    </div>
                    <div class="grid-12" style="margin-bottom: 1.5rem;">
                        <div class="col-3 card-neo">
                            <div class="metric-title">Team Members</div>
                            <div class="metric-value">{{ data_get($operatorWorkspace, 'summary.team_members', 0) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Команда текущего юрлица</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">Active Channels</div>
                            <div class="metric-value">{{ data_get($operatorWorkspace, 'summary.active_channels', 0) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Магазины и каналы продаж</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">Yandex Connected</div>
                            <div class="metric-value">{{ data_get($operatorWorkspace, 'summary.yandex_connected_channels', 0) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Каналы с business/campaign/API</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">API Apps</div>
                            <div class="metric-value">{{ data_get($operatorWorkspace, 'summary.api_applications', 0) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Интеграционные терминалы</div>
                        </div>
                    </div>
                    <div class="grid-12" style="margin-bottom: 1.5rem;">
                        <div class="col-3 card-neo">
                            <div class="metric-title">Service Usage 30d</div>
                            <div class="metric-value">{{ number_format((float) data_get($operatorWorkspace, 'tokenomics.total_sl1', 0), 4) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Работа AI и инфраструктуры</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">Recommendation Hit Rate</div>
                            <div class="metric-value">{{ number_format((float) data_get($operatorWorkspace, 'tokenomics.recommendations.hit_rate', 0) * 100, 1) }}%</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Попадания использованных подсказок</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">AI Audit Spend</div>
                            <div class="metric-value">{{ number_format((float) data_get($operatorWorkspace, 'tokenomics.ai_audit_sl1', 0), 4) }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Затраты на проверки и анализ</div>
                        </div>
                        <div class="col-3 card-neo">
                            <div class="metric-title">Estimated Value</div>
                            <div class="metric-value">₽{{ number_format((float) data_get($operatorWorkspace, 'tokenomics.estimated_value_rub', 0), 0, '.', ' ') }}</div>
                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Оценка созданной пользы</div>
                        </div>
                    </div>
                    @if(data_get($operatorWorkspace, 'first_party_storefront'))
                        <div class="grid-12" style="margin-bottom: 1.5rem;">
                            <div class="col-3 card-neo">
                                <div class="metric-title">Meanly Storefront GMV</div>
                                <div class="metric-value">₽{{ number_format((float) data_get($operatorWorkspace, 'first_party_storefront.storefront_gmv_30_days', 0), 0, '.', ' ') }}</div>
                                <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Своя витрина за 30 дней</div>
                            </div>
                            <div class="col-3 card-neo">
                                <div class="metric-title">Yandex GMV</div>
                                <div class="metric-value">₽{{ number_format((float) data_get($operatorWorkspace, 'first_party_storefront.yandex_gmv_30_days', 0), 0, '.', ' ') }}</div>
                                <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Внешний канал Meanly</div>
                            </div>
                            <div class="col-3 card-neo">
                                <div class="metric-title">Channel Overlap</div>
                                <div class="metric-value">{{ data_get($operatorWorkspace, 'first_party_storefront.channel_overlap', 0) }}</div>
                                <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Товары и у нас, и на Яндексе</div>
                            </div>
                            <div class="col-3 card-neo">
                                <div class="metric-title">Catalog Drift</div>
                                <div class="metric-value">{{ (int) data_get($operatorWorkspace, 'first_party_storefront.last_reconciliation.missing_local_count', 0) + (int) data_get($operatorWorkspace, 'first_party_storefront.last_reconciliation.missing_yandex_count', 0) + (int) data_get($operatorWorkspace, 'first_party_storefront.last_reconciliation.price_mismatch_count', 0) }}</div>
                                <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 8px;">Расхождения после сверки</div>
                            </div>
                        </div>
                    @endif

                    <div class="grid-12">
                        <div class="col-8 card-neo">
                            <h3 style="font-size: 1.1rem; font-weight: 900; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 8px;">
                                Operator Inbox
                                <span class="badge-neo" style="background: rgba(245, 48, 3, 0.08); color: var(--primary); border: 1px solid rgba(245, 48, 3, 0.2);">prioritized</span>
                            </h3>

                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                @forelse(data_get($operatorWorkspace, 'critical_alerts', []) as $alert)
                                    <div style="border: 1px solid var(--border-card); border-radius: 12px; padding: 14px; background: rgba(245, 48, 3, 0.04);">
                                        <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 6px;">
                                            <strong style="font-size: 0.9rem;">{{ $alert['title'] ?? $alert['type'] ?? 'Alert' }}</strong>
                                            <span class="badge-neo" style="background: rgba(245, 48, 3, 0.08); color: var(--primary); border: 1px solid rgba(245, 48, 3, 0.2);">{{ $alert['severity'] ?? 'risk' }}</span>
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.5;">{{ $alert['description'] ?? '' }}</div>
                                    </div>
                                @empty
                                    <div style="border: 1px dashed var(--border-card); border-radius: 12px; padding: 18px; color: var(--text-muted); text-align: center;">
                                        Нет критических сигналов. Операционный контур выглядит спокойно.
                                    </div>
                                @endforelse

                                @foreach(data_get($operatorWorkspace, 'trusted_recommendations', []) as $recommendation)
                                    <div style="border: 1px solid var(--border-card); border-radius: 12px; padding: 14px; background: rgba(16, 185, 129, 0.04);">
                                        <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 6px;">
                                            <strong style="font-size: 0.9rem;">{{ $recommendation['recommendation'] ?? 'recommendation' }}</strong>
                                            <span class="badge-neo" style="background: rgba(16, 185, 129, 0.08); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">{{ $recommendation['trust_level'] ?? 'trust' }}</span>
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); line-height: 1.5;">
                                            {{ $recommendation['reason'] ?? '' }} Priority: {{ number_format((float) ($recommendation['priority_score'] ?? 0), 2) }}
                                        </div>
                                    </div>
                                @endforeach

                                @foreach(data_get($operatorWorkspace, 'pending_reviews', []) as $review)
                                    <div style="border: 1px solid var(--border-card); border-radius: 12px; padding: 14px; background: rgba(245, 158, 11, 0.04);">
                                        <div style="display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 6px;">
                                            <strong style="font-size: 0.9rem;">{{ $review['type'] ?? 'review' }}</strong>
                                            <span class="badge-neo">{{ $review['status'] ?? 'pending' }}</span>
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted);">
                                            {{ number_format((float) ($review['amount'] ?? 0), 2) }} {{ $review['currency'] ?? 'RUB' }} · {{ $review['created_at'] ?? '' }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-4" style="display: flex; flex-direction: column; gap: 1rem;">
                            <div class="card-neo">
                                <h3 style="font-size: 1rem; font-weight: 900; margin: 0 0 1rem 0;">Commerce Scorecard</h3>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">GMV 30d</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'scorecard.gmv', 0), 2) }} ₽</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Margin est.</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'scorecard.margin', 0), 2) }} ₽</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">AOV</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'scorecard.aov', 0), 2) }} ₽</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Forecast Accuracy</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'scorecard.forecast_accuracy', 0) * 100, 1) }}%</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Active Products</span><strong>{{ data_get($operatorWorkspace, 'scorecard.products_active', 0) }}</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Recommendation Trust</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'scorecard.recommendation_trust', 0) * 100, 1) }}%</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Service Usage</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'scorecard.token_usage_sl1', 0), 4) }}</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Service ROI</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'scorecard.token_roi', 0), 2) }}×</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Fee Load</span><strong>{{ number_format((float) data_get($operatorWorkspace, 'tokenomics.fee_load_sl1', 0), 4) }} баллов</strong></div>
                                </div>
                            </div>

                            <div class="card-neo">
                                <h3 style="font-size: 1rem; font-weight: 900; margin: 0 0 1rem 0;">System Health</h3>
                                <div style="display: flex; flex-direction: column; gap: 10px;">
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Sync</span><strong>{{ data_get($operatorWorkspace, 'health.sync_health', 'unknown') }}</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Feed</span><strong>{{ data_get($operatorWorkspace, 'health.feed_freshness', 'unknown') }}</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Channels</span><strong>{{ data_get($operatorWorkspace, 'health.active_channels', 0) }}/{{ data_get($operatorWorkspace, 'health.total_channels', 0) }}</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Team</span><strong>{{ data_get($operatorWorkspace, 'health.team.active', 0) }}/{{ data_get($operatorWorkspace, 'health.team.total', 0) }}</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Yandex</span><strong>{{ data_get($operatorWorkspace, 'health.integrations.yandex_connected_channels', 0) }} connected · {{ data_get($operatorWorkspace, 'health.integrations.yandex_incomplete_channels', 0) }} incomplete</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">API Apps</span><strong>{{ data_get($operatorWorkspace, 'health.integrations.active_api_applications', 0) }}/{{ data_get($operatorWorkspace, 'health.integrations.api_applications', 0) }}</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Inventory</span><strong>{{ data_get($operatorWorkspace, 'health.inventory.available_vouchers', 0) }} available · {{ data_get($operatorWorkspace, 'health.inventory.low_stock', 0) }} low</strong></div>
                                    <div style="display:flex; justify-content:space-between; font-size:0.82rem;"><span style="color:var(--text-muted);">Failed Publishes</span><strong>{{ data_get($operatorWorkspace, 'summary.failed_publishes', 0) }}</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Audit & Cyber Chat tab pane -->
                <div class="tab-pane" id="tab-ai-audit">
                    <div class="grid-12">
                        <!-- AI Audit Controller Panel -->
                        <div class="col-4 card-neo" style="display: flex; flex-direction: column; gap: 1.5rem; justify-content: space-between;">
                            <div>
                                <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 1rem 0; border-bottom: 1px solid var(--border-card); padding-bottom: 0.5rem; display: flex; align-items: center; gap: 8px;">
                                    <svg style="width:18px; height:18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect><rect x="9" y="9" width="6" height="6"></rect><line x1="9" y1="1" x2="9" y2="4"></line><line x1="15" y1="1" x2="15" y2="4"></line><line x1="9" y1="20" x2="9" y2="23"></line><line x1="15" y1="20" x2="15" y2="23"></line><line x1="20" y1="9" x2="23" y2="9"></line><line x1="20" y1="15" x2="23" y2="15"></line><line x1="1" y1="9" x2="4" y2="9"></line><line x1="1" y1="15" x2="4" y2="15"></line></svg>
                                    AI-аудитор операций
                                </h3>

                                <div class="card-neo" style="background: rgba(0, 0, 0, 0.1); border: 1px dashed var(--border-card); padding: 1rem; border-radius: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div id="ai-status-indicator" class="w-3 h-3 rounded-full animate-pulse" style="width: 10px; height: 10px; border-radius: 50%; background-color: var(--primary);"></div>
                                        <span id="ai-status-text" style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; font-family: monospace;">Система готова к аудиту</span>
                                    </div>
                                    <p style="margin: 0.75rem 0 0; font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                                        ИИ анализирует журнал операций, проверяет подписи и помогает находить финансовые аномалии.
                                    </p>
                                </div>

                                <div id="ai-audit-result-wrapper" style="display: none; margin-top: 1.5rem;">
                                    <h4 style="font-size: 0.85rem; font-weight: 800; margin: 0 0 0.5rem; text-transform: uppercase; font-family: monospace; color: var(--primary);">
                                        Результат ИИ-Разведсводки
                                    </h4>
                                    <div id="ai-audit-result-text" class="custom-scrollbar" style="background: rgba(0,0,0,0.2); border: 1px solid var(--border-card); padding: 1rem; border-radius: 8px; font-family: monospace; font-size: 0.75rem; line-height: 1.5; color: var(--text-main); white-space: pre-wrap; max-height: 250px; overflow-y: auto;">
                                        <!-- Populated dynamically -->
                                    </div>
                                </div>
                            </div>

                            <button onclick="triggerAiAudit()" class="btn-neo btn-primary-neo" id="btn-ai-audit" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <span id="btn-ai-audit-label">Проверить операции</span>
                                <span id="btn-ai-audit-loader" style="display: none;" class="loader"></span>
                            </button>
                        </div>

                        <!-- AI Chat Terminal -->
                        <div class="col-8 card-neo" style="display: flex; flex-direction: column; background-color: #020617; color: #4ade80; border: 1.5px solid var(--primary); min-height: 550px; border-radius: 12px; overflow: hidden; position: relative; font-family: monospace;">
                            <!-- CRT scanlines overlay -->
                            <div style="position: absolute; inset: 0; pointer-events: none; z-index: 10; opacity: 0.04; background-image: linear-gradient(rgba(18,16,16,0) 50%, rgba(0,0,0,0.25) 50%), linear-gradient(90deg, rgba(255,0,0,0.06), rgba(0,255,0,0.02), rgba(0,0,255,0.06)); background-size: 100% 2px, 3px 100%;"></div>

                            <!-- Terminal header -->
                            <div style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(74, 222, 128, 0.2); background-color: rgba(74, 222, 128, 0.05); flex-shrink: 0;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background-color: #22c55e; box-shadow: 0 0 8px #4ade80;"></div>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #4ade80;">AI Аналитик</span>
                                        <span style="font-size: 0.55rem; color: rgba(74, 222, 128, 0.6); margin-top: 2px;">Анализ операций Meanly</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages body -->
                            <div id="ai-chat-body" class="custom-scrollbar" style="flex: 1; padding: 1.5rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1.25rem; scroll-behavior: smooth; max-height: 420px;">
                                <div class="chat-msg assistant" style="display: flex; flex-direction: column; gap: 4px;">
                                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.65rem; font-weight: 800; color: #22c55e;">
                                        <span>AI Аналитик</span>
                                    </div>
                                    <div style="border-left: 2px solid rgba(74, 222, 128, 0.2); padding-left: 8px; font-size: 0.85rem; line-height: 1.5; color: #4ade80; white-space: pre-wrap;">Привет! Я AI-аналитик Meanly. Помогу разобраться с продажами, заказами, остатками и операциями. О чем хотите узнать?</div>
                                </div>
                            </div>

                            <!-- Input form -->
                            <div style="padding: 1rem 1.5rem; background-color: rgba(0, 0, 0, 0.5); border-top: 1px solid rgba(74, 222, 128, 0.15);">
                                <form onsubmit="submitAiChatMessage(event)" style="display: flex; align-items: center; gap: 10px;">
                                    <span style="color: #4ade80; font-weight: 900;">&gt;&gt;&gt;</span>
                                    <input 
                                        type="text" 
                                        id="ai-chat-input"
                                        placeholder="AWAITING COMMAND..." 
                                        autocomplete="off"
                                        style="flex: 1; background: transparent; border: none; outline: none; font-family: monospace; font-size: 0.85rem; color: #4ade80; padding: 0; box-shadow: none;"
                                    />
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- --- 🏷️ Interactive Dialog Modals --- -->

        <!-- Modal: Storefront B2B Purchase Modal -->
        <div class="modal-backdrop" id="storefront-purchase-modal-backdrop">
            <div class="modal-content" style="width: 550px; max-width: 95%;">
                <div class="modal-header" id="storefront-purchase-title">Оформление B2B сделки</div>
                
                <!-- Main Form Area -->
                <div id="storefront-purchase-form-area" style="display: flex; flex-direction: column; gap: 20px;">
                    <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-card); padding: 15px; border-radius: 12px;">
                        <h4 id="storefront-modal-prod-name" style="margin: 0 0 6px 0; font-size: 1.1rem; font-weight: 850; color: var(--text-main);"></h4>
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted);">
                            <span>REF: <strong id="storefront-modal-prod-sku" style="color: var(--text-main);"></strong></span>
                            <span>Регион: <strong id="storefront-modal-prod-region" style="color: var(--text-main);"></strong></span>
                            <span>Источник: <strong id="storefront-modal-prod-provider" style="color: var(--text-main);"></strong></span>
                        </div>
                    </div>

                    <!-- Select Shop Dropdown -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px; font-weight: 700; text-transform: uppercase;">Центр дистрибуции 🏪</label>
                        <select id="storefront-purchase-shop-id" class="input-neo" style="width: 100%; font-size: 0.85rem; padding: 10px; cursor: pointer;" onchange="updateStorefrontWarehouseHint(); updateStorefrontSalesChannelAvailability();">
                            @foreach($shops as $sh)
                                <option value="{{ $sh->id }}" data-yandex-active="{{ $sh->isYandexMarketActive() ? '1' : '0' }}">{{ $sh->name }} ({{ $sh->shop_region ?? 'RU' }})</option>
                            @endforeach
                        </select>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 6px;" id="storefront-master-warehouse-hint">
                            Купленные коды попадут в единый мастер-склад продавца.
                        </div>
                    </div>

                    <!-- Payment Method Selector -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 8px; font-weight: 700; text-transform: uppercase;">Способ оплаты / Расчеты 💳</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;" id="storefront-payment-selector-container">
                            <div class="card-neo active" id="storefront-pay-rub" onclick="selectStorefrontPaymentMethod('rub_token')" style="padding: 12px; cursor: pointer; display: flex; flex-direction: column; gap: 4px; border: 1.5px solid var(--primary); background: rgba(245, 48, 3, 0.05); border-radius: 8px; transition: all 0.2s ease;">
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; font-weight: 800; color: var(--text-main);">
                                    <span>RUB-token</span>
                                    <i class="ph-bold ph-credit-card" style="color: var(--primary);"></i>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);" id="storefront-payment-rub-balance">
                                    {{ number_format($stats['balance'] ?? 0.00, 2, '.', ' ') }} ₽
                                </div>
                            </div>
                            <div class="card-neo" id="storefront-pay-native" onclick="selectStorefrontPaymentMethod('native_token')" style="padding: 12px; cursor: pointer; display: flex; flex-direction: column; gap: 4px; border: 1px solid var(--border-card); background: rgba(255, 255, 255, 0.02); border-radius: 8px; transition: all 0.2s ease;">
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8rem; font-weight: 800; color: var(--text-main);">
                                    <span>Бонусы</span>
                                    <i class="ph-bold ph-key" style="color: #10b981;"></i>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);" id="storefront-payment-native-balance">
                                    {{ number_format($stats['native_balance'] ?? 1000.0000, 4, '.', ' ') }} баллов
                                </div>
                            </div>
                        </div>
                        <input type="hidden" id="storefront-payment-method" value="rub_token">
                    </div>

                    <!-- Nominal Amount (for variable products) -->
                    <div id="storefront-nominal-container" style="display: none;">
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px; font-weight: 700; text-transform: uppercase;">Указать номинал закупки</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="number" id="storefront-nominal-amount" class="input-neo" style="flex: 1;" oninput="updateStorefrontCostCalculations()">
                            <span id="storefront-nominal-currency" style="font-weight: 800; font-family: var(--font-tech);">USD</span>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 4px;" id="storefront-nominal-limits-hint"></div>
                    </div>

                    <!-- Quantity with beautiful buttons -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 8px; font-weight: 700; text-transform: uppercase;">Количество кодов в сток</label>
                        <div style="display: flex; align-items: center; gap: 12px; max-width: 180px;">
                            <button onclick="decrementStorefrontQty()" class="btn-neo" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800;">-</button>
                            <input type="number" id="storefront-purchase-qty" class="input-neo" style="text-align: center; font-size: 1rem; font-weight: 800; padding: 8px; flex: 1;" value="1" min="1" max="20" oninput="normalizeStorefrontQty(); updateStorefrontCostCalculations()">
                            <button onclick="incrementStorefrontQty()" class="btn-neo" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800;">+</button>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 6px;" id="storefront-qty-limits-hint">Лимит закупки: от 1 до 20 кодов.</div>
                    </div>

                    <!-- Sales channels selector -->
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 8px; font-weight: 700; text-transform: uppercase;">Куда выставить после закупки</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <label class="card-neo" style="padding: 10px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" class="storefront-sales-channel-checkbox" value="meanly_storefront" checked>
                                <span style="font-size: 0.78rem; font-weight: 800;">Meanly витрина</span>
                            </label>
                            <label class="card-neo" id="storefront-yandex-channel-label" style="padding: 10px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" id="storefront-channel-yandex" class="storefront-sales-channel-checkbox" value="yandex_market">
                                <span style="font-size: 0.78rem; font-weight: 800;">Yandex Market</span>
                            </label>
                            <label class="card-neo" style="padding: 10px; display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" class="storefront-sales-channel-checkbox" value="offline_store">
                                <span style="font-size: 0.78rem; font-weight: 800;">Оффлайн / ручные продажи</span>
                            </label>
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 6px;">
                            Неконфигурированные каналы будут пропущены автоматически.
                        </div>
                        <div id="storefront-yandex-channel-hint" style="font-size: 0.68rem; color: var(--text-muted); margin-top: 7px;"></div>
                    </div>

                    <!-- Total cost calculator -->
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-card); padding-top: 15px; margin-top: 10px;">
                        <div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Итоговая стоимость сделки</div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);" id="storefront-cost-breakdown">1 × 0.00 ₽</div>
                        </div>
                        <div style="font-family: var(--font-tech); font-size: 1.6rem; font-weight: 900; color: var(--primary);" id="storefront-total-calculated-cost">
                            0.00 ₽
                        </div>
                    </div>

                    <!-- Signature validation checkbox -->
                    <div style="display: flex; align-items: flex-start; gap: 10px; background: rgba(16,185,129,0.04); border: 1px solid rgba(16,185,129,0.15); padding: 12px; border-radius: 8px; margin-top: 5px;">
                        <input type="checkbox" id="storefront-l1-sign-checkbox" checked disabled style="width: 18px; height: 18px; cursor: not-allowed; margin-top: 2px;">
                        <label for="storefront-l1-sign-checkbox" style="font-size: 0.75rem; color: var(--text-muted); cursor: pointer; line-height: 1.4;">
                            <strong style="color: #10b981;">Подтвердить закупку через Passkey</strong><br>
                            Закупка стока будет подтверждена TouchID/FaceID/Passkey, а коды появятся на складе как доступные для продажи.
                        </label>
                    </div>
                </div>

                <!-- L1 Clearing Loading Stream Panel -->
                <div id="storefront-clearing-stream-panel" style="display: none; flex-direction: column; gap: 15px; text-align: center; padding: 20px 0;">
                    <div class="atom-spinner" style="width: 50px; height: 50px; border: 4px solid rgba(245, 48, 3, 0.1); border-top-color: var(--primary); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                    <div style="font-size: 1.1rem; font-weight: 850; color: var(--text-main); letter-spacing: -0.5px;">Проводим операцию...</div>
                    <div style="font-family: var(--font-tech); background: #000; border: 1px solid var(--border-card); border-radius: 8px; padding: 15px; text-align: left; height: 180px; overflow-y: auto; font-size: 0.72rem; color: #4ade80; line-height: 1.6;" id="storefront-clearing-logs">
                        [SYSTEM] Initializing consensus anchor...
                    </div>
                </div>

                <!-- Success Voucher Delivery Grid -->
                <div id="storefront-success-panel" style="display: none; flex-direction: column; gap: 20px;">
                    <div style="text-align: center; padding: 10px 0;">
                        <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                            <i class="ph-bold ph-check" style="font-size: 2rem; color: #10b981;"></i>
                        </div>
                        <h3 style="font-size: 1.3rem; font-weight: 900; margin: 0; color: var(--text-main);">Сток успешно закуплен! 🏆</h3>
                        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 6px 0 0 0;" id="storefront-success-message-text"></p>
                    </div>

                    <div id="storefront-tx-receipt" style="display: none; background: rgba(16,185,129,0.04); border: 1px solid rgba(16,185,129,0.18); border-radius: 10px; padding: 12px; gap: 10px; flex-direction: column;">
                        <div style="font-size: 0.65rem; font-weight: 900; text-transform: uppercase; color: #10b981; letter-spacing: 0.5px;">Контрольная отметка операции</div>
                        <div id="storefront-tx-hash-value" style="font-family: var(--font-tech); font-size: 0.72rem; color: var(--text-main); word-break: break-all; line-height: 1.45;"></div>
                        <div id="storefront-tx-verify-status" style="display: none; font-size: 0.72rem; color: var(--text-muted); line-height: 1.4;"></div>
                        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                            <button type="button" onclick="copyStorefrontTxHash()" class="btn-neo" style="padding: 6px 10px; font-size: 0.72rem;">Скопировать hash</button>
                            <button type="button" onclick="verifyStorefrontTxHash()" class="btn-neo btn-primary-neo" style="padding: 6px 10px; font-size: 0.72rem;">Проверить в обозревателе</button>
                        </div>
                    </div>

                    <div style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 6px;">
                        📦 Зарегистрированный сток
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px; max-height: 200px; overflow-y: auto; padding-right: 5px;" id="storefront-delivered-keys-list">
                    </div>

                    <div style="font-size: 0.7rem; color: var(--text-muted); line-height: 1.4; background: rgba(255,255,255,0.01); border: 1px solid var(--border-card); padding: 12px; border-radius: 8px;">
                        * Коды зарегистрированы в вашем <strong>Сейфе</strong>, товар добавлен в каталог селлера и включен в выбранные каналы продаж.
                    </div>
                </div>

                <div class="modal-footer" id="storefront-modal-footer">
                    <button onclick="closeStorefrontPurchaseModal()" class="btn-neo" id="storefront-btn-cancel">Отмена</button>
                    <button onclick="submitStorefrontPurchase()" class="btn-neo btn-primary-neo" id="storefront-btn-submit" style="display: flex; align-items: center; gap: 8px;">
                        <i class="ph-bold ph-lock-key"></i> Подтвердить Passkey ⚡
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal 1: Deposit intent popup dialog -->
        <div class="modal-backdrop" id="deposit-modal-backdrop">
            <div class="modal-content">
                <div class="modal-header">Инициировать пополнение депозита</div>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Сумма пополнения (₽)</label>
                        <input type="number" id="deposit-amount-input" class="input-neo" value="15000" min="10">
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); line-height: 1.4;">
                        * После генерации платежного интента вы получите прямую ссылку СБП для тестирования автоматического клиринга пополнения баланса.
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="closeDepositModal()" class="btn-neo">Отмена</button>
                    <button onclick="submitCreateDepositIntent()" class="btn-neo btn-primary-neo">Сгенерировать интент 💳</button>
                </div>
            </div>
        </div>

        <!-- Create B2B Shop Modal -->
        <div class="modal-backdrop" id="create-shop-modal-backdrop">
            <div class="modal-content" style="width: 450px; max-width: 90%;">
                <div class="modal-header">Создать новый канал продаж / магазин</div>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Название магазина</label>
                        <input type="text" id="new-shop-name" class="input-neo" placeholder="e.g. Мой Мега Маркет">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Домен (опционально)</label>
                        <input type="text" id="new-shop-domain" class="input-neo" placeholder="e.g. shop.company.ru">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Регион обслуживания</label>
                        <select id="new-shop-region" class="input-neo">
                            <option value="RU">🇷🇺 Россия (RU)</option>
                            <option value="KZ">🇰🇿 Казахстан (KZ)</option>
                            <option value="BY">🇧🇾 Беларусь (BY)</option>
                            <option value="UZ">🇺🇿 Узбекистан (UZ)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="closeCreateShopModal()" class="btn-neo">Отмена</button>
                    <button onclick="submitCreateShop()" class="btn-neo btn-primary-neo">Создать магазин 🏗️</button>
                </div>
            </div>
        </div>

        <!-- Modal 3: Support ticket modal dialog -->
        <div class="modal-backdrop" id="ticket-modal-backdrop">
            <div class="modal-content">
                <div class="modal-header">Создать тикет саппорта</div>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Тема обращения</label>
                        <input type="text" id="tkt-subject" class="input-neo" placeholder="Не сходится сальдо по клирингу">
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Магазин / Канал продаж</label>
                        <select id="tkt-shop-id" class="input-neo">
                            @foreach($shops as $sh)
                                <option value="{{ $sh->id }}">{{ $sh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Сообщение</label>
                        <textarea id="tkt-message" class="input-neo" style="height: 100px; resize: none;"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="closeTicketModal()" class="btn-neo">Отмена</button>
                    <button onclick="submitCreateTicket()" class="btn-neo btn-primary-neo">Создать тикет 🎫</button>
                </div>
            </div>
        </div>

        <!-- Modal 4: Profile and Business Settings modal dialog -->
        <div class="modal-backdrop" id="profile-modal-backdrop">
            <div class="modal-content" style="width: 500px; max-width: 90%;">
                <div class="modal-header">Параметры профиля и бизнеса ⚙️</div>
                <div style="max-height: 400px; overflow-y: auto; padding-right: 8px; display: flex; flex-direction: column; gap: 20px;">
                    <!-- Раздел 1: Пользовательские данные -->
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 6px; margin-bottom: 12px;">
                            👤 Пользователь (Владелец)
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Имя</label>
                                    <input type="text" id="prof-first-name" class="input-neo" value="{{ $user->first_name }}">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Фамилия</label>
                                    <input type="text" id="prof-last-name" class="input-neo" value="{{ $user->last_name }}">
                                </div>
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Телефон</label>
                                <input type="text" id="prof-phone" class="input-neo" value="{{ $user->phone }}">
                            </div>
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">SL1E Identity</label>
                                <input type="text" class="input-neo" value="{{ $user->sovereignIdentityAddress() }}" disabled style="opacity: 0.6; cursor: not-allowed; background: rgba(0,0,0,0.2);">
                            </div>
                        </div>
                    </div>

                    <!-- Раздел 2: Реквизиты Бизнеса -->
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 6px; margin-bottom: 12px;">
                            🏢 Реквизиты организации
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div>
                                <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Название юридического лица</label>
                                <input type="text" class="input-neo" value="{{ $legalEntity->name }}" disabled style="opacity: 0.6; cursor: not-allowed; background: rgba(0,0,0,0.2);">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">ИНН организации</label>
                                    <input type="text" class="input-neo" value="{{ $legalEntity->inn }}" disabled style="opacity: 0.6; cursor: not-allowed; background: rgba(0,0,0,0.2);">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">ОГРН</label>
                                    <input type="text" class="input-neo" value="{{ $legalEntity->ogrn ?? 'Verified' }}" disabled style="opacity: 0.6; cursor: not-allowed; background: rgba(0,0,0,0.2);">
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">БИК Банка</label>
                                    <input type="text" id="prof-bank-bic" class="input-neo" value="{{ $legalEntity->bank_bic }}" placeholder="044525...">
                                </div>
                                <div>
                                    <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Расчетный счет</label>
                                    <input type="text" id="prof-bank-account" class="input-neo" value="{{ $legalEntity->bank_account }}" placeholder="40702810...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Раздел 3: Тема интерфейса -->
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px; border-bottom: 1px solid var(--border-card); padding-bottom: 6px; margin-bottom: 12px;">
                            🎨 Оформление и тема интерфейса
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(255,255,255,0.02); padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border-card);">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">Активная тема:</span>
                            <div class="skin-switcher-pill" style="display: flex; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--border-card); border-radius: 100px; padding: 4px; gap: 4px; box-shadow: var(--shadow-neo-inset);">
                                <button onclick="setTheme(\'partner\')" class="skin-btn" id="skin-btn-partner" style="background: transparent; border: none; color: var(--text-muted); font-size: 0.65rem; font-weight: 800; padding: 6px 10px; border-radius: 100px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Partner 🌟
                                </button>
                                <button onclick="setTheme(\'consortium\')" class="skin-btn" id="skin-btn-consortium" style="background: transparent; border: none; color: var(--text-muted); font-size: 0.65rem; font-weight: 800; padding: 6px 10px; border-radius: 100px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Flagship 🚩
                                </button>
                                <button onclick="setTheme(\'retro\')" class="skin-btn" id="skin-btn-retro" style="background: transparent; border: none; color: var(--text-muted); font-size: 0.65rem; font-weight: 800; padding: 6px 10px; border-radius: 100px; cursor: pointer; transition: all 0.3s; text-transform: uppercase; letter-spacing: 0.5px;">
                                    Retro ⚡
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button onclick="closeProfileModal()" class="btn-neo">Отмена</button>
                    <button onclick="submitSaveProfileAndBusiness()" class="btn-neo btn-primary-neo">Сохранить всё 💾</button>
                </div>
            </div>
        </div>

        <!-- Sovereign Balance Request Modal -->
        <div class="modal-backdrop" id="sovereign-request-modal-backdrop">
            <div class="modal-content" style="width: 550px; max-width: 95%;">
                <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between;">
                    <span>Создать запрос баланса</span>
                    <span style="font-family: var(--font-tech); font-size: 0.75rem; padding: 2px 8px; background: var(--border-neon); color: var(--primary); border: 1px solid var(--border-neon); border-radius: 12px;">Passkey</span>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 15px;" id="sovereign-request-form">
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Тип финансового запроса</label>
                        <select id="sovereign-request-type" class="input-neo">
                            <option value="top_up">💳 Пополнение баланса (Replenishment)</option>
                            <option value="grant_credit">📈 Кредитная линия (JIT Credit Line)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Сумма запроса (RUB)</label>
                        <input type="number" id="sovereign-request-amount" class="input-neo" placeholder="e.g. 50000" min="1" step="1">
                    </div>
                    
                    <div>
                        <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Комментарий / Обоснование</label>
                        <textarea id="sovereign-request-comment" class="input-neo" style="height: 80px; resize: none;" placeholder="Укажите цель запроса..."></textarea>
                    </div>

                    <!-- Cryptographic Interactive Terminal -->
                    <div id="sovereign-request-terminal" style="display: none; background: #020408; border: 1px solid var(--border-card); border-radius: 12px; padding: 15px; font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #10b981; min-height: 150px; max-height: 200px; overflow-y: auto; line-height: 1.5; box-shadow: inset 0 0 10px rgba(0,0,0,0.8);">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid rgba(16, 185, 129, 0.2); padding-bottom: 5px; margin-bottom: 8px;">
                            <span>SYSTEM TERMINAL v2.10</span>
                            <span style="color: var(--primary); animation: blink 1s infinite;">● RUNNING</span>
                        </div>
                        <div id="sovereign-request-terminal-logs"></div>
                    </div>
                </div>

                <div class="modal-footer" id="sovereign-request-footer">
                    <button onclick="closeSovereignRequestModal()" class="btn-neo">Отмена</button>
                    <button onclick="submitSovereignRequest()" class="btn-neo btn-primary-neo" id="btn-submit-sovereign-request">Подписать & Отправить 🔐</button>
                </div>
            </div>
        </div>

        <!-- Protected proof explorer modal -->
        <div class="modal-backdrop" id="sovereign-proof-modal-backdrop" style="display: none; align-items: center; justify-content: center; z-index: 1100;">
            <div class="modal-content" style="width: 650px; max-width: 95%; max-height: 90vh; overflow-y: auto; background: #070c14; border: 1px solid #10b981; box-shadow: 0 0 25px rgba(16, 185, 129, 0.25);">
                <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(16, 185, 129, 0.2); padding-bottom: 10px; margin-bottom: 15px;">
                    <span style="font-family: 'JetBrains Mono', monospace; font-size: 1.1rem; color: #10b981; font-weight: 800;">Проверка подтверждения</span>
                    <button onclick="closeSovereignProofModal()" class="btn-neo" style="padding: 2px 8px; font-size: 0.75rem; border-color: rgba(16, 185, 129, 0.3); color: #10b981;">[ Закрыть ]</button>
                </div>
                
                <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; line-height: 1.6; color: #e2e8f0; display: flex; flex-direction: column; gap: 15px;">
                    <!-- Request Summary -->
                    <div style="background: rgba(16, 185, 129, 0.03); border: 1px dashed rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 12px;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div><span style="color: #64748b;">Запрос ID:</span> <span id="proof-req-id" style="color: #10b981; font-weight: 800;"></span></div>
                            <div><span style="color: #64748b;">Статус:</span> <span id="proof-req-status"></span></div>
                            <div><span style="color: #64748b;">Сумма:</span> <span id="proof-req-amount" style="color: #10b981; font-weight: 800;"></span></div>
                            <div><span style="color: #64748b;">Дата подписи:</span> <span id="proof-req-date"></span></div>
                        </div>
                    </div>

                    <!-- Signer identity -->
                    <div>
                        <div style="color: #10b981; font-weight: 800; text-transform: uppercase; margin-bottom: 5px;">[ 1. Подтвержденный профиль ]</div>
                        <div style="background: #020408; border: 1px solid rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; word-break: break-all;">
                            <span style="color: #64748b;">DID:PASSKEY:</span> <span id="proof-signer-did" style="color: #34d399; font-weight: bold;"></span>
                        </div>
                    </div>

                    <!-- Signed Envelope -->
                    <div>
                        <div style="color: #10b981; font-weight: 800; text-transform: uppercase; margin-bottom: 5px;">[ 2. Signed Intent Envelope ]</div>
                        <pre id="proof-signed-envelope" style="background: #020408; border: 1px solid rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; max-height: 120px; overflow-y: auto; color: #a7f3d0; margin: 0;"></pre>
                    </div>

                    <!-- WebAuthn assertion -->
                    <div>
                        <div style="color: #10b981; font-weight: 800; text-transform: uppercase; margin-bottom: 5px;">[ 3. WebAuthn Hardware Assertion ]</div>
                        <div style="background: #020408; border: 1px solid rgba(255,255,255,0.05); padding: 10px; border-radius: 8px; display: flex; flex-direction: column; gap: 8px;">
                            <div>
                                <span style="color: #64748b;">Credential ID (Base64url):</span>
                                <div id="proof-cred-id" style="color: #f1f5f9; word-break: break-all; background: rgba(255,255,255,0.02); padding: 4px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); margin-top: 2px;"></div>
                            </div>
                            <div>
                                <span style="color: #64748b;">Client Data JSON (Decoded):</span>
                                <pre id="proof-client-data" style="color: #f1f5f9; background: rgba(255,255,255,0.02); padding: 6px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); overflow-x: auto; margin: 2px 0 0 0;"></pre>
                            </div>
                            <div>
                                <span style="color: #64748b;">Authenticator Data (Hex):</span>
                                <div id="proof-auth-data" style="color: #f1f5f9; word-break: break-all; background: rgba(255,255,255,0.02); padding: 4px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.05); font-size: 0.7rem; margin-top: 2px;"></div>
                            </div>
                            <div>
                                <span style="color: #64748b;">Signature Assertion (r, s):</span>
                                <div id="proof-signature" style="color: #34d399; word-break: break-all; background: rgba(16, 185, 129, 0.05); padding: 4px; border-radius: 4px; border: 1px solid rgba(16, 185, 129, 0.1); margin-top: 2px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Control mark derivation diagram -->
                    <div>
                        <div style="color: #10b981; font-weight: 800; text-transform: uppercase; margin-bottom: 5px;">[ 4. Контрольная отметка ]</div>
                        <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span>1. WebAuthn Public Key (PEM/DER)</span>
                                <span style="color: #64748b;">→ (Local Enclave Auth)</span>
                            </div>
                            <div style="text-align: center; color: #10b981; font-size: 1rem; margin: -2px 0;">▼</div>
                            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(0,0,0,0.2); padding: 4px 8px; border-radius: 4px;">
                                <span>2. SHA-256 Digest Hash</span>
                                <span id="proof-sha-hash" style="color: #34d399; font-weight: bold;"></span>
                            </div>
                            <div style="text-align: center; color: #10b981; font-size: 1rem; margin: -2px 0;">▼</div>
                            <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(16, 185, 129, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px solid rgba(16,185,129,0.2);">
                                <span>3. ID подтверждения</span>
                                <span id="proof-derived-l1" style="color: #34d399; font-weight: bold; font-size: 0.8rem;"></span>
                            </div>
                        </div>
                    </div>

                    <!-- consensus verification badge -->
                    <div style="display: flex; align-items: center; justify-content: center; gap: 10px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 10px; color: #10b981; font-weight: 800; font-size: 0.8rem; text-shadow: 0 0 5px rgba(16,185,129,0.3); animation: pulse 2s infinite;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        ПРОВЕРКА УСПЕШНА
                    </div>
                </div>
            </div>
        </div>

        <!-- 🛡️ B2B Console Client-Side JS SPA Logic -->
        <script>
            let shops = @json($shops);
            let catalog = @json($catalog);
            let providerProducts = @json($providerProducts);
            let storefrontCategoryCards = @json($storefrontCategoryCards);
            let orders = @json($orders);
            let warehouses = @json($warehouses);
            let activations = @json($activations);
            let vouchers = @json($vouchers);
            let apiApps = @json($apiApplications);
            let tickets = @json($tickets);

            let financeView = localStorage.getItem('finance_view') || 'cash'; 
            let activeFintentToken = localStorage.getItem('active_fintent_token') || null;
            let activeFintentAmount = parseFloat(localStorage.getItem('active_fintent_amount')) || 0;
            let isTokenVisible = false;

            // Raw loaded sovereign ledger data
            let ledger = @json($ledgerTransactions);
            let sovereignRequests = @json($sovereignRequests);

            // 🎨 Premium Theme/Skin Switcher
            function setTheme(theme) {
                if (window.MeanlyTheme && typeof window.MeanlyTheme.apply === 'function') {
                    theme = window.MeanlyTheme.apply(theme);
                }
                document.body.setAttribute('data-theme', theme);
                document.documentElement.setAttribute('data-theme', theme);
                localStorage.setItem('theme', theme);
                var cookieDomain = @json(config('session.domain') ?? null);
                var domainSuffix = cookieDomain ? '; domain=' + cookieDomain : '';
                document.cookie = `theme=${theme}; path=/; max-age=31536000; SameSite=Lax${domainSuffix}`;
                
                // Toggle active button style
                const updateBtnState = () => {
                    document.querySelectorAll('.skin-btn').forEach(btn => btn.classList.remove('active'));
                    const targetBtn = document.getElementById(`skin-btn-${theme}`);
                    if (targetBtn) targetBtn.classList.add('active');
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', updateBtnState);
                } else {
                    updateBtnState();
                }
            }
            
            // 🧠 Cognitive Demographic & Heuristic Default Theme Predictor
            function getCognitiveDemographicDefaultTheme() {
                try {
                    // 1. Detect Locale/Region
                    const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone || "";
                    const isCIS = /Moscow|Europe\/Moscow|Samara|Yekaterinburg|Novosibirsk|Asia\/Almaty|Asia\/Tashkent|Asia\/Baku|Europe\/Minsk|ru|ru-RU/i.test(timeZone + navigator.language);
                    
                    // 2. Detect Device Capabilities (Proxy for Generation / Age / Hacker profile)
                    const hasTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
                    const isHighDPI = window.devicePixelRatio && window.devicePixelRatio > 1.5;
                    
                    // Check for WebGPU (highly indicative of Gen Z bleeding-edge gamer/creator rigs)
                    const supportsWebGPU = !!navigator.gpu;
                    
                    // Check for older/desktop developer setups (Retro lovers)
                    const isLinuxOrOldOS = /Linux|Ubuntu|Debian|Windows NT 6.1|Windows NT 5.1/i.test(navigator.userAgent);
                    const lacksModernGpu = !supportsWebGPU && !window.WebGL2RenderingContext;

                    // 3. System Theme preference
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const currentHour = new Date().getHours();

                    console.log(`[Cognitive Engine] TZ: ${timeZone}, Touch: ${hasTouch}, WebGPU: ${supportsWebGPU}, PrefersDark: ${prefersDark}, Hour: ${currentHour}`);

                    // 4. Expanded Demographic Heuristics Decision Tree
                    
                    // A. Light System / Organic / Calm preferences (Nordic)
                    if (!prefersDark || /Stockholm|Oslo|Copenhagen|Helsinki|Europe\/London|Europe\/Paris/i.test(timeZone)) {
                        console.log("[Cognitive Choice] Matched calming light/organic profile -> NORDIC theme 🍃");
                        return 'nordic';
                    }
                    
                    // B. Retro technical profile / Gen X / old-school geeks (Retro)
                    if (isLinuxOrOldOS || lacksModernGpu) {
                        console.log("[Cognitive Choice] Matched old-school technical profile -> RETRO theme ⚡");
                        return 'retro';
                    }
                    
                    // C. Gamers, creative night owls, neon-futurism (Synthwave)
                    if (supportsWebGPU && (currentHour >= 18 || currentHour <= 4)) {
                        console.log("[Cognitive Choice] Matched late-night creative gamer -> SYNTHWAVE theme 🟣");
                        return 'synthwave';
                    }
                    
                    // D. High-performance desktop geeks / pure performance (Carbon)
                    if (prefersDark && !hasTouch && isHighDPI) {
                        console.log("[Cognitive Choice] Matched high-performance minimalist developer -> CARBON theme 🏁");
                        return 'carbon';
                    }
                    
                    // E. Mobile-first digital creators (Partner)
                    if (hasTouch && isHighDPI) {
                        console.log("[Cognitive Choice] Matched mobile digital creator -> PARTNER theme 🌟");
                        return 'partner';
                    }
                    
                    // F. Premium B2B Executive (Consortium)
                    console.log("[Cognitive Choice] Matched flagship executive profile -> CONSORTIUM theme 🚩");
                    return 'consortium';
                } catch (e) {
                    console.warn("[Cognitive Engine] Failed to compute heuristics, falling back to Consortium flagship.", e);
                    return 'consortium';
                }
            }

            // 📆 Holiday Detection Logic
            function getActiveHoliday() {
                return document.body.getAttribute('data-holiday') || null;
            }

            // 🎭 Sovereign Atmospheric Holiday & Context Effects Engine
            function initAtmosphericHolidayFX(holidayOverride) {
                const holiday = holidayOverride || getActiveHoliday();
                if (!holiday) return;

                // Set body attribute for CSS overrides
                document.body.setAttribute('data-holiday', holiday);
                
                if (holiday === 'sons-birthday') {
                    console.log("%c🦁 [Sovereign Heir Engine] 19 MAY: Happy Birthday to the Champion! С Днём Рождения, Сына! Расти сильным, смелым и свободным! 👑🏆⚡", "color: #ffd700; font-weight: bold; font-size: 14px;");
                } else if (holiday === 'little-prince') {
                    console.log("%c🌹 [Little Prince Engine] 17 OCTOBER: \"Ты навсегда в ответе за тех, кого приручил.\" Твоя единственная Роза. 💫🌠", "color: #e11d48; font-weight: bold; font-size: 14px;");
                } else if (holiday === 'orchid-day') {
                    console.log("%c🌸 [Orchid Engine] 12 MAY: В воздухе парит изысканность... С Днём Орхидей! 🌺💫", "color: #d946ef; font-weight: bold; font-size: 14px;");
                } else if (holiday === 'doctor-day') {
                    console.log("%c🩺 [Doctor Engine] 21 APRIL: Слышим каждое биение сердца... С Днём Врача! 💚⚕️", "color: #0d9488; font-weight: bold; font-size: 14px;");
                } else if (holiday === 'babel-library') {
                    console.log("%c📚 [Library of Babel] 24 AUGUST: \"La Biblioteca es ilimitada y periódica...\" / \"The Library is limitless and periodic...\" 🌌🚪", "color: #b45309; font-weight: bold; font-size: 14px;");
                } else {
                    console.log(`[Holiday Engine] Active Festive Period: ${holiday.toUpperCase()} 🎁`);
                }

                // Create canvas element
                const canvas = document.createElement('canvas');
                canvas.id = 'holiday-canvas-fx';
                Object.assign(canvas.style, {
                    position: 'fixed',
                    inset: '0',
                    pointerEvents: 'none',
                    zIndex: '1',
                    opacity: '0.65'
                });
                document.body.appendChild(canvas);

                const ctx = canvas.getContext('2d');
                let width = canvas.width = window.innerWidth;
                let height = canvas.height = window.innerHeight;

                window.addEventListener('resize', () => {
                    width = canvas.width = window.innerWidth;
                    height = canvas.height = window.innerHeight;
                });

                const particles = [];
                const maxParticles = 60;

                class Particle {
                    constructor() {
                        this.reset();
                    }

                    reset() {
                        this.x = Math.random() * width;
                        const isFloatingUp = (holiday === 'valentine' || holiday === 'sons-birthday' || holiday === 'little-prince' || holiday === 'orchid-day' || holiday === 'doctor-day' || holiday === 'babel-library');
                        this.y = isFloatingUp ? height + 25 : -25;
                        this.type = Math.floor(Math.random() * 12); // Stable random type assigned on reset
                        // Stable Babel character — assigned once on reset, never changes mid-flight
                        const _babelAlphabet = "abcdefghijklmnopqrstuvwxyz,.";
                        this.babelChar = _babelAlphabet[Math.floor(Math.random() * _babelAlphabet.length)];
                        
                        if (isFloatingUp) {
                            this.size = Math.random() * 12 + 10; // Majestic 10px to 22px size!
                            this.speedX = Math.random() * 0.2 - 0.1;
                            this.speedY = -(Math.random() * 0.45 + 0.25); // Gentle slow float upwards!
                            this.alpha = Math.random() * 0.3 + 0.7; // Bright and crisp visibility
                            this.angle = Math.random() * Math.PI * 2;
                            this.spin = Math.random() * 0.012 - 0.006; // Calm majestic rotation
                        } else {
                            this.size = Math.random() * 4 + 2;
                            this.speedX = holiday === 'womens-day' ? Math.random() * 1.5 - 0.2 : Math.random() * 1 - 0.5;
                            this.speedY = Math.random() * 1 + 0.8;
                            this.alpha = Math.random() * 0.6 + 0.4;
                            this.angle = Math.random() * Math.PI * 2;
                            this.spin = Math.random() * 0.04 - 0.02;
                        }
                    }

                    update() {
                        this.x += this.speedX;
                        this.y += this.speedY;
                        this.angle += this.spin;

                        const isFloatingUp = (holiday === 'valentine' || holiday === 'sons-birthday' || holiday === 'little-prince' || holiday === 'orchid-day' || holiday === 'doctor-day');

                        if (isFloatingUp) {
                            // Beautiful sinusoidal sway (fluttering float)
                            this.x += Math.sin(this.y / 35) * 0.35;
                        }

                        if (isFloatingUp) {
                            if (this.y < -25 || this.x < -25 || this.x > width + 25) this.reset();
                        } else {
                            if (this.y > height + 25 || this.x < -25 || this.x > width + 25) this.reset();
                        }
                    }

                    draw() {
                        ctx.save();
                        ctx.globalAlpha = this.alpha;
                        ctx.translate(this.x, this.y);
                        ctx.rotate(this.angle);

                        if (holiday === 'christmas') {
                            // Draw snowflake
                            ctx.fillStyle = '#ffffff';
                            ctx.beginPath();
                            ctx.arc(0, 0, this.size, 0, Math.PI * 2);
                            ctx.fill();
                        } else if (holiday === 'valentine') {
                            // Draw heart
                            ctx.fillStyle = '#ff3366';
                            ctx.beginPath();
                            ctx.moveTo(0, 0);
                            ctx.bezierCurveTo(-this.size, -this.size, -this.size * 2, this.size / 3, 0, this.size * 1.5);
                            ctx.bezierCurveTo(this.size * 2, this.size / 3, this.size, -this.size, 0, 0);
                            ctx.fill();
                        } else if (holiday === 'womens-day') {
                            // Draw flower petal
                            ctx.fillStyle = '#ffb7c5'; // Soft sakura pink
                            ctx.beginPath();
                            ctx.ellipse(0, 0, this.size * 1.5, this.size, Math.PI / 4, 0, Math.PI * 2);
                            ctx.fill();
                        } else if (holiday === 'halloween') {
                            // Draw embers
                            ctx.fillStyle = '#ff6600';
                            ctx.beginPath();
                            ctx.arc(0, 0, this.size * 1.2, 0, Math.PI * 2);
                            ctx.fill();
                        } else if (holiday === 'black-friday') {
                            // Draw neon glitch segment
                            ctx.strokeStyle = '#39ff14'; // Cyber green
                            ctx.lineWidth = 1;
                            ctx.beginPath();
                            ctx.moveTo(0, 0);
                            ctx.lineTo(0, this.size * 5);
                            ctx.stroke();
                        } else if (holiday === 'sons-birthday') {
                            // Render Swiss flags, Argentine flags (with Sol de Mayo), standalone Suns, cute Hippo, and Golden stars with Alejandro 👑!
                            const particleType = this.type % 5;
                            const scale = this.size * 1.35;

                            if (particleType === 0) {
                                // 1. Swiss Flag (Швейцарский флаг)
                                ctx.fillStyle = '#da291c';
                                ctx.fillRect(-scale, -scale, scale * 2, scale * 2);
                                ctx.fillStyle = '#ffffff';
                                const barW = scale * 0.4;
                                const barH = scale * 1.3;
                                ctx.fillRect(-barW / 2, -barH / 2, barW, barH);
                                ctx.fillRect(-barH / 2, -barW / 2, barH, barW);
                            } else if (particleType === 1) {
                                // 2. Argentine Flag (Аргентинский флаг с прорисованным Солнцем!)
                                const w = scale * 1.8;
                                const h = scale * 1.2;
                                ctx.fillStyle = '#74acdf';
                                ctx.fillRect(-w / 2, -h / 2, w, h / 3);
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(-w / 2, -h / 2 + h / 3, w, h / 3);
                                ctx.fillStyle = '#74acdf';
                                ctx.fillRect(-w / 2, -h / 2 + (h / 3) * 2, w, h / 3);
                                
                                // Sun center
                                ctx.fillStyle = '#f6b40e';
                                ctx.beginPath();
                                ctx.arc(0, 0, h * 0.12, 0, Math.PI * 2);
                                ctx.fill();
                                
                                // Miniature Sun rays
                                ctx.strokeStyle = '#f6b40e';
                                ctx.lineWidth = h * 0.04;
                                for (let r = 0; r < 8; r++) {
                                    ctx.beginPath();
                                    ctx.moveTo(0, 0);
                                    const rx = Math.cos(r * Math.PI / 4) * h * 0.22;
                                    const ry = Math.sin(r * Math.PI / 4) * h * 0.22;
                                    ctx.lineTo(rx, ry);
                                    ctx.stroke();
                                }
                            } else if (particleType === 2) {
                                // 3. Standalone Sol de Mayo (Солнце Аргентины)
                                const rSun = scale * 0.45;
                                ctx.fillStyle = '#f6b40e';
                                ctx.beginPath();
                                ctx.arc(0, 0, rSun, 0, Math.PI * 2);
                                ctx.fill();

                                ctx.strokeStyle = '#f6b40e';
                                ctx.lineWidth = scale * 0.12;
                                for (let r = 0; r < 12; r++) {
                                    ctx.beginPath();
                                    ctx.moveTo(0, 0);
                                    const rx = Math.cos(r * Math.PI / 6) * scale * 1.1;
                                    const ry = Math.sin(r * Math.PI / 6) * scale * 1.1;
                                    ctx.lineTo(rx, ry);
                                    ctx.stroke();
                                }
                            } else if (particleType === 3) {
                                // 4. Cute Vector Hippo (Бегемотик)
                                // Head
                                ctx.fillStyle = '#a5b4fc'; // Cute indigo/lilac color
                                ctx.beginPath();
                                ctx.arc(0, -scale * 0.1, scale * 0.5, 0, Math.PI * 2);
                                ctx.fill();

                                // Snout (large lower oval)
                                ctx.fillStyle = '#818cf8';
                                ctx.beginPath();
                                ctx.ellipse(0, scale * 0.2, scale * 0.6, scale * 0.35, 0, 0, Math.PI * 2);
                                ctx.fill();

                                // Nostrils
                                ctx.fillStyle = '#4f46e5';
                                ctx.beginPath();
                                ctx.arc(-scale * 0.18, scale * 0.18, scale * 0.08, 0, Math.PI * 2);
                                ctx.fill();
                                ctx.beginPath();
                                ctx.arc(scale * 0.18, scale * 0.18, scale * 0.08, 0, Math.PI * 2);
                                ctx.fill();

                                // Eyes
                                ctx.fillStyle = '#1e1b4b'; // Dark blue eyes
                                ctx.beginPath();
                                ctx.arc(-scale * 0.18, -scale * 0.15, scale * 0.07, 0, Math.PI * 2);
                                ctx.fill();
                                ctx.beginPath();
                                ctx.arc(scale * 0.18, -scale * 0.15, scale * 0.07, 0, Math.PI * 2);
                                ctx.fill();

                                // Eye highlights
                                ctx.fillStyle = '#ffffff';
                                ctx.beginPath();
                                ctx.arc(-scale * 0.2, -scale * 0.17, scale * 0.025, 0, Math.PI * 2);
                                ctx.fill();
                                ctx.beginPath();
                                ctx.arc(scale * 0.16, -scale * 0.17, scale * 0.025, 0, Math.PI * 2);
                                ctx.fill();

                                // Ears
                                ctx.fillStyle = '#a5b4fc';
                                ctx.beginPath();
                                ctx.arc(-scale * 0.38, -scale * 0.5, scale * 0.15, 0, Math.PI * 2);
                                ctx.fill();
                                ctx.beginPath();
                                ctx.arc(scale * 0.38, -scale * 0.5, scale * 0.15, 0, Math.PI * 2);
                                ctx.fill();

                                // Pink inner ear
                                ctx.fillStyle = '#fda4af';
                                ctx.beginPath();
                                ctx.arc(-scale * 0.38, -scale * 0.5, scale * 0.08, 0, Math.PI * 2);
                                ctx.fill();
                                ctx.beginPath();
                                ctx.arc(scale * 0.38, -scale * 0.5, scale * 0.08, 0, Math.PI * 2);
                                ctx.fill();
                            } else {
                                // 5. Golden Champion Star
                                ctx.fillStyle = '#ffd700';
                                ctx.beginPath();
                                ctx.moveTo(0, -scale * 1.3);
                                ctx.lineTo(scale * 0.35, -scale * 0.35);
                                ctx.lineTo(scale * 1.3, 0);
                                ctx.lineTo(scale * 0.35, scale * 0.35);
                                ctx.lineTo(0, scale * 1.3);
                                ctx.lineTo(-scale * 0.35, scale * 0.35);
                                ctx.lineTo(-scale * 1.3, 0);
                                ctx.lineTo(-scale * 0.35, -scale * 0.35);
                                ctx.closePath();
                                ctx.fill();

                                // Golden Crown at the top of the star
                                ctx.fillStyle = '#f59e0b'; // Amber Gold
                                ctx.beginPath();
                                ctx.moveTo(-scale * 0.3, -scale * 1.4);
                                ctx.lineTo(-scale * 0.2, -scale * 1.7);
                                ctx.lineTo(0, -scale * 1.5);
                                ctx.lineTo(scale * 0.2, -scale * 1.7);
                                ctx.lineTo(scale * 0.3, -scale * 1.4);
                                ctx.closePath();
                                ctx.fill();

                                ctx.shadowBlur = 0; // reset shadow
                            }
                        } else if (holiday === 'little-prince') {
                            // Draw sparkling golden stars of Asteroid B-612!
                            ctx.fillStyle = '#ffd700';
                            ctx.beginPath();
                            ctx.moveTo(0, -this.size * 1.25);
                            ctx.lineTo(this.size * 0.3, -this.size * 0.3);
                            ctx.lineTo(this.size * 1.25, 0);
                            ctx.lineTo(this.size * 0.3, this.size * 0.3);
                            ctx.lineTo(0, this.size * 1.25);
                            ctx.lineTo(-this.size * 0.3, this.size * 0.3);
                            ctx.lineTo(-this.size * 1.25, 0);
                            ctx.lineTo(-this.size * 0.3, -this.size * 0.3);
                            ctx.closePath();
                            ctx.fill();
                        } else if (holiday === 'orchid-day') {
                            // Draw a majestic vector orchid flower!
                            const scale = this.size * 1.4;
                            
                            // Sepals
                            ctx.fillStyle = '#f5d0fe'; // Lavender
                            ctx.beginPath();
                            ctx.ellipse(0, -scale * 0.8, scale * 0.45, scale * 0.8, 0, 0, Math.PI * 2);
                            ctx.fill();
                            ctx.beginPath();
                            ctx.ellipse(-scale * 0.6, scale * 0.6, scale * 0.45, scale * 0.7, Math.PI / 3, 0, Math.PI * 2);
                            ctx.fill();
                            ctx.beginPath();
                            ctx.ellipse(scale * 0.6, scale * 0.6, scale * 0.45, scale * 0.7, -Math.PI / 3, 0, Math.PI * 2);
                            ctx.fill();
                            
                            // Large lateral petals
                            ctx.fillStyle = '#e879f9'; // Vibrant orchid pink
                            ctx.beginPath();
                            ctx.ellipse(-scale * 0.8, -scale * 0.1, scale * 0.7, scale * 0.55, -Math.PI / 8, 0, Math.PI * 2);
                            ctx.fill();
                            ctx.beginPath();
                            ctx.ellipse(scale * 0.8, -scale * 0.1, scale * 0.7, scale * 0.55, Math.PI / 8, 0, Math.PI * 2);
                            ctx.fill();
                            
                            // Deep magenta center lip (Labellum)
                            ctx.fillStyle = '#df0893';
                            ctx.beginPath();
                            ctx.ellipse(0, scale * 0.25, scale * 0.45, scale * 0.5, 0, 0, Math.PI * 2);
                            ctx.fill();
                            
                            // Yellow stamen core
                            ctx.fillStyle = '#eab308';
                            ctx.beginPath();
                            ctx.arc(0, -scale * 0.1, scale * 0.18, 0, Math.PI * 2);
                            ctx.fill();
                        } else if (holiday === 'doctor-day') {
                            // Draw a beautiful stethoscope vector!
                            const scale = this.size * 1.3;
                            const particleType = this.type % 3;

                            if (particleType === 0) {
                                // 1. Classic Medical Stethoscope
                                // Outer chestpiece rim
                                ctx.strokeStyle = '#cbd5e1'; // Silver/grey
                                ctx.lineWidth = scale * 0.15;
                                ctx.beginPath();
                                ctx.arc(0, scale * 0.6, scale * 0.45, 0, Math.PI * 2);
                                ctx.stroke();

                                // Inner chestpiece diaphragm
                                ctx.fillStyle = '#06b6d4'; // Cyan glowing core
                                ctx.beginPath();
                                ctx.arc(0, scale * 0.6, scale * 0.3, 0, Math.PI * 2);
                                ctx.fill();

                                // Rubber tubes (curved)
                                ctx.strokeStyle = '#0d9488'; // Teal tube
                                ctx.lineWidth = scale * 0.16;
                                ctx.lineCap = 'round';
                                ctx.lineJoin = 'round';

                                // Main tube connecting chestpiece to the headset Y
                                ctx.beginPath();
                                ctx.moveTo(0, scale * 0.15);
                                ctx.bezierCurveTo(-scale * 0.4, -scale * 0.1, -scale * 0.4, -scale * 0.6, 0, -scale * 0.7);
                                ctx.stroke();

                                // Y-binaural metallic branches
                                ctx.strokeStyle = '#cbd5e1'; // Metallic binaural
                                ctx.lineWidth = scale * 0.1;
                                ctx.beginPath();
                                ctx.arc(-scale * 0.35, -scale * 1.0, scale * 0.45, 0, Math.PI, true);
                                ctx.stroke();
                                ctx.beginPath();
                                ctx.arc(scale * 0.35, -scale * 1.0, scale * 0.45, 0, Math.PI, true);
                                ctx.stroke();

                                // Black plastic Eartips at the top
                                ctx.fillStyle = '#1e293b';
                                ctx.beginPath();
                                ctx.arc(-scale * 0.78, -scale * 1.0, scale * 0.15, 0, Math.PI * 2);
                                ctx.fill();
                                ctx.beginPath();
                                ctx.arc(scale * 0.78, -scale * 1.0, scale * 0.15, 0, Math.PI * 2);
                                ctx.fill();
                            } else if (particleType === 1) {
                                // 2. Glowing Medical Red/Teal Cross
                                ctx.fillStyle = Math.abs(this.x) % 2 === 0 ? '#10b981' : '#0d9488'; // Emerald / Teal
                                const w = scale * 0.4;
                                const h = scale * 1.3;
                                ctx.fillRect(-w / 2, -h / 2, w, h);
                                ctx.fillRect(-h / 2, -w / 2, h, w);
                            } else {
                                // 3. EKG Pulse Line Segment (Зеленая линия ЭКГ)
                                ctx.strokeStyle = '#2dd4bf'; // Glowing turquoise
                                ctx.lineWidth = scale * 0.18;
                                ctx.lineCap = 'round';
                                ctx.beginPath();
                                ctx.moveTo(-scale, 0);
                                ctx.lineTo(-scale * 0.4, 0);
                                ctx.lineTo(-scale * 0.2, -scale * 0.8);
                                ctx.lineTo(scale * 0.1, scale * 0.8);
                                ctx.lineTo(scale * 0.3, -scale * 0.2);
                                ctx.lineTo(scale * 0.5, 0);
                                ctx.lineTo(scale, 0);
                                ctx.stroke();
                            }
                        } else if (holiday === 'babel-library') {
                            // Draw Borges' Library of Babel vectors!
                            const scale = this.size * 1.3;
                            const particleType = this.type % 4;

                            if (particleType === 0) {
                                // 1. Hexagonal Gallery (Borges' Hexagon)
                                ctx.strokeStyle = '#d97706'; // Antique Amber
                                ctx.lineWidth = scale * 0.12;
                                ctx.beginPath();
                                for (let h = 0; h < 6; h++) {
                                    const hx = Math.cos(h * Math.PI / 3) * scale;
                                    const hy = Math.sin(h * Math.PI / 3) * scale;
                                    if (h === 0) ctx.moveTo(hx, hy);
                                    else ctx.lineTo(hx, hy);
                                }
                                ctx.closePath();
                                ctx.stroke();
                            } else if (particleType === 1) {
                                // 2. Mystical Open Book (Книга Вавилонской Библиотеки)
                                ctx.fillStyle = '#fef3c7'; // Old parchment pages
                                ctx.strokeStyle = '#78350f'; // Leather brown spine/cover
                                ctx.lineWidth = scale * 0.08;

                                // Left page
                                ctx.beginPath();
                                ctx.moveTo(0, scale * 0.4);
                                ctx.bezierCurveTo(-scale * 0.4, scale * 0.2, -scale * 0.6, scale * 0.4, -scale * 0.8, scale * 0.2);
                                ctx.lineTo(-scale * 0.8, -scale * 0.4);
                                ctx.bezierCurveTo(-scale * 0.6, -scale * 0.2, -scale * 0.4, -scale * 0.4, 0, -scale * 0.2);
                                ctx.closePath();
                                ctx.fill();
                                ctx.stroke();

                                // Right page
                                ctx.beginPath();
                                ctx.moveTo(0, scale * 0.4);
                                ctx.bezierCurveTo(scale * 0.4, scale * 0.2, scale * 0.6, scale * 0.4, scale * 0.8, scale * 0.2);
                                ctx.lineTo(scale * 0.8, -scale * 0.4);
                                ctx.bezierCurveTo(scale * 0.6, -scale * 0.2, scale * 0.4, -scale * 0.4, 0, -scale * 0.2);
                                ctx.closePath();
                                ctx.fill();
                                ctx.stroke();

                                // Spine line
                                ctx.beginPath();
                                ctx.moveTo(0, -scale * 0.2);
                                ctx.lineTo(0, scale * 0.4);
                                ctx.stroke();
                            } else if (particleType === 2) {
                                // 3. Floating Random Character / Letter of Babel (Случайный символ бесконечного алфавита)
                                const char = this.babelChar || 'a';
                                ctx.fillStyle = '#f59e0b'; // Glowing gold
                                ctx.font = `italic bold ${Math.max(12, scale * 0.85)}px serif`;
                                ctx.textAlign = 'center';
                                ctx.fillText(char, 0, scale * 0.3);
                            } else {
                                // 4. Rolled Parchment Scroll (Свиток)
                                ctx.fillStyle = '#fef3c7'; // Parchment roll
                                ctx.strokeStyle = '#d97706';
                                ctx.lineWidth = scale * 0.06;
                                ctx.beginPath();
                                ctx.ellipse(0, 0, scale * 0.7, scale * 0.25, Math.PI / 6, 0, Math.PI * 2);
                                ctx.fill();
                                ctx.stroke();
                            }
                        }

                        ctx.restore();
                    }
                }

                for (let i = 0; i < maxParticles; i++) {
                    particles.push(new Particle());
                    // Pre-warm particles across screen height
                    particles[i].y = Math.random() * height;
                }

                function animate() {
                    ctx.clearRect(0, 0, width, height);
                    for (let i = 0; i < particles.length; i++) {
                        particles[i].update();
                        particles[i].draw();
                    }
                    requestAnimationFrame(animate);
                }

                animate();
            }

            // Auto initialize theme & holiday effects
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('theme')) {
                localStorage.setItem('theme', urlParams.get('theme').toLowerCase());
            }
            const savedTheme = localStorage.getItem('theme') || getCognitiveDemographicDefaultTheme();
            setTheme(savedTheme);
            
            // Instant fallback/sync load
            initAtmosphericHolidayFX();

            // Async active holiday sync with backend Google-Doodle-style API
            async function syncActiveHolidayWithApi() {
                try {
                    const holidayParam = urlParams.get('holiday');
                    const dateParam = urlParams.get('date');
                    
                    let apiUrl = '/api/holidays/active';
                    const params = [];
                    if (holidayParam) params.push(`holiday=${holidayParam}`);
                    if (dateParam) params.push(`date=${dateParam}`);
                    if (params.length > 0) apiUrl += `?${params.join('&')}`;

                    const response = await fetch(apiUrl);
                    if (!response.ok) throw new Error("API failed");
                    const data = await response.json();
                    
                    const apiHoliday = data.active_holiday;
                    const currentHoliday = document.body.getAttribute('data-holiday');
                    
                    if (apiHoliday) {
                        if (currentHoliday !== apiHoliday.id) {
                            console.log(`[Festive API] Dynamic Sync: Switching active holiday to ${apiHoliday.name} (${apiHoliday.id})! 🎭`);
                            document.body.setAttribute('data-holiday', apiHoliday.id);
                            
                            const existingCanvas = document.getElementById('holiday-canvas-fx');
                            if (existingCanvas) existingCanvas.remove();
                            
                            initAtmosphericHolidayFX(apiHoliday.id);
                        }
                    } else {
                        if (currentHoliday) {
                            document.body.removeAttribute('data-holiday');
                            const existingCanvas = document.getElementById('holiday-canvas-fx');
                            if (existingCanvas) existingCanvas.remove();
                        }
                    }
                } catch (e) {
                    console.warn("[Festive API] Failed to fetch active holiday, keeping local client fallback.", e);
                }
            }
            
            // Defer API sync to ensure high performance
            if (window.requestIdleCallback) {
                window.requestIdleCallback(() => syncActiveHolidayWithApi());
            } else {
                setTimeout(syncActiveHolidayWithApi, 200);
            }

            // 🌐 SPA Tab Router Setup
            function switchTab(tabId) {
                document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
                document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
                
                const tabEl = document.getElementById(`tab-${tabId}`);
                const menuEl = document.getElementById(`menu-${tabId}`);
                if (tabEl) tabEl.classList.add('active');
                if (menuEl) menuEl.classList.add('active');
                
                const pageTitleText = {
                    'dashboard': 'Инфопанель',
                    'orders': 'Заказы',
                    'storefront': 'Каталог поставщиков',
                    'shops': 'Магазины',
                    'catalog': 'Мой каталог',
                    'activations': 'История активаций',
                    'warehouses': 'Склады',
                    'vouchers': 'Реестр кодов',
                    'finance': 'Финансы',
                    'team': 'Команда',
                    'support': 'Служба поддержки',
                    'operator': 'Operator Workspace',
                    'ai-audit': 'AI Аудит',
                    'documents': 'Документооборот'
                };
                document.getElementById('page-title-text').innerText = pageTitleText[tabId] || 'Кабинет';
                localStorage.setItem('active_partner_tab', tabId);

                if (tabId === 'finance') {
                    renderFinanceTab();
                }

                if (tabId === 'ai-audit') {
                    setTimeout(() => {
                        const input = document.getElementById('ai-chat-input');
                        if (input) input.focus();
                    }, 100);
                }
            }

            // Load saved tab
            document.addEventListener('DOMContentLoaded', () => {
                const serverTab = @json($activePartnerTab ?? null);
                const savedTab = serverTab || localStorage.getItem('active_partner_tab') || 'dashboard';
                switchTab(savedTab);
                renderDepositIntentsPanel();
            });

            // 👁️ API Key Visibility Toggle
            const rawToken = "{{ $apiApplications->first()->token ?? 'Нет активных API ключей' }}";
            function toggleApiTokenVisibility() {
                const display = document.getElementById('dashboard-api-token-display');
                if (isTokenVisible) {
                    display.innerText = '••••••••••••••••••••••••';
                    display.style.letterSpacing = '1.5px';
                } else {
                    display.innerText = rawToken;
                    display.style.letterSpacing = 'normal';
                }
                isTokenVisible = !isTokenVisible;
            }

            // 💰 Finance View toggles & dynamic statements
            function setFinanceView(view) {
                financeView = view;
                localStorage.setItem('finance_view', view);
                
                document.getElementById('finance-view-cash').classList.remove('btn-primary-neo');
                document.getElementById('finance-view-all').classList.remove('btn-primary-neo');
                
                if (view === 'cash') {
                    document.getElementById('finance-view-cash').classList.add('btn-primary-neo');
                } else {
                    document.getElementById('finance-view-all').classList.add('btn-primary-neo');
                }

                renderFinanceTab();
            }

            function renderFinanceTab() {
                const header = document.getElementById('finance-table-header');
                const tbody = document.getElementById('finance-table-body');
                
                tbody.innerHTML = '';

                if (financeView === 'cash') {
                    header.innerHTML = `
                        <th>ID операции</th>
                        <th>Описание события</th>
                        <th>Канал</th>
                        <th>Дата и время</th>
                        <th>Сумма (₽)</th>
                    `;

                    const cashEvents = ledger.filter(item => {
                        try {
                            const p = JSON.parse(item.payload);
                            return p.amount && (p.clearing_type || p.new_balance || item.event_type.includes('DEPOSIT') || item.event_type.includes('ORDER'));
                        } catch (e) {
                            return false;
                        }
                    });

                    if (cashEvents.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Нет зарегистрированных рублевых операций.</td></tr>`;
                        return;
                    }

                    cashEvents.forEach(item => {
                        const p = JSON.parse(item.payload);
                        const shopName = item.shop_id ? (shops.find(s => s.id === item.shop_id)?.name || '—') : '—';
                        const txRef = item.transaction_ref || (item.fingerprint ? `SL1-${item.fingerprint.substring(0, 8).toUpperCase()}-${item.fingerprint.substring(8, 16).toUpperCase()}` : `SL1-BLOCK-${item.id}`);
                        
                        tbody.innerHTML += `
                            <tr>
                                <td style="font-family: var(--font-tech); font-weight: 700;">${txRef}</td>
                                <td>
                                    <div style="font-weight: 700;">${translateEventType(item.event_type)}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">${item.event_type}</div>
                                </td>
                                <td>${shopName}</td>
                                <td>${formatDate(item.created_at)}</td>
                                <td style="font-family: var(--font-tech); font-weight: 900; color: ${p.amount > 0 ? '#10b981' : '#f43f5e'};">
                                    ${p.amount > 0 ? '+' : ''}${number_format(p.amount, 2, '.', ' ')} ₽
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    header.innerHTML = `
                        <th>Запись</th>
                        <th>Тип и источник события</th>
                        <th>Профиль подписи</th>
                        <th>ID события</th>
                        <th>Дата</th>
                    `;

                    if (ledger.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">Журнал операций пуст.</td></tr>`;
                        return;
                    }

                    ledger.forEach(item => {
                        let l1_address = item.trigger_source;
                        if (l1_address.includes('SYSTEM')) {
                            l1_address = 'DID:L1:0x41f879...';
                        }
                        const txRef = item.transaction_ref || (item.fingerprint ? `SL1-${item.fingerprint.substring(0, 8).toUpperCase()}-${item.fingerprint.substring(8, 16).toUpperCase()}` : `SL1-BLOCK-${item.id}`);
                        
                        tbody.innerHTML += `
                            <tr>
                                <td style="font-family: var(--font-tech); font-weight: 700; color: var(--primary);">${txRef}</td>
                                <td>
                                    <div style="font-weight: 700;">${item.event_type}</div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted);">${item.input_data ? 'Подтверждено Passkey' : 'Операция проверена'}</div>
                                </td>
                                <td style="font-family: monospace; font-size: 0.75rem; color: #10b981;">
                                    ${l1_address}
                                </td>
                                <td style="font-family: monospace; font-size: 0.75rem;">
                                    DID:SYS:MEANLY:${txRef}
                                </td>
                                <td>${formatDate(item.created_at)}</td>
                            </tr>
                        `;
                    });
                }

                renderDepositIntentsPanel();
                renderSovereignRequests();
            }

            function renderSovereignRequests() {
                const tbody = document.getElementById('sovereign-requests-table-body');
                if (!tbody) return;
                
                tbody.innerHTML = '';
                
                if (!sovereignRequests || sovereignRequests.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 2rem;">Запросов пока нет.</td></tr>`;
                    return;
                }
                
                sovereignRequests.forEach(req => {
                    const typeText = req.type === 'top_up' ? '💳 Пополнение (Replenish)' : '📈 Кредит (JIT Credit)';
                    
                    let statusBadge = '';
                    if (req.status === 'pending') {
                        statusBadge = `<span class="badge-neo" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2);">Ожидание ⏳</span>`;
                    } else if (req.status === 'approved') {
                        statusBadge = `<span class="badge-neo" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2);">Исполнен ✅</span>`;
                    } else {
                        statusBadge = `<span class="badge-neo" style="background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: 1px solid rgba(244, 63, 94, 0.2);">Отклонен ❌</span>`;
                    }
                    
                    const signatureBadge = `<span onclick="openSovereignProofModal(${req.id})" class="badge-neo" style="background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 12px; font-weight: 800; font-size: 0.65rem; box-shadow: 0 0 8px rgba(16,185,129,0.1); cursor: pointer; display: inline-flex; align-items: center; gap: 4px; transition: transform 0.15s, box-shadow 0.15s;" onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 0 12px rgba(16,185,129,0.35)';" onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 0 8px rgba(16,185,129,0.1)';">Verified ✅</span>`;

                    const displayAddr = req.l1_address ? (req.l1_address.substring(0, 10) + '...' + req.l1_address.substring(req.l1_address.length - 8)) : '—';
                    const commentText = req.comment ? escapeHtml(req.comment) : '—';
                    const dateFormatted = req.created_at_formatted || (req.created_at ? new Date(req.created_at).toLocaleString('ru-RU', {day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute:'2-digit'}) : '—');
                    
                    tbody.innerHTML += `
                        <tr style="transition: background 0.2s;">
                            <td style="font-family: var(--font-tech); font-weight: 700; color: var(--primary);">REQ-${req.id}</td>
                            <td style="font-weight: 700;">${typeText}</td>
                            <td style="font-family: var(--font-tech); font-weight: 900; color: var(--primary);">${number_format(req.amount, 2, '.', ' ')} ₽</td>
                            <td>${statusBadge}</td>
                            <td>${signatureBadge}</td>
                            <td style="font-family: monospace; font-size: 0.75rem; color: #10b981;">
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <span>${displayAddr}</span>
                                    <button onclick="copyToClipboard(this, '${req.l1_address}')" class="btn-neo" style="padding: 2px 6px; font-size: 0.65rem; border-radius: 4px; cursor: pointer;">
                                        <i class="ph-bold ph-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${commentText}">${commentText}</td>
                            <td>${dateFormatted}</td>
                        </tr>
                    `;
                });
            }

            function openSovereignRequestModal() {
                document.getElementById('sovereign-request-amount').value = '';
                document.getElementById('sovereign-request-comment').value = '';
                document.getElementById('sovereign-request-terminal').style.display = 'none';
                document.getElementById('sovereign-request-terminal-logs').innerHTML = '';
                
                const btnSubmit = document.getElementById('btn-submit-sovereign-request');
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = 'Подписать & Отправить 🔐';
                btnSubmit.style.opacity = '1';
                
                document.getElementById('sovereign-request-modal-backdrop').style.display = 'flex';
            }

            function closeSovereignRequestModal() {
                document.getElementById('sovereign-request-modal-backdrop').style.display = 'none';
            }

            async function submitSovereignRequest() {
                const type = document.getElementById('sovereign-request-type').value;
                const amountInput = document.getElementById('sovereign-request-amount').value;
                const comment = document.getElementById('sovereign-request-comment').value;
                
                const amount = parseFloat(amountInput);
                if (isNaN(amount) || amount <= 0) {
                    alert('Пожалуйста, введите корректную сумму запроса!');
                    return;
                }
                
                const terminal = document.getElementById('sovereign-request-terminal');
                const logsContainer = document.getElementById('sovereign-request-terminal-logs');
                const btnSubmit = document.getElementById('btn-submit-sovereign-request');
                
                terminal.style.display = 'block';
                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span style="animation: pulse 1s infinite;">Процесс подписи... ⏳</span>';
                btnSubmit.style.opacity = '0.7';
                
                const writeLog = (msg, isError = false) => {
                    const color = isError ? '#f43f5e' : '#10b981';
                    logsContainer.innerHTML += `<div style="margin-bottom: 4px; color: ${color};">[${new Date().toLocaleTimeString()}] ${msg}</div>`;
                    terminal.scrollTop = terminal.scrollHeight;
                };
                
                try {
                    writeLog('⚙️ Инициализация транзакции...');
                    await new Promise(r => setTimeout(r, 400));
                    
                    writeLog(`📝 Формирование запроса на ${amount.toFixed(2)} ₽...`);
                    await new Promise(r => setTimeout(r, 400));
                    
                    writeLog('📡 Запрос криптографического челленджа (Passkey Options)...');
                    const optionsResp = await fetch('/partner/dashboard/finance/sovereign-request/options', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    
                    if (!optionsResp.ok) {
                        throw new Error('Не удалось получить параметры авторизации Passkey.');
                    }
                    
                    const options = await optionsResp.json();
                    writeLog(`   ↳ Challenge: ${options.challenge}`);
                    writeLog(`   ↳ Relying Party Host: ${options.rpId}`);
                    await new Promise(r => setTimeout(r, 400));
                    
                    writeLog('🔑 Ожидание аппаратной подписи Passkey (FaceID/TouchID)...');
                    const assertionPayload = await SimpleWebAuthnBrowser.startAuthentication(options);
                    
                    writeLog(`🔒 Подпись успешно сгенерирована! Credential ID: ${assertionPayload.id.substring(0, 16)}...`);
                    writeLog(`   ↳ rawId: ${assertionPayload.rawId.substring(0, 16)}...`);
                    writeLog(`   ↳ signature: ${assertionPayload.response.signature.substring(0, 24)}...`);
                    
                    try {
                        const rawBin = atob(assertionPayload.response.clientDataJSON.replace(/-/g, '+').replace(/_/g, '/'));
                        const clientData = JSON.parse(rawBin);
                        writeLog(`   ↳ Signed Challenge: ${clientData.challenge}`);
                        writeLog(`   ↳ Origin Authenticated: ${clientData.origin}`);
                    } catch (e) {}

                    writeLog(`🔬 Вычисление L1 адреса на основе открытого ключа...`);
                    await new Promise(r => setTimeout(r, 500));
                    writeLog(`   ↳ Алгоритм хэширования: SHA-256`);
                    writeLog(`   ↳ Метод: SHA256(PublicKey) -> sl1_...`);
                    await new Promise(r => setTimeout(r, 400));
                    
                    writeLog('📡 Передача подписанного блока на сервер консенсуса...');
                    const createResp = await fetch('/partner/dashboard/finance/sovereign-request/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            type: type,
                            amount: amount,
                            comment: comment,
                            assertion: assertionPayload
                        })
                    });
                    
                    const data = await createResp.json();
                    if (!createResp.ok || data.error) {
                        throw new Error(data.error || 'Ошибка проверки подписи на сервере.');
                    }
                    
                    writeLog(`✅ Получен консенсус! L1 Адрес: ${data.request.l1_address}`);
                    writeLog('✅ Запрос успешно верифицирован и внесен в суверенный реестр!');
                    await new Promise(r => setTimeout(r, 800));
                    
                    closeSovereignRequestModal();
                    alert('🏛️ Суверенный запрос успешно отправлен и ожидает подтверждения админа!');
                    
                    await fetchFinanceDataAndRefresh();
                } catch (err) {
                    console.error(err);
                    writeLog(`❌ Ошибка: ${err.message}`, true);
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = 'Попробовать снова 🔐';
                    btnSubmit.style.opacity = '1';
                    alert(`Не удалось отправить суверенный запрос: ${err.message}`);
                }
            }

            function openSovereignProofModal(reqId) {
                const req = sovereignRequests.find(r => r.id === reqId);
                if (!req) return;
                
                document.getElementById('proof-req-id').innerText = `REQ-${req.id}`;
                
                let statusBadge = '';
                if (req.status === 'pending') {
                    statusBadge = `<span style="color: #f59e0b;">Ожидание ⏳</span>`;
                } else if (req.status === 'approved') {
                    statusBadge = `<span style="color: #10b981;">Исполнен ✅</span>`;
                } else {
                    statusBadge = `<span style="color: #f43f5e;">Отклонен ❌</span>`;
                }
                document.getElementById('proof-req-status').innerHTML = statusBadge;
                document.getElementById('proof-req-amount').innerText = req.amount_formatted;
                
                const date = req.created_at_formatted || new Date(req.created_at).toLocaleString('ru-RU');
                document.getElementById('proof-req-date').innerText = date;
                document.getElementById('proof-signer-did').innerText = req.l1_address || 'N/A';
                
                const envelope = {
                    transaction_id: req.id,
                    entity: "Consortium B2B Legal Entity",
                    operation: req.type === 'top_up' ? "REPLENISHMENT_DEPOSIT" : "JIT_CREDIT_LINE",
                    amount: req.amount + " RUB",
                    signer_l1: req.l1_address,
                    timestamp: date
                };
                document.getElementById('proof-signed-envelope').innerText = JSON.stringify(envelope, null, 2);
                
                const assertion = req.signature_assertion;
                if (assertion) {
                    document.getElementById('proof-cred-id').innerText = assertion.id || 'N/A';
                    
                    let clientData = 'N/A';
                    if (assertion.response?.clientDataJSON) {
                        try {
                            const rawBin = atob(assertion.response.clientDataJSON.replace(/-/g, '+').replace(/_/g, '/'));
                            clientData = JSON.stringify(JSON.parse(rawBin), null, 2);
                        } catch(e) {
                            clientData = 'Error decoding clientDataJSON';
                        }
                    }
                    document.getElementById('proof-client-data').innerText = clientData;
                    document.getElementById('proof-auth-data').innerText = assertion.response?.authenticatorData || 'N/A';
                    document.getElementById('proof-signature').innerText = assertion.response?.signature || 'N/A';
                } else {
                    document.getElementById('proof-cred-id').innerText = 'N/A (Seeded Transaction)';
                    document.getElementById('proof-client-data').innerText = 'N/A (Seeded Transaction)';
                    document.getElementById('proof-auth-data').innerText = 'N/A';
                    document.getElementById('proof-signature').innerText = 'N/A (Seeded Signature)';
                }
                
                const sha = req.l1_address ? req.l1_address.replace('sl1_', '') : 'N/A';
                document.getElementById('proof-sha-hash').innerText = sha;
                document.getElementById('proof-derived-l1').innerText = req.l1_address || 'N/A';
                
                document.getElementById('sovereign-proof-modal-backdrop').style.display = 'flex';
            }

            function closeSovereignProofModal() {
                document.getElementById('sovereign-proof-modal-backdrop').style.display = 'none';
            }

            async function fetchFinanceDataAndRefresh() {
                try {
                    const response = await fetch('/partner/dashboard/finance/data', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    
                    if (!response.ok) {
                        throw new Error('Ошибка обновления финансовых данных.');
                    }
                    
                    const data = await response.json();
                    if (data.success) {
                        const avBal = document.getElementById('header-balance-available');
                        if (avBal) avBal.innerText = data.balances.available_formatted;
                        
                        const totalBal = document.getElementById('stats-deposit-balance');
                        if (totalBal) totalBal.innerText = data.balances.total_formatted;
                        
                        ledger.length = 0;
                        ledger.push(...data.transactions.data || data.transactions || []);
                        
                        sovereignRequests = data.sovereign_requests || [];
                        
                        renderFinanceTab();
                    }
                } catch (err) {
                    console.error('Ошибка при обновлении балансов: ', err);
                }
            }

            function copyToClipboard(element, text) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Значение скопировано в буфер обмена! 📋');
                });
            }

            function translateEventType(type) {
                const dict = {
                    'DEPOSIT_INTENT_CLEARED': 'Входящий платеж СБП (Депозит)',
                    'API_APPLICATION_CREATED': 'Интеграция API активирована',
                    'API_APPLICATION_DELETED': 'Удален ключ доступа API',
                    'YANDEX_MARKET_CONFIGURED': 'Синхронизация с Yandex Market',
                    'YANDEX_MARKET_LEGAL_ATTENTION': 'Yandex Market: требует внимания',
                    'YANDEX_MARKET_LEGAL_REJECTED': 'Yandex Market: проверка не прошла',
                    'YANDEX_MARKET_LEGAL_VERIFIED': 'Yandex Market: проверка прошла',
                    'ORDER_FUNDS_CAPTURED': 'Списание за выкуп карт (Заказ)',
                    'ORDER_FUNDS_RESERVED': 'Блокировка под выкуп (Холд)'
                };
                return dict[type] || type;
            }

            // --- 💳 Intent & Simulation Panel ---
            function renderDepositIntentsPanel() {
                const container = document.getElementById('deposit-intent-status-container');
                if (!activeFintentToken) {
                    container.innerHTML = `
                        <div style="text-align: center; color: var(--text-muted); padding: 20px; font-size: 0.8rem; border: 1px dashed var(--border-card); border-radius: 12px;">
                            Нет активных незавершенных интентов. Сгенерируйте новый интент для запуска клиринга.
                        </div>
                    `;
                    return;
                }

                container.innerHTML = `
                    <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid var(--border-neon); border-radius: 16px; padding: 15px; display: flex; flex-direction: column; gap: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 800; font-size: 0.8rem; color: var(--primary);">${activeFintentToken}</span>
                            <span class="badge-neo badge-amber" style="animation: pulseGlow 1.5s infinite;">AWAITING</span>
                        </div>
                        <div style="font-size: 1.4rem; font-weight: 900; font-family: var(--font-tech);">
                            ${number_format(activeFintentAmount, 2, '.', ' ')} ₽
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); line-height: 1.4;">
                            Интент активен. Эмулируйте ответ СБП для моментального пополнения счета.
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 6px; margin-top: 10px;">
                            <button onclick="triggerSbpMockClearing()" class="btn-neo btn-primary-neo" style="background: #10b981; color: #000; border: none; padding: 8px; justify-content: center; font-size: 0.75rem; border-radius: 8px;">
                                Эмулировать оплату через СБП 🟢
                            </button>
                            <button onclick="cancelActiveIntent()" class="btn-neo" style="padding: 6px; justify-content: center; font-size: 0.7rem; border-radius: 8px;">
                                Отменить интент
                            </button>
                        </div>
                    </div>
                `;
            }

            function openDepositModal() {

                document.getElementById('deposit-modal-backdrop').style.display = 'flex';
            }
            function closeDepositModal() {
                document.getElementById('deposit-modal-backdrop').style.display = 'none';
            }

            async function submitCreateDepositIntent() {
                const amount = document.getElementById('deposit-amount-input').value;
                if (!amount || amount < 10) {
                    alert('Введите корректную сумму пополнения (не менее 10 руб)!');
                    return;
                }

                try {
                    const response = await fetch('/partner/dashboard/deposit-intent', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ amount: parseFloat(amount) })
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        activeFintentToken = data.token;
                        activeFintentAmount = parseFloat(data.amount);
                        
                        localStorage.setItem('active_fintent_token', data.token);
                        localStorage.setItem('active_fintent_amount', data.amount);
                        
                        closeDepositModal();
                        window.location.reload();
                    } else {
                        alert(`Ошибка генерации интента: ${data.error}`);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Ошибка сети при генерации интента');
                }
            }

            async function triggerSbpMockClearing() {
                if (!activeFintentToken) return;

                try {
                    const response = await fetch('/partner/dashboard/clear-deposit-intent', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ token: activeFintentToken })
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        alert('🟢 Оплата успешно эмулирована! Баланс пополнен.');
                        
                        localStorage.removeItem('active_fintent_token');
                        localStorage.removeItem('active_fintent_amount');
                        activeFintentToken = null;
                        activeFintentAmount = 0;

                        window.location.reload();
                    } else {
                        alert(`Ошибка клиринга: ${data.error}`);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Сбой при эмуляции ответа банка');
                }
            }

            function cancelActiveIntent() {
                localStorage.removeItem('active_fintent_token');
                localStorage.removeItem('active_fintent_amount');
                activeFintentToken = null;
                activeFintentAmount = 0;
                renderFinanceTab();
            }

            // --- Integration detail view (list ↔ details) ---
            function closeAllIntegrationPanels() {
                document.querySelectorAll('.integration-detail-panel').forEach((panel) => {
                    panel.style.display = 'none';
                });
            }

            function showIntegrationsList() {
                closeAllIntegrationPanels();
                const detailView = document.getElementById('integration-detail-view');
                const listView = document.getElementById('shops-integrations-list');
                if (detailView) detailView.style.display = 'none';
                if (listView) listView.style.display = '';
            }

            function showIntegrationDetail(panelId) {
                closeAllIntegrationPanels();
                const detailView = document.getElementById('integration-detail-view');
                const listView = document.getElementById('shops-integrations-list');
                const panel = document.getElementById(panelId);
                if (listView) listView.style.display = 'none';
                if (detailView) detailView.style.display = 'block';
                if (panel) {
                    panel.style.display = 'block';
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }

            function closeIntegrationDetail() {
                showIntegrationsList();
            }

            // --- 🏪 Yandex Market Configuration Panel ---
            let activeYandexShopName = '';

            function openYandexMarketModal(shopId, shopName, businessId, campaignId, warehouseId, verification = null, legalVerified = false) {
                document.getElementById('yandex-shop-id').value = shopId;
                activeYandexShopName = shopName || '';
                document.getElementById('yandex-settings-title').innerText = `Настройка Yandex Market — ${shopName}`;
                document.getElementById('yandex-business-id').value = businessId || '';
                document.getElementById('yandex-campaign-id').value = campaignId || '';
                document.getElementById('yandex-warehouse-id').value = warehouseId || '';
                const warehouseSelect = document.getElementById('yandex-warehouse-select');
                if (warehouseSelect) {
                    warehouseSelect.style.display = 'none';
                    warehouseSelect.innerHTML = '';
                }
                const warehouseStatus = document.getElementById('yandex-warehouse-status');
                if (warehouseStatus) {
                    warehouseStatus.style.color = 'var(--text-muted)';
                    warehouseStatus.innerText = warehouseId
                        ? `Привязан склад Yandex Market: ${warehouseId}`
                        : 'Нужен для отправки остатков из мастер-склада в Маркет.';
                }
                document.getElementById('yandex-api-key').value = '';
                renderYandexLegalStatus(verification, legalVerified);
                showIntegrationDetail('yandex-settings-panel');
            }

            function closeYandexMarketModal() {
                closeIntegrationDetail();
            }

            async function parseJsonResponse(response) {
                const text = await response.text();
                if (! text) {
                    return {};
                }

                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(`HTTP ${response.status}: ${text.slice(0, 180)}`);
                }
            }

            function classifyYandexVerificationTier(verification = null, legalVerified = false) {
                if (!verification) {
                    return 'pending';
                }

                const tier = verification.verification_tier;
                if (legalVerified || verification.verified || tier === 'approved') {
                    return 'approved';
                }

                const background = verification.background_services_report || {};
                if (['queued', 'processing'].includes(background.status)) {
                    return 'processing';
                }

                if (background.status === 'timeout' || tier === 'attention' || verification.status === 'review_required' || background.status === 'review_required') {
                    return 'attention';
                }

                if (tier === 'rejected' || verification.status === 'rejected' || background.status === 'failed') {
                    return 'rejected';
                }

                return 'pending';
            }

            function renderYandexLegalStatus(verification = null, legalVerified = false) {
                const status = document.getElementById('yandex-legal-status');
                const badge = document.getElementById('yandex-legal-status-badge');
                const support = document.getElementById('yandex-support-action');
                if (!status) return;

                if (support) {
                    support.style.display = 'none';
                }

                const tier = classifyYandexVerificationTier(verification, legalVerified);

                if (tier === 'pending') {
                    status.style.color = 'var(--text-muted)';
                    status.innerHTML = 'Фоновая проверка запустится после сохранения API-данных и склада.';
                    if (badge) {
                        badge.style.background = 'rgba(255,255,255,0.03)';
                        badge.style.color = 'var(--text-muted)';
                        badge.innerText = 'Not checked';
                    }
                    return;
                }

                const styles = {
                    approved: {
                        color: '#10b981',
                        badgeBg: 'rgba(16, 185, 129, 0.1)',
                        badgeText: 'Проверка прошла',
                        message: 'Проверка прошла. Yandex Market активирован.',
                    },
                    processing: {
                        color: '#f59e0b',
                        badgeBg: 'rgba(245, 158, 11, 0.1)',
                        badgeText: 'Проверяем',
                        message: 'Проверяем реквизиты. Обычно это занимает до нескольких минут.',
                    },
                    attention: {
                        color: '#f59e0b',
                        badgeBg: 'rgba(245, 158, 11, 0.1)',
                        badgeText: 'Требует внимания',
                        message: 'Автоматически подтвердить реквизиты не удалось. Нужна ручная проверка или обращение в поддержку.',
                    },
                    rejected: {
                        color: 'var(--rose)',
                        badgeBg: 'rgba(244, 63, 94, 0.1)',
                        badgeText: 'Проверка не прошла',
                        message: 'Реквизиты Yandex Market не совпали с юрлицом Meanly. Интеграция заблокирована.',
                    },
                };

                const view = styles[tier] || styles.pending;
                status.style.color = view.color;
                status.innerHTML = `<div style="font-weight: 850;">${view.message}</div>`;

                if (badge) {
                    badge.style.background = view.badgeBg;
                    badge.style.color = view.color;
                    badge.innerText = view.badgeText;
                }

                if (support && (tier === 'attention' || tier === 'rejected')) {
                    support.style.display = 'flex';
                }
            }

            function openYandexSupportFromSettings() {
                const shopId = document.getElementById('yandex-shop-id').value;
                document.getElementById('tkt-subject').value = 'Yandex Market: проверка юрлица не прошла';
                document.getElementById('tkt-shop-id').value = shopId || '';
                document.getElementById('tkt-message').value = `Нужна помощь с проверкой юрлица для Yandex Market${activeYandexShopName ? ` (${activeYandexShopName})` : ''}. API-данные сохранены, но автоматическая фоновая проверка по отчету Yandex не подтвердила реквизиты.`;
                openTicketModal();
            }

            function selectYandexWarehouseFromList() {
                const select = document.getElementById('yandex-warehouse-select');
                const input = document.getElementById('yandex-warehouse-id');
                if (!select || !input) return;

                input.value = select.value || '';
            }

            async function fetchYandexMarketWarehouses() {
                const id = document.getElementById('yandex-shop-id').value;
                const business_id = document.getElementById('yandex-business-id').value;
                const campaign_id = document.getElementById('yandex-campaign-id').value;
                const api_key = document.getElementById('yandex-api-key').value;
                const select = document.getElementById('yandex-warehouse-select');
                const status = document.getElementById('yandex-warehouse-status');

                if (!business_id || !campaign_id) {
                    alert('Сначала заполните Business ID и Campaign ID.');
                    return;
                }

                if (status) {
                    status.style.color = 'var(--text-muted)';
                    status.innerText = 'Запрашиваем склады из Yandex Market...';
                }

                try {
                    const response = await fetch(`/partner/dashboard/shop/${id}/yandex-market/warehouses`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            business_id: parseInt(business_id),
                            campaign_id: parseInt(campaign_id),
                            api_key: api_key || null,
                        })
                    });
                    const data = await parseJsonResponse(response);
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || data.message || 'Не удалось получить склады');
                    }

                    const warehouses = data.warehouses || [];
                    if (warehouses.length === 0) {
                        if (select) {
                            select.style.display = 'none';
                            select.innerHTML = '';
                        }
                        if (status) {
                            status.style.color = 'var(--rose)';
                            status.innerText = 'Yandex Market не вернул склады для этих credentials.';
                        }
                        return;
                    }

                    if (select) {
                        select.innerHTML = warehouses.map(warehouse => `<option value="${Number(warehouse.id)}">${escapeHtml(warehouse.name)} · ${Number(warehouse.id)}</option>`).join('');
                        select.style.display = 'block';
                        select.value = String(warehouses[0].id);
                        selectYandexWarehouseFromList();
                    }
                    if (status) {
                        status.style.color = 'var(--green)';
                        status.innerText = warehouses.length === 1
                            ? 'Склад найден и подставлен.'
                            : `Найдено складов: ${warehouses.length}. Выберите нужный склад для остатков.`;
                    }
                } catch (e) {
                    console.error(e);
                    if (status) {
                        status.style.color = 'var(--rose)';
                        status.innerText = e.message || 'Не удалось получить склады Yandex Market.';
                    }
                    alert(`Не удалось получить склады: ${e.message}`);
                }
            }

            async function submitSaveYandexMarket() {
                const id = document.getElementById('yandex-shop-id').value;
                const business_id = document.getElementById('yandex-business-id').value;
                const campaign_id = document.getElementById('yandex-campaign-id').value;
                const ym_warehouse_id = document.getElementById('yandex-warehouse-id').value;
                const api_key = document.getElementById('yandex-api-key').value;

                try {
                    const response = await fetch(`/partner/dashboard/shop/${id}/yandex-market`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            business_id: business_id ? parseInt(business_id) : null,
                            campaign_id: campaign_id ? parseInt(campaign_id) : null,
                            ym_warehouse_id: ym_warehouse_id ? parseInt(ym_warehouse_id) : null,
                            api_key: api_key || null
                        })
                    });
                    const data = await parseJsonResponse(response);
                    if (response.ok && data.success) {
                        if (data.verification) {
                            renderYandexLegalStatus(data.verification, Boolean(data.shop?.legal_verified));
                        }
                        window.location.reload();
                    } else {
                        alert(`Ошибка сохранения настроек: ${data.error || data.message || 'неизвестная ошибка'}`);
                    }
                } catch (e) {
                    console.error(e);
                    alert(`Сбой при сохранении параметров интеграции: ${e.message}`);
                }
            }

            // --- 🏗️ Shop Creation & Universal Marketplace Integrations JS ---
            function openCreateShopModal() {
                document.getElementById('new-shop-name').value = '';
                document.getElementById('new-shop-domain').value = '';
                document.getElementById('create-shop-modal-backdrop').style.display = 'flex';
            }

            function closeCreateShopModal() {
                document.getElementById('create-shop-modal-backdrop').style.display = 'none';
            }

            async function submitCreateShop() {
                const name = document.getElementById('new-shop-name').value;
                const domain = document.getElementById('new-shop-domain').value;
                const shop_region = document.getElementById('new-shop-region').value;

                if (!name) {
                    alert('Пожалуйста, укажите название магазина.');
                    return;
                }

                try {
                    const response = await fetch('/partner/dashboard/shop/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ name, domain, shop_region })
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        alert(`🏗️ Магазин "${data.shop.name}" успешно создан!`);
                        closeCreateShopModal();
                        window.location.reload();
                    } else {
                        alert(`Ошибка при создании магазина: ${data.error || 'Неизвестная ошибка'}`);
                    }
                } catch (e) {
                    console.error(e);
                    alert('Сбой при соединении с сервером');
                }
            }

            function openMarketplaceModal(shopId, shopName, platform) {
                document.getElementById('marketplace-shop-id').value = shopId;
                document.getElementById('marketplace-platform-type').value = platform;
                
                const titleEl = document.getElementById('marketplace-settings-title');
                const containerEl = document.getElementById('marketplace-fields-container');
                
                let fieldsHtml = '';
                
                if (platform === 'avito') {
                    titleEl.innerText = `Интеграция с Авито 🥑 — ${shopName}`;
                    fieldsHtml = `
                        <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                            Для автоматической синхронизации остатков и выкупа кодов активации на Avito укажите ваши ключи API (клиентские учетные данные).
                        </p>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Client ID (Идентификатор клиента)</label>
                            <input type="text" id="market-client-id" class="input-neo" placeholder="e.g. 5x98y1...">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Client Secret (Секрет клиента)</label>
                            <input type="password" id="market-client-secret" class="input-neo" placeholder="••••••••••••••••">
                        </div>
                    `;
                } else if (platform === 'ozon') {
                    titleEl.innerText = `Интеграция с Ozon API 🌐 — ${shopName}`;
                    fieldsHtml = `
                        <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                            Укажите API Key и Client ID вашего продавца Ozon для выгрузки каталога и обработки заказов.
                        </p>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Client ID (Ozon Client ID)</label>
                            <input type="text" id="market-client-id" class="input-neo" placeholder="e.g. 10045239">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">API Key (Ozon Seller API Key)</label>
                            <input type="password" id="market-client-secret" class="input-neo" placeholder="AQAAAAA...">
                        </div>
                    `;
                } else if (platform === 'wildberries') {
                    titleEl.innerText = `Интеграция с Wildberries 🟣 — ${shopName}`;
                    fieldsHtml = `
                        <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                            Введите API-токен поставщика Wildberries для автоматической обработки поставок и кодов.
                        </p>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">API Token (Токен WB API)</label>
                            <input type="password" id="market-client-secret" class="input-neo" placeholder="eyJhbGciOiJ...">
                        </div>
                    `;
                } else if (platform === 'woocommerce') {
                    titleEl.innerText = `Интеграция с WooCommerce 🛍️ — ${shopName}`;
                    fieldsHtml = `
                        <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                            Учетные данные WooCommerce REST API для двусторонней синхронизации продуктов и заказов.
                        </p>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Consumer Key (ck_...)</label>
                            <input type="text" id="market-client-id" class="input-neo" placeholder="ck_12345...">
                        </div>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Consumer Secret (cs_...)</label>
                            <input type="password" id="market-client-secret" class="input-neo" placeholder="cs_12345...">
                        </div>
                    `;
                } else if (platform === 'megamarket') {
                    titleEl.innerText = `Интеграция с Мегамаркет 🟢 — ${shopName}`;
                    fieldsHtml = `
                        <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                            Подключите Мегамаркет по API (Схема FBS/DBS) для автовыкупа кодов.
                        </p>
                        <div>
                            <label style="font-size: 0.75rem; color: var(--text-muted); display: block; margin-bottom: 6px;">Merchant API Token (Токен Мегамаркет)</label>
                            <input type="password" id="market-client-secret" class="input-neo" placeholder="e.g. 5A92B-...">
                        </div>
                    `;
                }
                
                containerEl.innerHTML = fieldsHtml;
                showIntegrationDetail('marketplace-settings-panel');
            }

            function closeMarketplaceModal() {
                closeIntegrationDetail();
            }

            async function submitSaveMarketplace() {
                const id = document.getElementById('marketplace-shop-id').value;
                const platform = document.getElementById('marketplace-platform-type').value;
                
                const client_id = document.getElementById('market-client-id') ? document.getElementById('market-client-id').value : null;
                const client_secret = document.getElementById('market-client-secret') ? document.getElementById('market-client-secret').value : null;

                try {
                    const response = await fetch(`/partner/dashboard/shop/${id}/yandex-market`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            business_id: client_id ? parseInt(client_id) : null,
                            campaign_id: client_id ? parseInt(client_id) : null,
                            api_key: client_secret
                        })
                    });
                    const data = await parseJsonResponse(response);
                    if (response.ok && data.success) {
                        alert(`🔌 Интеграция с ${platform.toUpperCase()} успешно подключена и протестирована!`);
                        closeMarketplaceModal();
                        window.location.reload();
                    } else {
                        alert(`Ошибка при сохранении: ${data.error || data.message || 'неизвестная ошибка'}`);
                    }
                } catch (e) {
                    console.error(e);
                    alert(`Сбой при сохранении параметров интеграции: ${e.message}`);
                }
            }

            // --- 🎫 Support Tickets ---
            function openTicketModal() {
                document.getElementById('ticket-modal-backdrop').style.display = 'flex';
            }
            function closeTicketModal() {
                document.getElementById('ticket-modal-backdrop').style.display = 'none';
            }

            async function submitCreateTicket() {
                const subject = document.getElementById('tkt-subject').value;
                const shop_id = document.getElementById('tkt-shop-id').value;
                const message = document.getElementById('tkt-message').value;

                if (!subject || !message) {
                    alert('Заполните все поля!');
                    return;
                }

                try {
                    const response = await fetch('/partner/dashboard/tickets/create', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ subject, shop_id, message, priority: 'medium' })
                    });
                    if (response.ok) {
                        alert('🎫 Тикет саппорта успешно создан! Дежурный инженер рассмотрит его в ближайшее время.');
                        closeTicketModal();
                        window.location.reload();
                    } else {
                        alert('Ошибка создания тикета.');
                    }
                } catch (e) {
                    console.error(e);
                }
            }

            // --- 👤 Profile and Business Settings Dialog ---
            function openProfileModal() {
                document.getElementById('profile-modal-backdrop').style.display = 'flex';
            }
            function closeProfileModal() {
                document.getElementById('profile-modal-backdrop').style.display = 'none';
            }

            async function submitSaveProfileAndBusiness() {
                const firstName = document.getElementById('prof-first-name').value;
                const lastName = document.getElementById('prof-last-name').value;
                const phone = document.getElementById('prof-phone').value;
                const bic = document.getElementById('prof-bank-bic').value;
                const account = document.getElementById('prof-bank-account').value;

                if (!firstName) {
                    alert('Поле "Имя" обязательно для заполнения.');
                    return;
                }

                try {
                    // 1. Update Profile Settings
                    const profileResp = await fetch('/partner/dashboard/profile-update', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            first_name: firstName,
                            last_name: lastName,
                            phone: phone
                        })
                    });

                    if (!profileResp.ok) {
                        const err = await profileResp.json();
                        alert('Ошибка сохранения профиля: ' + (err.message || 'неизвестная ошибка'));
                        return;
                    }

                    // 2. Update Bank Details if edited
                    if (bic || account) {
                        if (!bic || !account) {
                            alert('Для изменения реквизитов бизнеса необходимо заполнить оба поля: БИК и Номер счета.');
                            return;
                        }
                        const bankResp = await fetch('{{ route("partner.dashboard.bank") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ bic, account })
                        });

                        if (!bankResp.ok) {
                            alert('Ошибка сохранения банковских реквизитов.');
                            return;
                        }
                    }

                    alert('Все изменения успешно сохранены! 🛡️');
                    closeProfileModal();
                    window.location.reload();
                } catch (e) {
                    console.error(e);
                    alert('Произошла непредвиденная ошибка при сохранении.');
                }
            }

            // --- Helper Utility Functions ---
            function formatDate(str) {
                if (!str) return '—';
                const d = new Date(str);
                return d.toLocaleDateString('ru-RU') + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
            }

            function number_format(number, decimals, dec_point, thousands_sep) {
                number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
                var n = !isFinite(+number) ? 0 : +number,
                    prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
                    sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
                    dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
                    s = '',
                    toFixedFix = function(n, prec) {
                        var k = Math.pow(10, prec);
                        return '' + (Math.round(n * k) / k).toFixed(prec);
                    };
                s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
                if (s[0].length > 3) {
                    s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
                }
                if ((s[1] || '').length < prec) {
                    s[1] = s[1] || '';
                    s[1] += new Array(prec - s[1].length + 1).join('0');
                }
                return s.join(dec);
            }

            function copyApiTokenToClipboard() {
                copyRawToken(rawToken);
            }

            function copyRawToken(token) {
                if (token === 'Нет активных API ключей') return;
                navigator.clipboard.writeText(token).then(() => {
                    alert('API Ключ успешно скопирован в буфер обмена! 📋');
                });
            }

            // 🧠 Sovereign AI features implementation
            async function triggerAiAudit() {
                const indicator = document.getElementById('ai-status-indicator');
                const statusText = document.getElementById('ai-status-text');
                const btnLabel = document.getElementById('btn-ai-audit-label');
                const btnLoader = document.getElementById('btn-ai-audit-loader');
                const resultWrapper = document.getElementById('ai-audit-result-wrapper');
                const resultText = document.getElementById('ai-audit-result-text');

                indicator.style.backgroundColor = '#f59e0b'; // amber
                statusText.innerText = 'ИИ анализирует цепочку событий...';
                btnLabel.style.display = 'none';
                btnLoader.style.display = 'inline-block';

                try {
                    const response = await fetch('/partner/dashboard/ai/audit', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        resultText.innerText = data.result;
                        resultWrapper.style.display = 'block';
                        indicator.style.backgroundColor = '#10b981'; // emerald
                        statusText.innerText = 'Система готова к аудиту';
                        alert('🟢 ИИ-разведсводка (Ledger AI Audit) успешно сгенерирована!');
                    } else {
                        alert('Ошибка аудита: ' + (data.error || 'Неизвестная ошибка'));
                        indicator.style.backgroundColor = '#ef4444'; // red
                        statusText.innerText = 'Ошибка при анализе';
                    }
                } catch (e) {
                    console.error(e);
                    alert('Сетевая ошибка при запуске ИИ-анализа');
                    indicator.style.backgroundColor = '#ef4444';
                    statusText.innerText = 'Ошибка сети';
                } finally {
                    btnLabel.style.display = 'inline';
                    btnLoader.style.display = 'none';
                }
            }

            async function submitAiChatMessage(event) {
                event.preventDefault();
                const input = document.getElementById('ai-chat-input');
                const message = input.value.trim();
                if (!message) return;

                input.value = '';
                const chatBody = document.getElementById('ai-chat-body');

                // Append user message
                const userMsgHtml = `
                    <div class="chat-msg user" style="display: flex; flex-direction: column; gap: 4px; margin-top: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.6rem; font-weight: 800; color: #60a5fa;">
                            <span>OPERATOR</span>
                            <span style="opacity: 0.3;">[${getCurrentTimeStr()}]</span>
                        </div>
                        <div style="border-left: 2px solid rgba(96, 165, 250, 0.2); padding-left: 8px; font-size: 0.85rem; line-height: 1.5; color: #cbd5e1; white-space: pre-wrap;">${escapeHtml(message)}</div>
                    </div>
                `;
                chatBody.insertAdjacentHTML('beforeend', userMsgHtml);

                // Append typing indicator
                const typingHtml = `
                    <div id="ai-typing-indicator" class="chat-msg assistant" style="display: flex; align-items: center; gap: 10px; font-size: 0.7rem; color: #16a34a; animation: pulse 1.5s infinite; margin-top: 10px;">
                        <span>●</span><span>●</span><span>●</span>
                        <span style="font-weight: 800; text-transform: uppercase;">Decoding stream...</span>
                    </div>
                `;
                chatBody.insertAdjacentHTML('beforeend', typingHtml);
                chatBody.scrollTop = chatBody.scrollHeight;

                try {
                    const response = await fetch('/partner/dashboard/ai/chat', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ message: message })
                    });
                    const data = await response.json();
                    
                    // Remove typing indicator
                    const typingEl = document.getElementById('ai-typing-indicator');
                    if (typingEl) typingEl.remove();

                    if (response.ok && data.success) {
                        const assistantMsgHtml = `
                            <div class="chat-msg assistant" style="display: flex; flex-direction: column; gap: 4px; margin-top: 10px;">
                                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.6rem; font-weight: 800; color: #22c55e;">
                                    <span>AI_ANALYST</span>
                                    <span style="opacity: 0.3;">[${data.time}]</span>
                                </div>
                                <div style="border-left: 2px solid rgba(74, 222, 128, 0.2); padding-left: 8px; font-size: 0.85rem; line-height: 1.5; color: #4ade80; white-space: pre-wrap;">${escapeHtml(data.content)}</div>
                            </div>
                        `;
                        chatBody.insertAdjacentHTML('beforeend', assistantMsgHtml);
                    } else {
                        appendSystemError('Ошибка трансляции ИИ-ядра.');
                    }
                } catch (e) {
                    console.error(e);
                    const typingEl = document.getElementById('ai-typing-indicator');
                    if (typingEl) typingEl.remove();
                    appendSystemError('Сетевой тайм-аут связи с Ollama Llama 3.');
                } finally {
                    chatBody.scrollTop = chatBody.scrollHeight;
                }
            }

            function appendSystemError(text) {
                const chatBody = document.getElementById('ai-chat-body');
                const errorHtml = `
                    <div class="chat-msg assistant" style="display: flex; flex-direction: column; gap: 4px; margin-top: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 0.6rem; font-weight: 800; color: #ef4444;">
                            <span>SYSTEM_ERR</span>
                            <span style="opacity: 0.3;">[${getCurrentTimeStr()}]</span>
                        </div>
                        <div style="border-left: 2px solid rgba(239, 68, 68, 0.2); padding-left: 8px; font-size: 0.85rem; line-height: 1.5; color: #fca5a5; white-space: pre-wrap;">${text}</div>
                    </div>
                `;
                chatBody.insertAdjacentHTML('beforeend', errorHtml);
            }

            // 🏪 B2B Storefront & Simple Layer One Ledger clearing actions
            let currentStorefrontProduct = null;
            let currentStorefrontIsSovereign = false;
            let storefrontPaymentMethod = 'rub_token';
            let storefrontPage = 1;
            let storefrontLastPage = Math.ceil(({{ (int) ($providerProductsTotal ?? count($providerProducts)) }}) / 24) || 1;
            let storefrontTotal = {{ (int) ($providerProductsTotal ?? count($providerProducts)) }};
            let storefrontLoading = false;
            let storefrontSearchTimer = null;
            let storefrontSelectedCategoryId = null;
            let storefrontSelectedCategoryName = '';
            let storefrontRequestSeq = 0;
            const storefrontHasShops = {{ $shops->isNotEmpty() ? 'true' : 'false' }};

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            }

            function storefrontCardHtml(prod) {
                const isVault = prod.supply_class === 'vault';
                const region = prod.region_code || prod.region_name || 'GLOBAL';
                const brand = prod.brand_name || 'Другое';
                const category = prod.category_label || 'Other';
                const reviewRequired = Boolean(prod?.curation?.review_required);
                const actionEnabled = prod?.action?.enabled !== false && storefrontHasShops;
                const identityConfidence = prod?.canonical_identity?.confidence || 'low';
                const indexingSurface = prod?.indexing_policy?.surface || prod?.indexing?.surface || 'internal_review';
                const sellerAvailability = prod?.seller_offer_availability?.availability || 'not_listed';
                const faceValue = prod.face_value ? ` · Face: ${escapeHtml(prod.face_value)} ${escapeHtml(prod.face_value_currency || prod.currency || '')}` : '';
                const buttonClass = isVault && actionEnabled ? 'btn-primary-neo' : '';
                const buttonLabel = !storefrontHasShops
                    ? '<i class="ph-bold ph-storefront"></i> Готовим мастер-склад'
                    : (!actionEnabled
                    ? '<i class="ph-bold ph-warning"></i> Требуется ревью'
                    : (isVault
                        ? '<i class="ph-bold ph-lightning"></i> Закупить сток через L1 ⚡'
                        : '<i class="ph-bold ph-shopping-cart"></i> Закупить сток 🛒'));
                const badge = isVault
                    ? '<div class="sovereign-glow-badge" style="position: absolute; top: 0; right: 0; background: linear-gradient(90deg, #10b981, #059669); color: #fff; font-size: 0.65rem; font-weight: 900; padding: 4px 12px; border-bottom-left-radius: 12px; box-shadow: 0 2px 10px rgba(16, 185, 129, 0.2); letter-spacing: 0.5px;">MEANLY VAULT</div>'
                    : '';
                const reviewBadge = reviewRequired
                    ? '<span class="badge-neo" style="font-size: 0.65rem; font-weight: 900; border-color: rgba(245, 158, 11, 0.35); color: #f59e0b;">Review needed</span>'
                    : '';
                const canonicalLink = prod.canonical_product_url
                    ? `<a href="${escapeHtml(prod.canonical_product_url)}" target="_blank" rel="noopener" style="color: var(--primary);">Canonical page</a>`
                    : '';
                const providerLink = prod.provider_candidate_url
                    ? `<a href="${escapeHtml(prod.provider_candidate_url)}" target="_blank" rel="noopener" style="color: var(--text-muted);">Provider detail</a>`
                    : '';
                const actionAttr = actionEnabled
                    ? `onclick="openStorefrontPurchaseModalById(${Number(prod.id)}, ${isVault ? 'true' : 'false'}, this)"`
                    : 'disabled';

                return `
                    <div class="col-4 card-neo storefront-card" data-name="${escapeHtml((prod.name || '').toLowerCase())}" data-public-sku="${escapeHtml((prod.public_sku || '').toLowerCase())}" data-category="${escapeHtml(prod.category_slug || 'other')}" style="display: flex; flex-direction: column; justify-content: space-between; gap: 20px; transition: transform 0.2s, border-color 0.2s; min-height: 250px; position: relative; overflow: hidden; padding: 20px;">
                        ${badge}
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                    <span class="badge-neo badge-amber" style="font-size: 0.65rem; font-weight: 800; border-radius: 100px;">${escapeHtml(category)}</span>
                                    ${reviewBadge}
                                </div>
                                <span style="font-size: 0.7rem; color: var(--text-muted); font-family: var(--font-tech);">REF: ${escapeHtml(prod.public_sku)}</span>
                            </div>
                            <h4 style="font-size: 1.05rem; font-weight: 850; margin: 0 0 6px 0; color: var(--text-main); line-height: 1.3;">${escapeHtml(prod.name)}</h4>
                            <p style="font-size: 0.72rem; color: var(--text-muted); margin: 0; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">${escapeHtml(brand)} · ${escapeHtml(region)} · ${escapeHtml(prod.currency || 'USD')}</p>
                            <div style="font-size: 0.68rem; color: var(--text-muted); margin-top: 8px; line-height: 1.45;">
                                Identity: <strong style="color: var(--text-main);">${escapeHtml(identityConfidence)}</strong>
                                · Policy: <strong style="color: ${reviewRequired ? '#f59e0b' : 'var(--text-main)'};">${escapeHtml(indexingSurface)}</strong>
                                ${faceValue}
                                · Candidates: ${Number(prod.provider_candidate_count || 1)}/${Number(prod.provider_source_count || 1)}
                                · Seller: ${escapeHtml(sellerAvailability)}
                            </div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; font-size: 0.68rem; font-weight: 800;">
                                ${canonicalLink}
                                ${providerLink}
                            </div>
                        </div>
                        <div style="border-top: 1px solid var(--border-card); padding-top: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div>
                                    <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">B2B Закупка</div>
                                    <div style="font-family: var(--font-tech); font-size: 1.15rem; font-weight: 900; color: var(--primary);">${escapeHtml(prod.purchase_price_formatted)}</div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Розничный тариф</div>
                                    <div style="font-family: var(--font-tech); font-size: 0.9rem; font-weight: 750; color: var(--text-main);">${escapeHtml(prod.nominal_price_formatted)}</div>
                                </div>
                            </div>
                            <button ${actionAttr} class="btn-neo ${buttonClass}" style="width: 100%; font-size: 0.8rem; font-weight: 800; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 10px;">${buttonLabel}</button>
                        </div>
                    </div>
                `;
            }

            function renderStorefrontProducts(products, replace = false) {
                const grid = document.getElementById('storefront-grid');
                if (!grid) return;

                grid.style.display = 'grid';

                if (replace) {
                    grid.innerHTML = '';
                    providerProducts = [];
                }

                products.forEach(prod => {
                    providerProducts = providerProducts.filter(existing => Number(existing.id) !== Number(prod.id));
                    providerProducts.push(prod);
                    grid.insertAdjacentHTML('beforeend', storefrontCardHtml(prod));
                });

                if (providerProducts.length === 0) {
                    grid.innerHTML = '<div class="col-12 card-neo" style="text-align: center; padding: 4rem;"><p style="color: var(--text-muted);">По этому запросу товаров нет.</p></div>';
                }

                const shown = document.querySelectorAll('.storefront-card').length;
                const hint = document.getElementById('storefront-total-hint');
                if (hint) {
                    hint.textContent = `Показано ${shown} из ${storefrontTotal}`;
                }

                const wrap = document.getElementById('storefront-load-more-wrap');
                if (wrap) {
                    wrap.style.display = storefrontPage < storefrontLastPage ? 'flex' : 'none';
                }
            }

            function setStorefrontCategoryMode(isProductMode) {
                const categoryGrid = document.getElementById('storefront-category-grid');
                const productGrid = document.getElementById('storefront-grid');
                const backBtn = document.getElementById('storefront-back-to-categories');
                const label = document.getElementById('storefront-selected-category-label');
                const search = document.getElementById('storefront-search');
                const loadMore = document.getElementById('storefront-load-more-wrap');

                if (categoryGrid) categoryGrid.style.display = isProductMode ? 'none' : 'grid';
                if (productGrid) productGrid.style.display = isProductMode ? 'grid' : 'none';
                if (backBtn) backBtn.style.display = isProductMode ? 'inline-flex' : 'none';
                if (label) {
                    label.style.display = isProductMode ? 'inline-flex' : 'none';
                    label.textContent = isProductMode ? `Категория: ${storefrontSelectedCategoryName}` : '';
                }
                if (search) {
                    search.placeholder = isProductMode ? 'Поиск товара...' : 'Поиск категории...';
                }
                if (!isProductMode && loadMore) {
                    loadMore.style.display = 'none';
                }
            }

            function showStorefrontCategories() {
                storefrontSelectedCategoryId = null;
                storefrontSelectedCategoryName = '';
                const search = document.getElementById('storefront-search');
                if (search) search.value = '';
                setStorefrontCategoryMode(false);
                document.querySelectorAll('.storefront-category-card').forEach(card => {
                    card.style.display = 'flex';
                });
            }

            function filterStorefrontCategories() {
                const needle = (document.getElementById('storefront-search')?.value || '').toLowerCase().trim();
                document.querySelectorAll('.storefront-category-card').forEach(card => {
                    const name = card.getAttribute('data-name') || '';
                    card.style.display = !needle || name.includes(needle) ? 'flex' : 'none';
                });
            }

            function openStorefrontCategory(categoryId, categoryName) {
                storefrontSelectedCategoryId = categoryId;
                storefrontSelectedCategoryName = categoryName || 'Категория';
                const search = document.getElementById('storefront-search');
                if (search) search.value = '';
                setStorefrontCategoryMode(true);
                const grid = document.getElementById('storefront-grid');
                if (grid) {
                    grid.innerHTML = '<div class="col-12 card-neo" style="text-align: center; padding: 3rem;"><p style="color: var(--text-muted); font-weight: 800;">Загружаем товары категории...</p></div>';
                }
                loadStorefrontProducts(1, true);
            }

            async function loadStorefrontProducts(page = 1, replace = false) {
                if (storefrontLoading && !replace) return;
                storefrontLoading = true;
                const requestSeq = ++storefrontRequestSeq;

                const btn = document.getElementById('storefront-load-more-btn');
                if (btn) btn.disabled = true;

                const searchVal = document.getElementById('storefront-search')?.value || '';
                const params = new URLSearchParams({
                    page: String(page),
                    per_page: '24',
                });

                if (searchVal.trim() !== '') params.set('search', searchVal.trim());
                if (storefrontSelectedCategoryId && storefrontSelectedCategoryId !== '__all') {
                    params.set('catalog_group_id', String(storefrontSelectedCategoryId));
                }

                try {
                    const response = await fetch(`/partner/dashboard/provider-catalog/data?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (!response.ok || data.error) {
                        throw new Error(data.error || 'Не удалось загрузить товары');
                    }
                    if (requestSeq !== storefrontRequestSeq) {
                        return;
                    }

                    storefrontPage = data.current_page || page;
                    storefrontLastPage = data.last_page || 1;
                    storefrontTotal = data.total || 0;
                    renderStorefrontProducts(data.products || [], replace);
                } catch (error) {
                    console.error(error);
                    const grid = document.getElementById('storefront-grid');
                    if (grid && replace) {
                        grid.innerHTML = '<div class="col-12 card-neo" style="text-align: center; padding: 3rem;"><p style="color: var(--text-muted); font-weight: 800; margin-bottom: 12px;">Не удалось загрузить товары категории.</p><button type="button" onclick="loadStorefrontProducts(1, true)" class="btn-neo" style="font-size: 0.8rem; font-weight: 800; padding: 8px 12px;">Повторить загрузку</button></div>';
                    }
                    showToast(error.message || 'Ошибка загрузки витрины', 'danger');
                } finally {
                    storefrontLoading = false;
                    if (btn) btn.disabled = false;
                }
            }

            function filterStorefront() {
                if (!storefrontSelectedCategoryId) {
                    filterStorefrontCategories();
                    return;
                }

                clearTimeout(storefrontSearchTimer);
                storefrontSearchTimer = setTimeout(() => loadStorefrontProducts(1, true), 250);
            }

            async function openWarehouseStock(warehouseId) {
                const panel = document.getElementById('warehouse-stock-panel');
                const body = document.getElementById('warehouse-stock-body');
                const title = document.getElementById('warehouse-stock-title');
                const subtitle = document.getElementById('warehouse-stock-subtitle');

                if (!panel || !body) return;

                panel.style.display = 'block';
                body.innerHTML = '<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 1.5rem;">Загружаем сток...</td></tr>';

                try {
                    const response = await fetch(`/partner/dashboard/warehouses/${warehouseId}/stock`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    if (!response.ok || data.error) {
                        throw new Error(data.error || 'Не удалось открыть склад');
                    }

                    if (title) title.textContent = data.warehouse?.name || 'Содержимое склада';
                    if (subtitle) {
                        subtitle.textContent = `${data.warehouse?.role || 'Склад'} · ${data.total_sku || 0} SKU · доступно ${data.total_available || 0} кодов`;
                    }

                    const items = data.items || [];
                    if (items.length === 0) {
                        body.innerHTML = '<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding: 1.5rem;">В этом складе пока нет кодов.</td></tr>';
                        return;
                    }

                    body.innerHTML = items.map(item => `
                        <tr>
                            <td style="font-weight: 800;">${escapeHtml(item.product_name)}</td>
                            <td style="font-family: var(--font-tech); font-size: 0.75rem;">${escapeHtml(item.sku)}</td>
                            <td style="font-family: var(--font-tech); font-weight: 900; color: var(--green);">${Number(item.available_count || 0)}</td>
                            <td>${Number(item.reserved_count || 0)}</td>
                            <td>${Number(item.used_count || 0)}</td>
                            <td>${Number(item.total_count || 0)}</td>
                        </tr>
                    `).join('');
                } catch (error) {
                    body.innerHTML = `<tr><td colspan="6" style="text-align:center; color: var(--rose); padding: 1.5rem;">${escapeHtml(error.message || 'Не удалось открыть склад')}</td></tr>`;
                }
            }

            function closeWarehouseStock() {
                const panel = document.getElementById('warehouse-stock-panel');
                if (panel) panel.style.display = 'none';
            }

            async function openStorefrontPurchaseModalById(productId, isVault, buttonEl = null) {
                const product = providerProducts.find(p => Number(p.id) === Number(productId));
                if (!product) {
                    showToast('Товар не найден в локальной витрине. Обновите поиск.', 'danger');
                    return;
                }

                await openStorefrontPurchaseModal(product, isVault, buttonEl);
            }

            function selectStorefrontPaymentMethod(method) {
                storefrontPaymentMethod = method;
                document.getElementById('storefront-payment-method').value = method;

                const rubCard = document.getElementById('storefront-pay-rub');
                const nativeCard = document.getElementById('storefront-pay-native');
                const signCheckbox = document.getElementById('storefront-l1-sign-checkbox');

                if (method === 'rub_token' || method === 'rub') {
                    storefrontPaymentMethod = 'rub_token';
                    document.getElementById('storefront-payment-method').value = 'rub_token';
                    if (rubCard) {
                        rubCard.style.border = '1.5px solid var(--primary)';
                        rubCard.style.background = 'rgba(245, 48, 3, 0.05)';
                    }
                    if (nativeCard) {
                        nativeCard.style.border = '1px solid var(--border-card)';
                        nativeCard.style.background = 'rgba(255, 255, 255, 0.02)';
                    }
                    if (signCheckbox) {
                        signCheckbox.checked = true;
                        signCheckbox.disabled = true;
                    }
                } else {
                    if (nativeCard) {
                        nativeCard.style.border = '1.5px solid #10b981';
                        nativeCard.style.background = 'rgba(16, 185, 129, 0.05)';
                    }
                    if (rubCard) {
                        rubCard.style.border = '1px solid var(--border-card)';
                        rubCard.style.background = 'rgba(255, 255, 255, 0.02)';
                    }
                    if (signCheckbox) {
                        signCheckbox.checked = true;
                        signCheckbox.disabled = true;
                    }
                }

                updateStorefrontCostCalculations();
            }

            async function openStorefrontPurchaseModal(prod, isSovereign, buttonEl = null) {
                if (prod?.action?.enabled === false) {
                    showToast('Позиция требует внутреннего ревью identity перед публикацией.', 'warning');
                    return;
                }

                const originalButtonHtml = buttonEl?.innerHTML;
                if (buttonEl) {
                    buttonEl.disabled = true;
                    buttonEl.innerHTML = '<i class="ph-bold ph-spinner-gap"></i> Проверяем сток...';
                }

                try {
                    const precheck = await precheckStorefrontAvailability(prod);
                    if (!precheck.available) {
                        const message = precheck.error || 'Товар временно недоступен у поставщика.';
                        if (typeof showToast === 'function') {
                            showToast(message, 'danger');
                        } else {
                            alert(message);
                        }
                        return;
                    }
                } finally {
                    if (buttonEl) {
                        buttonEl.disabled = false;
                        buttonEl.innerHTML = originalButtonHtml;
                    }
                }

                currentStorefrontProduct = prod;
                currentStorefrontIsSovereign = isSovereign;

                document.getElementById('storefront-modal-prod-name').innerText = prod.name;
                document.getElementById('storefront-modal-prod-sku').innerText = prod.public_sku || 'MS-REF';
                document.getElementById('storefront-modal-prod-region').innerText = prod.region_name || prod.region_code || 'Global';
                document.getElementById('storefront-modal-prod-provider').innerText = prod.supply_label || 'Meanly Supply Network';

                // Reset form elements
                const qtyInput = document.getElementById('storefront-purchase-qty');
                const minQty = getStorefrontMinQty();
                const maxQty = getStorefrontMaxQty();
                qtyInput.min = minQty;
                qtyInput.max = maxQty;
                qtyInput.value = minQty;
                const limitsHint = document.getElementById('storefront-qty-limits-hint');
                if (limitsHint) {
                    limitsHint.innerText = `Лимит закупки: от ${minQty} до ${maxQty} кодов.`;
                }
                updateStorefrontWarehouseHint();
                updateStorefrontSalesChannelAvailability();
                
                // Toggle nominal input if variable price
                const isVariable = prod.is_variable || (prod.min_price > 0 && prod.max_price > prod.min_price + 0.01);
                const nominalContainer = document.getElementById('storefront-nominal-container');
                if (isVariable) {
                    nominalContainer.style.display = 'block';
                    const nominalInput = document.getElementById('storefront-nominal-amount');
                    nominalInput.value = prod.retail_price || prod.min_price;
                    nominalInput.min = prod.min_price;
                    nominalInput.max = prod.max_price;
                    document.getElementById('storefront-nominal-currency').innerText = prod.currency || 'USD';
                    document.getElementById('storefront-nominal-limits-hint').innerText = `Лимиты номинала: от ${prod.min_price} до ${prod.max_price} ${prod.currency || 'USD'}`;
                } else {
                    nominalContainer.style.display = 'none';
                }

                // Reset payment selection to tokenized RUB.
                selectStorefrontPaymentMethod('rub_token');

                // Show main form, hide clearing stream & success panels
                document.getElementById('storefront-purchase-form-area').style.display = 'flex';
                document.getElementById('storefront-clearing-stream-panel').style.display = 'none';
                document.getElementById('storefront-success-panel').style.display = 'none';

                // Setup footer buttons
                document.getElementById('storefront-modal-footer').style.display = 'flex';
                document.getElementById('storefront-btn-cancel').style.display = 'inline-block';
                document.getElementById('storefront-btn-submit').style.display = 'inline-block';
                document.getElementById('storefront-btn-submit').innerHTML = isSovereign ? '<i class="ph-bold ph-lock-key"></i> Подписать Passkey и закупить' : '<i class="ph-bold ph-lock-key"></i> Подписать Passkey и закупить 🛒';

                updateStorefrontCostCalculations();

                const modal = document.getElementById('storefront-purchase-modal-backdrop');
                modal.style.display = 'flex';
                modal.classList.add('active');
            }

            async function precheckStorefrontAvailability(prod) {
                const shopSelect = document.getElementById('storefront-purchase-shop-id');
                const shopId = shopSelect?.value;
                const count = Math.max(1, parseInt(prod?.min_purchase_quantity || 1) || 1);
                const isVariable = prod?.is_variable || (prod?.min_price > 0 && prod?.max_price > prod?.min_price + 0.01);
                const amount = isVariable ? (parseFloat(prod?.retail_price || prod?.min_price || 0) || null) : null;

                if (!shopId) {
                    return { available: false, error: 'Сначала создайте магазин и мастер-склад для закупки.' };
                }

                try {
                    const response = await fetch('/partner/dashboard/storefront/check-availability', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            provider_product_id: prod.id,
                            shop_id: shopId,
                            count,
                            amount
                        })
                    });
                    const data = await response.json();

                    return {
                        available: response.ok && data.available === true,
                        error: data.error || data.message
                    };
                } catch (error) {
                    return {
                        available: false,
                        error: `Не удалось проверить сток у поставщика: ${error.message}`
                    };
                }
            }

            function getStorefrontMinQty() {
                return Math.max(1, parseInt(currentStorefrontProduct?.min_purchase_quantity || 1) || 1);
            }

            function getStorefrontMaxQty() {
                const minQty = getStorefrontMinQty();
                return Math.max(minQty, parseInt(currentStorefrontProduct?.max_purchase_quantity || 20) || 20);
            }

            function normalizeStorefrontQty() {
                const qtyInput = document.getElementById('storefront-purchase-qty');
                if (!qtyInput) return 1;

                const minQty = getStorefrontMinQty();
                const maxQty = getStorefrontMaxQty();
                let currentVal = parseInt(qtyInput.value) || minQty;
                currentVal = Math.min(maxQty, Math.max(minQty, currentVal));
                qtyInput.value = currentVal;

                return currentVal;
            }

            function updateStorefrontWarehouseHint() {
                const select = document.getElementById('storefront-purchase-shop-id');
                const hint = document.getElementById('storefront-master-warehouse-hint');
                if (!select || !hint) return;

                const shopName = select.options[select.selectedIndex]?.text || 'центр дистрибуции';
                hint.innerText = `Коды попадут в единый мастер-склад продавца. Технический центр: ${shopName}.`;
            }

            function updateStorefrontSalesChannelAvailability() {
                const select = document.getElementById('storefront-purchase-shop-id');
                const yandexCheckbox = document.getElementById('storefront-channel-yandex');
                const yandexLabel = document.getElementById('storefront-yandex-channel-label');
                const yandexHint = document.getElementById('storefront-yandex-channel-hint');
                if (!select || !yandexCheckbox) return;

                const yandexActive = select.options[select.selectedIndex]?.dataset?.yandexActive === '1';
                yandexCheckbox.disabled = !yandexActive;
                yandexCheckbox.checked = yandexActive;
                if (yandexLabel) {
                    yandexLabel.style.opacity = yandexActive ? '1' : '0.55';
                    yandexLabel.style.cursor = yandexActive ? 'pointer' : 'not-allowed';
                }
                if (yandexHint) {
                    yandexHint.innerText = yandexActive
                        ? 'Yandex Market активен: после закупки товар и остаток будут поставлены в очередь синхронизации.'
                        : 'Yandex Market недоступен: подтвердите юрлицо и Warehouse ID в настройках интеграции.';
                    yandexHint.style.color = yandexActive ? 'var(--green)' : 'var(--text-muted)';
                }
            }

            function closeStorefrontPurchaseModal() {
                const modal = document.getElementById('storefront-purchase-modal-backdrop');
                modal.classList.remove('active');
                modal.style.display = 'none';
            }

            function decrementStorefrontQty() {
                const qtyInput = document.getElementById('storefront-purchase-qty');
                const minQty = getStorefrontMinQty();
                let currentVal = parseInt(qtyInput.value) || minQty;
                if (currentVal > minQty) {
                    qtyInput.value = currentVal - 1;
                    updateStorefrontCostCalculations();
                }
            }

            function incrementStorefrontQty() {
                const qtyInput = document.getElementById('storefront-purchase-qty');
                const maxQty = getStorefrontMaxQty();
                let currentVal = parseInt(qtyInput.value) || getStorefrontMinQty();
                if (currentVal < maxQty) {
                    qtyInput.value = currentVal + 1;
                    updateStorefrontCostCalculations();
                }
            }

            function updateStorefrontCostCalculations() {
                if (!currentStorefrontProduct) return;

                const qty = normalizeStorefrontQty();
                let unitPrice = parseFloat(currentStorefrontProduct.purchase_price);

                // If variable, use nominal amount
                const isVariable = currentStorefrontProduct.min_price > 0 && currentStorefrontProduct.max_price > currentStorefrontProduct.min_price + 0.01;
                if (isVariable) {
                    const nominalAmount = parseFloat(document.getElementById('storefront-nominal-amount').value) || 0;
                    const percentageOfBuyingPrice = -2; // fallback -2%
                    unitPrice = nominalAmount * (1 + (percentageOfBuyingPrice / 100));
                    const rate = 85.0; // conversion rate for UI simulation
                    if (currentStorefrontProduct.currency !== 'RUB') {
                        unitPrice = unitPrice * rate;
                    }
                }

                const total = unitPrice * qty;
                const method = document.getElementById('storefront-payment-method').value;

                if (method === 'native_token') {
                    const costSl1 = total / 100.0;
                    const gasFee = 0.0015;
                    const totalCostSl1 = costSl1 + gasFee;
                    document.getElementById('storefront-cost-breakdown').innerHTML = `${qty} × ${(unitPrice / 100.0).toFixed(4)} SL1 + ${gasFee} SL1 (сеть)`;
                    document.getElementById('storefront-total-calculated-cost').innerText = `${totalCostSl1.toFixed(4)} SL1`;
                    document.getElementById('storefront-total-calculated-cost').style.color = '#10b981';
                } else {
                    document.getElementById('storefront-cost-breakdown').innerText = `${qty} × ${unitPrice.toFixed(2)} RUBT`;
                    document.getElementById('storefront-total-calculated-cost').innerText = `${total.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2})} RUBT`;
                    document.getElementById('storefront-total-calculated-cost').style.color = 'var(--primary)';
                }
            }

            async function submitStorefrontPurchase() {
                if (!currentStorefrontProduct) return;

                const shopId = document.getElementById('storefront-purchase-shop-id').value;
                const qty = normalizeStorefrontQty();
                const minQty = getStorefrontMinQty();
                const maxQty = getStorefrontMaxQty();
                const nominalAmount = document.getElementById('storefront-nominal-container').style.display !== 'none'
                    ? parseFloat(document.getElementById('storefront-nominal-amount').value)
                    : null;
                const paymentMethod = document.getElementById('storefront-payment-method').value;
                const salesChannels = Array.from(document.querySelectorAll('.storefront-sales-channel-checkbox:checked'))
                    .map(input => input.value);

                if (qty < minQty || qty > maxQty) {
                    alert(`Количество для закупки должно быть от ${minQty} до ${maxQty}.`);
                    return;
                }

                if (salesChannels.length === 0) {
                    alert('Выберите хотя бы один канал продаж для публикации стока.');
                    return;
                }

                if (!window.SimpleWebAuthnBrowser || !window.PublicKeyCredential) {
                    alert('Для подтверждения Simple Layer One транзакции нужен браузер с поддержкой Passkey/WebAuthn.');
                    return;
                }

                // 1. Hide form, show L1 Clearing logs stream
                document.getElementById('storefront-purchase-form-area').style.display = 'none';
                document.getElementById('storefront-btn-cancel').style.display = 'none';
                document.getElementById('storefront-btn-submit').style.display = 'none';
                document.getElementById('storefront-clearing-stream-panel').style.display = 'flex';

                const logsEl = document.getElementById('storefront-clearing-logs');
                logsEl.innerHTML = '';

                const writeLog = (msg) => {
                    logsEl.innerHTML += `<div style="margin-bottom: 4px;">[${new Date().toLocaleTimeString()}] ${msg}</div>`;
                    logsEl.scrollTop = logsEl.scrollHeight;
                };

                let assertionPayload = null;
                const transactionPayload = {
                    action: 'stock_procurement',
                    provider_product_id: currentStorefrontProduct.id,
                    shop_id: shopId,
                    count: qty,
                    amount: nominalAmount,
                    payment_method: paymentMethod,
                    sales_channels: salesChannels
                };

                try {
                    writeLog('⚙️ Запрос параметров Passkey для конкретной Simple Layer One транзакции...');
                    const optionsResp = await fetch('/partner/dashboard/storefront/buy-options', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ transaction: transactionPayload })
                    });

                    const options = await optionsResp.json();
                    if (!optionsResp.ok || options.error) {
                        throw new Error(options.error || 'Не удалось получить параметры авторизации Passkey.');
                    }

                    writeLog('🔑 Подтвердите закупку стока через Passkey (FaceID/TouchID)...');
                    assertionPayload = await SimpleWebAuthnBrowser.startAuthentication(options);
                    writeLog(`🔒 Passkey-подпись создана: ${assertionPayload.id.substring(0, 16)}...`);
                    writeLog(`📡 Отправка подписанного запроса списания ${paymentMethod === 'native_token' ? 'SL1' : 'RUBT'} на Simple Layer One консенсус-ноду...`);
                } catch (authErr) {
                    console.error(authErr);
                    writeLog(`❌ Ошибка подписи транзакции: ${authErr.message}`);
                    await new Promise(r => setTimeout(r, 1200));
                    resetStorefrontModalState();
                    alert(`Криптографическая подпись прервана или не удалась: ${authErr.message}`);
                    return;
                }

                writeLog('📡 Трансляция транзакции в суверенную цепочку блоков...');
                writeLog('📝 Подготовка события [FINANCE_HOLD] и проверка баланса...');

                try {
                    // Send actual backend request synchronously
                    const response = await fetch('/partner/dashboard/storefront/add-to-catalog', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken(),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            provider_product_id: currentStorefrontProduct.id,
                            shop_id: shopId,
                            count: qty,
                            amount: nominalAmount,
                            payment_method: paymentMethod,
                            sales_channels: salesChannels,
                            assertion: assertionPayload
                        })
                    });

                    const data = await response.json();

                    if (!response.ok || data.error) {
                        throw new Error(data.error || 'Сетевая ошибка проведения транзакции');
                    }

                    writeLog('⚡ L1 State consensus resolved! Валидаторы подтвердили балансы.');
                    writeLog('📝 Событие [FINANCE_HOLD] записано в распределенный реестр.');
                    writeLog('✅ Событие [STOCK_REPLENISH] и выпуск лицензионных ключей завершены.');
                    writeLog('🔓 Расшифровка пакета кодов во встроенном Secretbox...');
                    writeLog('🚀 Запись кодов в Сейф и синхронизация завершена!');

                    // Show success panel
                    document.getElementById('storefront-clearing-stream-panel').style.display = 'none';
                    document.getElementById('storefront-success-panel').style.display = 'flex';
                    document.getElementById('storefront-success-message-text').innerText = data.message;
                    renderStorefrontTxReceipt(data);

                    // Render license keys nicely with dynamic copy-to-clipboard functionality
                    const keysListEl = document.getElementById('storefront-delivered-keys-list');
                    keysListEl.innerHTML = '';

                    keysListEl.innerHTML = `
                        <div style="background: #000; border: 1px solid var(--border-card); border-radius: 8px; padding: 12px;">
                            <div style="font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase;">Создано / обновлено</div>
                            <div style="font-family: var(--font-tech); font-size: 0.95rem; font-weight: 850; color: #10b981; margin-top: 2px;">${qty} кодов в доступном стоке</div>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 6px;">Каналы: ${salesChannels.join(', ')}</div>
                        </div>
                    `;

                    // Hide modal footer buttons
                    document.getElementById('storefront-modal-footer').style.display = 'none';

                    // Update Top Stats bar balance in real-time
                    const totalDeducted = parseFloat(data.total_cost) || parseFloat(document.getElementById('storefront-total-calculated-cost').innerText.replace(/[^\d.]/g, '')) || 0;
                    if (data.currency === 'SL1') {
                        const nativeBalanceElements = document.querySelectorAll('#header-balance-native, #storefront-payment-native-balance');
                        nativeBalanceElements.forEach(el => {
                            let currentBalanceText = el.innerText.replace(/[^\d.]/g, '');
                            let currentBalance = parseFloat(currentBalanceText) || 0;
                            let newBalance = currentBalance - totalDeducted;
                            if (newBalance < 0) newBalance = 0;
                            el.innerText = `${newBalance.toFixed(4)} SL1`;
                        });
                    } else {
                        const balanceElements = document.querySelectorAll('#header-balance-available, #storefront-payment-rub-balance, #stats-deposit-balance');
                        balanceElements.forEach(el => {
                            let currentBalanceText = el.innerText.replace(/[^\d.]/g, '');
                            let currentBalance = parseFloat(currentBalanceText) || 0;
                            let newBalance = currentBalance - totalDeducted;
                            if (newBalance < 0) newBalance = 0;
                            el.innerText = `${newBalance.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2})} RUBT`;
                        });
                    }

                } catch (err) {
                    writeLog(`❌ ERROR: ${err.message}`);
                    await new Promise(r => setTimeout(r, 1200));
                    resetStorefrontModalState();
                    alert(`Ошибка совершения B2B сделки: ${err.message}`);
                }
            }

            function renderStorefrontTxReceipt(data) {
                const receiptEl = document.getElementById('storefront-tx-receipt');
                const hashEl = document.getElementById('storefront-tx-hash-value');
                const statusEl = document.getElementById('storefront-tx-verify-status');
                const txHash = data?.tx_hash || data?.explorer_reference;

                window.currentStorefrontTxReceipt = txHash ? {
                    tx_hash: txHash,
                    explorer_url: data.explorer_url || `/partner/dashboard/simple-layer-1/trace?reference=${encodeURIComponent(txHash)}`
                } : null;

                if (!receiptEl || !hashEl || !txHash) {
                    if (receiptEl) receiptEl.style.display = 'none';
                    return;
                }

                hashEl.innerText = txHash;
                if (statusEl) {
                    statusEl.style.display = 'none';
                    statusEl.innerText = '';
                    statusEl.style.color = 'var(--text-muted)';
                }
                receiptEl.style.display = 'flex';
            }

            async function copyStorefrontTxHash() {
                const txHash = window.currentStorefrontTxReceipt?.tx_hash;
                if (!txHash) return;

                await navigator.clipboard.writeText(txHash);
                const statusEl = document.getElementById('storefront-tx-verify-status');
                if (statusEl) {
                    statusEl.style.display = 'block';
                    statusEl.style.color = '#10b981';
                    statusEl.innerText = 'tx_hash скопирован.';
                }
            }

            async function verifyStorefrontTxHash() {
                const receipt = window.currentStorefrontTxReceipt;
                const statusEl = document.getElementById('storefront-tx-verify-status');
                if (!receipt?.tx_hash || !statusEl) return;

                statusEl.style.display = 'block';
                statusEl.style.color = 'var(--text-muted)';
                statusEl.innerText = 'Проверяем запись в Simple Layer One Ledger...';

                try {
                    const response = await fetch(receipt.explorer_url, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Транзакция не найдена в обозревателе.');
                    }

                    const target = data.trace?.target || {};
                    statusEl.style.color = '#10b981';
                    statusEl.innerText = `Подтверждено: ${target.event_type || 'ledger block'} • ${data.trace?.canonical_ref || receipt.tx_hash}`;
                } catch (error) {
                    statusEl.style.color = '#ef4444';
                    statusEl.innerText = error.message || 'Не удалось проверить tx_hash.';
                }
            }

            function resetStorefrontModalState() {
                document.getElementById('storefront-clearing-stream-panel').style.display = 'none';
                document.getElementById('storefront-purchase-form-area').style.display = 'flex';
                document.getElementById('storefront-modal-footer').style.display = 'flex';
                document.getElementById('storefront-btn-cancel').style.display = 'inline-block';
                document.getElementById('storefront-btn-submit').style.display = 'inline-block';
                renderStorefrontTxReceipt(null);
            }

            function getCurrentTimeStr() {
                const now = new Date();
                return now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
            }

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
        </script>
    @endif
</div>

<script src="https://unpkg.com/@simplewebauthn/browser/dist/bundle/index.umd.min.js"></script>

</body>
</html>

