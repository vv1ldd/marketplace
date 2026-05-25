<footer class="marketplace-footer">
    <div class="footer-grid">
        <div class="footer-brand-block">
            <a class="footer-logo" href="{{ route('home') }}"><span class="logo-mark"></span> MEANLY</a>
            <p>Маркетплейс цифровых карт, игровых ключей и подписок с быстрой выдачей кодов и понятным статусом заказа.</p>
        </div>

        <div class="footer-links-block">
            <span class="footer-title">Маркетплейс</span>
            <a href="{{ route('home') }}">Главная</a>
            <a href="{{ route('meanly.catalog.index') }}">Каталог</a>
            <a href="{{ route('home') }}#infrastructure">Как работает</a>
            <a href="{{ route('business.landing') }}">Стать продавцом</a>
        </div>

        <div class="footer-links-block">
            <span class="footer-title">Аккаунт</span>
            @auth
                <a href="/cabinet">Личный кабинет</a>
                @if(auth()->user()->hasRole('b2b_partner'))
                    <a href="/partner">B2B Консоль</a>
                @endif
            @else
                <a href="{{ route('login') }}">Войти по Passkey</a>
                <a href="{{ route('business.register') }}">Стать продавцом</a>
            @endauth
        </div>

        <div class="footer-links-block">
            <span class="footer-title">Для продавцов</span>
            <a href="{{ route('business.services.index') }}">Услуги для бизнеса</a>
            <a href="{{ route('business.register') }}">Стать продавцом</a>
            <a href="/partner">B2B Консоль</a>
        </div>
    </div>

    <div class="footer-bottom">
        <span>© {{ now()->year }} Meanly Systems</span>
        <span>Digital assets storefront · Fast checkout · Seller support</span>
    </div>
</footer>
