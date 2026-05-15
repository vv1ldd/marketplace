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
            <p>Укажите ИНН вашей организации для начала работы с Кернелом.</p>
        </div>

        @if($errors->any())
            <div style="color: #f87171; font-size: 0.85rem; margin-bottom: 1rem; text-align: center;">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('partner.register.submit') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label class="form-label">ИНН организации</label>
                <input type="text" name="inn" id="inn-field" class="form-input" placeholder="7700123456" required value="{{ old('inn') }}" autocomplete="off">
            </div>

            <div class="form-group" id="name-container" style="display: none; transition: all 0.3s ease; opacity: 0; margin-bottom: 1.5rem;">
                <label class="form-label">Официальное название (автоматически)</label>
                <input type="text" name="legal_name" id="name-field" class="form-input" readonly style="background: rgba(0,255,0,0.05); border-color: rgba(0,255,0,0.2); color: #10b981; font-weight: 600;">
                <div style="font-size: 0.75rem; color: #10b981; margin-top: 0.4rem; font-weight: 700; display: flex; align-items: center; gap: 4px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    VERIFIED BY DADATA & ANCHORED IN SIMPLE-L1
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Рабочий Email</label>
                <input type="email" name="email" class="form-input" placeholder="ivan@company.com" required value="{{ old('email') }}">
            </div>

            <!-- 💰 Tax System (New Section) -->
            <div id="tax-section" style="margin-top: 1.5rem; display: none; animation: slideDown 0.4s ease forwards;">
                <label style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.5rem; display: block;">Система налогообложения</label>
                <select name="tax_system" id="tax_system" class="form-input" style="height: 44px; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22/%3E%3C/svg%3E'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 0.65rem auto;">
                    <option value="OSN">ОСНО (Общая система)</option>
                    <option value="USN">УСН (Упрощенная система)</option>
                    <option value="USN_INCOME">УСН Доходы</option>
                    <option value="NPD">НПД (Самозанятый)</option>
                </select>
            </div>

            <!-- Fallback Fields (Manual Entry for IPs or when DaData fails) -->
            <style>
                @keyframes slideDown {
                    from { opacity: 0; transform: translateY(-10px); }
                    to { opacity: 1; transform: translateY(0); }
                }
                .fallback-active {
                    animation: slideDown 0.4s ease forwards;
                    display: block !important;
                }
            </style>
            <div id="fallback-fields" style="display: none; margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <p id="fallback-message" style="font-size: 0.75rem; color: var(--amber); margin-bottom: 1rem;">
                    Не удалось автоматически найти данные. Пожалуйста, введите их вручную.
                </p>
                <div id="manual-name-group" class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.5rem; display: block;">Полное название организации</label>
                    <input type="text" name="legal_name" id="manual_legal_name" class="form-input" placeholder='ООО "КОМПАНИЯ"'>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.5rem; display: block;">ОГРН</label>
                    <input type="text" name="ogrn" id="manual_ogrn" class="form-input" placeholder="1234567890123">
                </div>
                <div class="form-group">
                    <label id="address-label" style="font-size: 0.75rem; color: var(--muted); margin-bottom: 0.5rem; display: block;">Юридический адрес</label>
                    <textarea name="address" id="manual_address" class="form-input" style="height: 60px;"></textarea>
                </div>
            </div>

            <div id="background-data"></div> <!-- Container for hidden inputs -->

            <button type="submit" class="btn-submit" id="submit-btn">Начать регистрацию →</button>
        </form>

        <div class="auth-footer">
            Уже есть аккаунт? <a href="/partner">Войти</a>
        </div>
    </div>
</div>

<script>
    const innInput = document.getElementById('inn-field');
    const submitBtn = document.getElementById('submit-btn');
    const fallbackFields = document.getElementById('fallback-fields');
    const taxSection = document.getElementById('tax-section');
    const bgData = document.getElementById('background-data');
    const nameField = document.getElementById('name-field');
    const nameContainer = document.getElementById('name-container');
    
    let typingTimer;

    const handleInput = () => {
        clearTimeout(typingTimer);
        const inn = innInput.value.trim();
        console.log('INN Input:', inn);
        if (inn.length === 10 || inn.length === 12) {
            searchINN();
        } else if (inn.length > 12) {
            typingTimer = setTimeout(searchINN, 300);
        }
    };

    innInput.addEventListener('input', handleInput);
    innInput.addEventListener('paste', () => setTimeout(handleInput, 100));

    async function searchINN() {
        const inn = innInput.value.trim();
        if (!inn) return;

        console.log('Searching for INN (POST):', inn);
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
            console.log('Search Result:', data);
            bgData.innerHTML = ''; // Clear previous

            if (data.suggestions && data.suggestions.length > 0) {
                const org = data.suggestions[0];
                const d = org.data;
                
                nameContainer.style.opacity = '1';
                nameField.value = org.value;
                
                // Populate Background Data
                addHidden('legal_name', org.value);
                addHidden('ogrn', d.ogrn);
                addHidden('kpp', d.kpp || '');
                addHidden('address', d.address ? d.address.value : '');

                // 💰 Deep Search for Tax System
                taxSection.style.display = 'block';
                let detectedTax = d.tax_system || (d.finance ? d.finance.tax_system : null);
                
                const taxMap = {
                    'ОСН': 'OSN', 'ОСНО': 'OSN',
                    'УСН': 'USN', 'УСНО': 'USN',
                    'ЕНВД': 'OSN',
                    'ЕСХН': 'USN',
                    'ПСН': 'USN',
                    'НПД': 'NPD'
                };

                const taxValue = detectedTax ? (taxMap[detectedTax.toUpperCase()] || 'OSN') : (org.is_ip ? 'USN' : 'OSN');
                document.getElementById('tax_system').value = taxValue;

                if (org.is_ip) {
                    fallbackFields.classList.add('fallback-active');
                    document.getElementById('manual-name-group').style.display = 'none';
                    document.getElementById('fallback-message').textContent = 'Для ИП и самозанятых необходимо подтвердить адрес регистрации:';
                    document.getElementById('address-label').textContent = 'Адрес регистрации';
                    document.getElementById('manual_address').value = d.address ? d.address.value : '';
                    document.getElementById('manual_ogrn').value = d.ogrn;
                } else {
                    fallbackFields.classList.remove('fallback-active');
                    fallbackFields.style.display = 'none';
                }

                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            } else if (data.fallback) {
                nameField.value = "ИНН не найден в реестре";
                nameContainer.style.opacity = '1';
                taxSection.style.display = 'block';
                fallbackFields.classList.add('fallback-active');
                document.getElementById('manual-name-group').style.display = 'block';
                document.getElementById('fallback-message').textContent = 'Не удалось найти данные. Пожалуйста, введите их вручную:';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
            } else {
                nameField.value = "Ничего не найдено";
                nameContainer.style.opacity = '1';
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
