<footer class="marketplace-footer">
    <div class="footer-grid">
        <div class="footer-brand-block">
            <a class="footer-logo" href="{{ route('home', [], false) }}"><span class="logo-mark"></span> MEANLY</a>
            <p>{{ __('storefront.footer.description') }}</p>
        </div>

        <div class="footer-links-block">
            <span class="footer-title">{{ __('storefront.footer.marketplace') }}</span>
            <a href="{{ route('home', [], false) }}">{{ __('storefront.footer.home') }}</a>
            <a href="{{ route('meanly.catalog.index', [], false) }}">{{ __('storefront.footer.catalog') }}</a>
            <a href="{{ route('home', [], false) }}#infrastructure">{{ __('storefront.footer.how_it_works') }}</a>
        </div>

        <div class="footer-links-block">
            <span class="footer-title">{{ __('storefront.footer.account') }}</span>
            @auth
                <a href="/vault">{{ __('storefront.footer.vault') }}</a>
                @if(auth()->user()->isMerchantNode())
                    <a href="/merchant">{{ __('storefront.footer.b2b_console') }}</a>
                @endif
            @else
                <a href="{{ route('login', [], false) }}">{{ __('storefront.footer.login_sl1e') }}</a>
            @endauth
        </div>

        <div class="footer-links-block">
            <span class="footer-title">{{ __('storefront.footer.business') }}</span>
            <a href="{{ route('business.services.index', [], false) }}">{{ __('storefront.footer.business_services') }}</a>
            <a href="{{ route('business.register', [], false) }}">{{ __('storefront.footer.become_seller') }}</a>
            <a href="{{ route('business.landing', [], false) }}">{{ __('storefront.footer.b2b_console') }}</a>
        </div>

        <div class="footer-links-block">
            <span class="footer-title">{{ __('storefront.footer.documents') }}</span>
            <a href="/company">{{ __('storefront.footer.company') }}</a>
            <a href="/payment">{{ __('storefront.footer.payment') }}</a>
            <a href="/delivery">{{ __('storefront.footer.delivery') }}</a>
            <a href="/refund">{{ __('storefront.footer.refund') }}</a>
            <a href="/offer">{{ __('storefront.footer.offer') }}</a>
            <a href="/privacy">{{ __('storefront.footer.privacy') }}</a>
            <a href="/terms">{{ __('storefront.footer.terms') }}</a>
        </div>
    </div>

    <div class="footer-bottom">
        <span>© {{ now()->year }} Meanly Systems</span>
        <span>{{ __('storefront.footer.bottom') }}</span>
    </div>
</footer>
