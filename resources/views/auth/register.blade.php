<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.theme-sync')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Создание аккаунта — Meanly</title>
    <style>
        :root { color-scheme: dark; }
        body { margin: 0; min-height: 100vh; font-family: Instrument Sans, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #030303; color: #fff; }
        .sovereign-auth-wrapper { min-height: 100vh; display: grid; place-items: center; padding: 24px; background: radial-gradient(circle at 50% 0%, rgba(245,48,3,.12), transparent 36%), #030303; }
        .auth-card { width: min(100%, 480px); background: #090909; border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 34px; box-shadow: 0 24px 60px rgba(0,0,0,.55); text-align: center; }
        .logo-header { color: #fff; display: inline-flex; align-items: center; gap: 10px; font-weight: 850; letter-spacing: -.02em; text-decoration: none; margin-bottom: 28px; }
        .logo-mark { width: 12px; height: 12px; border-radius: 3px; background: #f53003; box-shadow: 0 0 18px rgba(245,48,3,.7); }
        .auth-title { font-size: 24px; margin: 0 0 10px; letter-spacing: -.03em; }
        .auth-subtitle { color: #9ca3af; font-size: 14px; line-height: 1.5; margin: 0 0 24px; }
        .neo-field-group { text-align: left; margin-bottom: 18px; }
        .neo-label { display: block; font-size: 11px; color: #9ca3af; font-weight: 850; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 8px; }
        .neo-input { width: 100%; box-sizing: border-box; border: 2px solid rgba(255,255,255,.12); background: #111; color: #fff; border-radius: 10px; padding: 13px 14px; font: inherit; outline: none; }
        .neo-input:focus { border-color: #f53003; box-shadow: 0 0 0 4px rgba(245,48,3,.12); }
        .neo-help { color: #737373; font-size: 12px; line-height: 1.4; margin: 8px 0 0; }
        .profile-note { text-align: left; border: 1px solid rgba(245,48,3,.2); background: rgba(245,48,3,.07); border-radius: 12px; padding: 14px; margin-bottom: 18px; }
        .profile-note-title { font-size: 12px; font-weight: 850; color: #fff; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        .profile-note-copy { color: #c4c4c4; font-size: 13px; line-height: 1.45; }
        .profile-error { display: none; border: 1px solid rgba(248,113,113,.35); color: #fecaca; background: rgba(127,29,29,.32); border-radius: 10px; padding: 12px; font-size: 13px; margin-bottom: 16px; text-align: left; }
        .profile-error.is-visible { display: block; }
        .fi-btn, .sovereign-secondary-btn { width: 100%; box-sizing: border-box; display: inline-flex; justify-content: center; align-items: center; border-radius: 10px; padding: 13px 16px; border: 1px solid #f53003; background: #f53003; color: #fff; text-decoration: none; font-weight: 800; cursor: pointer; }
        .fi-btn:disabled { opacity: .65; cursor: wait; }
        .sovereign-secondary-btn { background: #111; border-color: rgba(255,255,255,.12); margin-top: 12px; }
        .footer-brand { color: #555; font-size: 10px; font-weight: 850; letter-spacing: .14em; text-align: center; margin-top: 18px; }
    </style>
</head>
<body>
@include('partials.theme-sync-body')
<div id="sovereign-auth-root" class="sovereign-auth-wrapper" data-theme="{{ $currentTheme ?? request()->cookie('theme') ?? 'consortium' }}" data-holiday="{{ request()->cookie('holiday') }}">
    <main class="auth-card">
        <a class="logo-header" href="{{ route('home') }}" aria-label="На главную Meanly"><span class="logo-mark"></span>MEANLY</a>
        <h1 class="auth-title">Создание аккаунта</h1>
        <p class="auth-subtitle">Создайте профиль без почты. Вход будет подтверждаться этим устройством через биометрию или PIN.</p>
        <form id="sl1-register-form">
            @csrf
            <input type="hidden" name="registration_target" value="profile">
            <div class="neo-field-group">
                <label for="display-name" class="neo-label">Как вас называть?</label>
                <input id="display-name" class="neo-input" name="display_name" type="text" maxlength="80" placeholder="Например, Selim" autocomplete="name" required>
                <p class="neo-help">Это имя видно в кабинете и при входе. Его можно изменить позже.</p>
            </div>
            <div class="profile-note">
                <div class="profile-note-title">Профиль входа</div>
                <div class="profile-note-copy">Аккаунт создается без email. Это устройство станет способом входа, а профиль можно будет использовать и на других устройствах.</div>
            </div>
            <div id="profile-error" class="profile-error" role="alert" aria-live="polite"></div>
            <button type="submit" id="sl1-register-submit" class="fi-btn">Создать профиль</button>
        </form>
        <a href="{{ route('login') }}" class="sovereign-secondary-btn">Войти безопасно</a>
        <div class="footer-brand">CABINET.MEANLY.SYSTEMS</div>
    </main>
</div>
<script src="https://unpkg.com/@simplewebauthn/browser@13.3.0/dist/bundle/index.umd.min.js"></script>
<script>
(() => {
    const form = document.getElementById('sl1-register-form');
    const submit = document.getElementById('sl1-register-submit');
    const errorBox = document.getElementById('profile-error');
    if (!form || !submit) return;

    const normalizeDisplayName = (value) => value.replace(/\s+/g, ' ').trim();
    const setError = (message = '') => {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.classList.toggle('is-visible', Boolean(message));
    };
    const friendlyPasskeyError = (error) => error?.name === 'NotAllowedError'
        ? 'Создание профиля отменено. Нажмите «Создать профиль», когда будете готовы подтвердить вход на этом устройстве.'
        : (error?.message || 'Попробуйте еще раз.');
    const requestJson = async (url, payload, csrfToken) => {
        const response = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.error || data.message || `HTTP ${response.status}`);
        return data;
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;
        setError('');
        const displayNameInput = form.querySelector('input[name="display_name"]');
        const displayName = normalizeDisplayName(displayNameInput?.value || '');
        if (displayNameInput) displayNameInput.value = displayName;
        if (!displayName) { setError('Введите имя владельца профиля.'); displayNameInput?.focus(); return; }
        form.dataset.submitting = 'true'; submit.disabled = true; submit.textContent = 'Создаем профиль...';
        try {
            if (!window.isSecureContext) throw new Error('Для безопасного входа нужен HTTPS или secure local domain.');
            if (!window.SimpleWebAuthnBrowser?.startRegistration) throw new Error('Браузерный модуль Passkey не загрузился. Обновите страницу.');
            const csrfInput = form.querySelector('input[name="_token"]');
            const csrf = csrfInput?.value || document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
            const optionsPayload = await requestJson(@json(route('business.register.options')), { registration_target: 'profile', display_name: displayName }, csrf);
            if (csrfInput && optionsPayload.new_csrf) csrfInput.value = optionsPayload.new_csrf;
            const attestation = await window.SimpleWebAuthnBrowser.startRegistration({ optionsJSON: optionsPayload.options });
            await requestJson(@json(route('business.register.submit')), { registration_target: 'profile', display_name: displayName, passkey_attestation: JSON.stringify(attestation) }, csrfInput?.value || optionsPayload.new_csrf || csrf);
            window.location.assign('/cabinet');
        } catch (error) {
            console.error('Profile registration failed', error);
            setError(friendlyPasskeyError(error));
            form.dataset.submitting = 'false'; submit.disabled = false; submit.textContent = 'Создать профиль';
        }
    });
})();
</script>
</body>
</html>
