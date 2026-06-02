<?php

use App\Http\Controllers\CodeController;
use App\Http\Middleware\AllowIframeForRoute;
use App\Http\Middleware\ApplyRedeemThemeFromQuery;
use App\Http\Controllers\TelemetryController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/reset-opcache', function() {
        if (function_exists('opcache_reset')) {
            opcache_reset();
            return 'OPcache reset success';
        }
        return 'OPcache not active';
    });

    Route::get('passkeys/authentication-options', fn () => response()->json([
            'error' => 'Local passkey login was retired. Use Simple Layer Identity instead.',
        ], 410))->name('passkeys.authentication_options');
    Route::post('passkeys/authenticate', fn () => response()->json([
            'error' => 'Local passkey login was retired. Use Simple Layer Identity instead.',
        ], 410))->name('passkeys.login');
});

$meanlyPublicRoutes = function () {
    // 🎟️ Staff Invitation Accept Flow
    Route::get('/invite/{token}', [\App\Http\Controllers\Auth\InviteAcceptController::class, 'show'])->name('invite.accept');
    Route::post('/invite/{token}/options', [\App\Http\Controllers\Auth\InviteAcceptController::class, 'options'])->name('invite.accept.options');
    Route::post('/invite/{token}/accept', [\App\Http\Controllers\Auth\InviteAcceptController::class, 'accept'])->name('invite.accept.submit');

    Route::get('/', [\App\Http\Controllers\MeanlyStorefrontController::class, 'index'])->name('home');
    Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots.txt');
    Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap.index');
    Route::get('/sitemap-products.xml', [\App\Http\Controllers\SitemapController::class, 'products'])->name('sitemap.products');
    Route::get('/sitemap-categories.xml', [\App\Http\Controllers\SitemapController::class, 'categories'])->name('sitemap.categories');
    Route::get('/sitemap-brands.xml', [\App\Http\Controllers\SitemapController::class, 'brands'])->name('sitemap.brands');
    Route::get('/sitemap-regions.xml', [\App\Http\Controllers\SitemapController::class, 'regions'])->name('sitemap.regions');
    Route::get('/sitemap-brand-regions.xml', [\App\Http\Controllers\SitemapController::class, 'brandRegions'])->name('sitemap.brand-regions');
    Route::get('/sitemap-services.xml', [\App\Http\Controllers\SitemapController::class, 'services'])->name('sitemap.services');
    Route::get('/sitemap-network.xml', [\App\Http\Controllers\SitemapController::class, 'network'])->name('sitemap.network');
    Route::get('/sitemap-llms.xml', [\App\Http\Controllers\SitemapController::class, 'llms'])->name('sitemap.llms');
    Route::post('/analytics/events', [\App\Http\Controllers\AnalyticsEventController::class, 'store'])->name('meanly.analytics.events.store');
    Route::get('/llms.txt', [\App\Http\Controllers\LlmCatalogController::class, 'llmsTxt'])->name('llms.txt');
    Route::get('/llms/catalog.json', [\App\Http\Controllers\LlmCatalogController::class, 'catalog'])->name('llms.catalog.index');
    Route::match(['get', 'post'], '/llms/catalog/understand', \App\Http\Controllers\CatalogQueryUnderstandingController::class)
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('llms.catalog.understand');
    Route::match(['get', 'post'], '/llms/catalog/retrieve', \App\Http\Controllers\CatalogRetrievalController::class)
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class])
        ->name('llms.catalog.retrieve');
    Route::get('/llms/commerce/opportunities', [\App\Http\Controllers\OpportunityGraphController::class, 'opportunities'])->name('llms.commerce.opportunities');
    Route::get('/llms/commerce/entities/{type}/{slug}', [\App\Http\Controllers\OpportunityGraphController::class, 'entity'])->name('llms.commerce.entities.show');
    Route::get('/llms/commerce/actions/effectiveness', [\App\Http\Controllers\OpportunityGraphController::class, 'actionEffectiveness'])->name('llms.commerce.actions.effectiveness');
    Route::get('/llms/intents', [\App\Http\Controllers\IntentLiquidityGraphController::class, 'index'])->name('llms.intents.index');
    Route::get('/llms/intents/{intentKey}', [\App\Http\Controllers\IntentLiquidityGraphController::class, 'show'])
        ->where('intentKey', '.+')
        ->name('llms.intents.show');
    Route::get('/llms/categories/{category}.json', [\App\Http\Controllers\LlmCatalogController::class, 'category'])->name('llms.categories.show');
    Route::get('/llms/products/{slug}.json', [\App\Http\Controllers\LlmCatalogController::class, 'product'])->name('llms.products.show');
    Route::get('/llms/services.json', [\App\Http\Controllers\LlmCatalogController::class, 'services'])->name('llms.services.index');
    Route::get('/llms/services/{slug}.json', [\App\Http\Controllers\LlmCatalogController::class, 'service'])->name('llms.services.show');
    Route::get('/llms/network/categories/{category}.json', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'categoryJson'])->name('llms.network.categories.show');
    Route::get('/llms/network/categories/{category}/identities.json', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'categoryIdentitiesJson'])->name('llms.network.categories.identities');
    Route::get('/llms/network/products/{idSlug}/intents/{intent}.json', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'productIntentJson'])->name('llms.network.products.intents.show');
    Route::get('/llms/network/products/{idSlug}/offers.json', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'productOffersJson'])->name('llms.network.products.offers');
    Route::get('/llms/network/products/{idSlug}.json', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'productJson'])->name('llms.network.products.show');
    Route::get('/llms/catalog/products/{identitySlug}/intents/{intent}.json', [\App\Http\Controllers\CanonicalProductPageController::class, 'productIntentJson'])->name('llms.catalog.canonical-products.intents.show');
    Route::get('/llms/catalog/products/{identitySlug}.json', [\App\Http\Controllers\CanonicalProductPageController::class, 'productJson'])->name('llms.catalog.canonical-products.show');
    Route::get('/store', [\App\Http\Controllers\MeanlyStorefrontController::class, 'index'])->name('meanly.storefront.index');
    Route::get('/store/suggest', [\App\Http\Controllers\MeanlyStorefrontController::class, 'suggest'])->name('meanly.storefront.suggest');
    Route::get('/store/search', [\App\Http\Controllers\MeanlyStorefrontController::class, 'search'])->name('meanly.storefront.search');
    Route::get('/store/products/{slug}', [\App\Http\Controllers\MeanlyStorefrontController::class, 'show'])->name('meanly.storefront.products.show');
    Route::get('/simple-l1/connect', [\App\Http\Controllers\SimpleL1ConnectController::class, 'connect'])->name('meanly.simple_l1.connect');
    Route::get('/simple-l1/callback', [\App\Http\Controllers\SimpleL1ConnectController::class, 'callback'])->name('meanly.simple_l1.callback');
    Route::post('/simple-l1/callback', [\App\Http\Controllers\SimpleL1ConnectController::class, 'callback'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    Route::get('/simple-l1/complete', [\App\Http\Controllers\SimpleL1ConnectController::class, 'complete'])->name('meanly.simple_l1.complete');
    Route::get('/simple-l1/status', [\App\Http\Controllers\SimpleL1ConnectController::class, 'status'])->name('meanly.simple_l1.status');
    Route::get('/csrf-token', fn () => response()->json(['csrf_token' => csrf_token()]))->name('csrf.token');
    Route::post('/store/favorites/{product}/toggle', [\App\Http\Controllers\MeanlyStorefrontController::class, 'toggleFavorite'])->name('meanly.storefront.favorites.toggle');
    Route::post('/store/checkout/availability', [\App\Http\Controllers\MeanlyStorefrontController::class, 'checkoutAvailability'])->name('meanly.storefront.checkout.availability');
    Route::post('/store/checkout', [\App\Http\Controllers\MeanlyStorefrontController::class, 'checkout'])->name('meanly.storefront.checkout');
    Route::post('/store/checkout/wallet/options', [\App\Http\Controllers\MeanlyStorefrontController::class, 'walletOptions'])->middleware('auth')->name('meanly.storefront.checkout.wallet.options');
    Route::post('/store/checkout/wallet/confirm', [\App\Http\Controllers\MeanlyStorefrontController::class, 'walletConfirm'])->middleware('auth')->name('meanly.storefront.checkout.wallet.confirm');
    Route::get('/store/orders/{order:uuid}/safe', [\App\Http\Controllers\MeanlyStorefrontController::class, 'orderSafe'])->name('meanly.storefront.orders.safe.show');
    Route::get('/store/orders/{order:uuid}/safe/status', [\App\Http\Controllers\MeanlyStorefrontController::class, 'orderSafeStatusJson'])->name('meanly.storefront.orders.safe.status');
    Route::post('/store/orders/{order:uuid}/safe/open', [\App\Http\Controllers\MeanlyStorefrontController::class, 'openOrderSafe'])->name('meanly.storefront.orders.safe.open');
    Route::post('/store/orders/{order:uuid}/safe/scratch', [\App\Http\Controllers\MeanlyStorefrontController::class, 'recordOrderScratch'])->name('meanly.storefront.orders.safe.scratch');
    Route::get('/store/orders/{order:uuid}/safe/support-ticket', [\App\Http\Controllers\MeanlyStorefrontController::class, 'orderSafeSupportTicket'])->name('meanly.storefront.orders.safe.support-ticket');
    Route::get('/store/orders/{order:uuid}/safe/support-ticket/messages', [\App\Http\Controllers\MeanlyStorefrontController::class, 'orderSafeSupportTicketMessages'])->name('meanly.storefront.orders.safe.support-ticket.messages');
    Route::post('/store/orders/{order:uuid}/safe/support-ticket/reply', [\App\Http\Controllers\MeanlyStorefrontController::class, 'replyOrderSafeSupportTicket'])->name('meanly.storefront.orders.safe.support-ticket.reply');
    Route::get('/meanly-ai', [\App\Http\Controllers\StorefrontChatController::class, 'page'])->name('storefront.ai-chat');
    Route::post('/storefront/chat', [\App\Http\Controllers\StorefrontChatController::class, 'chat'])->name('storefront.chat');
    Route::get('/catalog', [\App\Http\Controllers\MeanlyCatalogCategoryController::class, 'index'])->name('meanly.catalog.index');
    Route::get('/catalog/tags/{slug}', [\App\Http\Controllers\MeanlyCatalogCategoryController::class, 'collection'])->name('meanly.catalog.collections.show');
    Route::get('/catalog/brands/{brandSlug}/regions/{regionSlug}', [\App\Http\Controllers\MeanlyCatalogCategoryController::class, 'brandRegion'])->name('meanly.catalog.brand-regions.show');
    Route::get('/catalog/brands/{brandSlug}', [\App\Http\Controllers\MeanlyCatalogCategoryController::class, 'brand'])->name('meanly.catalog.brands.show');
    Route::get('/catalog/regions/{regionSlug}', [\App\Http\Controllers\MeanlyCatalogCategoryController::class, 'region'])->name('meanly.catalog.regions.show');
    Route::get('/catalog/groups/{category}/{brandSlug}/{kindSlug}', [\App\Http\Controllers\MeanlyCatalogCategoryController::class, 'group'])->name('meanly.catalog.groups.show');
    Route::get('/catalog/products/{identitySlug}', [\App\Http\Controllers\CanonicalProductPageController::class, 'show'])->name('meanly.canonical-products.show');
    Route::get('/catalog/{category}', [\App\Http\Controllers\MeanlyCatalogCategoryController::class, 'show'])->name('meanly.catalog.categories.show');
    Route::get('/catalog-network', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'index'])->name('meanly.network.index');
    Route::get('/catalog-network/products/{idSlug}', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'show'])->name('meanly.network.products.show');
    Route::get('/catalog-network/{category}', [\App\Http\Controllers\ProviderNetworkCatalogController::class, 'category'])->name('meanly.network.categories.show');
    Route::get('/services', fn (\Illuminate\Http\Request $request) => redirect()->route('business.services.index', $request->query(), 301))->name('meanly.services.index');
    Route::get('/services/{slug}', fn (string $slug, \Illuminate\Http\Request $request) => redirect()->route('business.services.show', ['slug' => $slug] + $request->query(), 301))->name('meanly.services.show');
    Route::get('/products/{slug}', [\App\Http\Controllers\ProductController::class, 'show'])->name('products.show');
    Route::get('/products-search', [\App\Http\Controllers\ProductController::class, 'search'])->name('products.search');
    Route::get('/login', fn () => view('auth.login'))->name('login');
    Route::post('/logout', function () {
        \Illuminate\Support\Facades\Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    })->middleware('auth')->name('logout');
    Route::post('/cabinet/logout', function () {
        \Illuminate\Support\Facades\Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    })->middleware('auth')->name('cabinet.logout');
    Route::get('/register', fn () => redirect()->route('meanly.simple_l1.connect', ['return_to' => '/vault', 'mode' => 'register']))->name('register');
    Route::get('/register/verify-intent', [\App\Http\Controllers\PartnerRegistrationController::class, 'verifyIntent'])->name('register.verify');
    Route::get('/business', fn () => view('business', [
        'serviceFacts' => app(\App\Services\LlmServiceFactsService::class)->services(),
        'serviceJsonLd' => app(\App\Services\LlmServiceFactsService::class)->serviceListJsonLd(),
    ]))->name('business.landing');
    Route::get('/business/services', [\App\Http\Controllers\MeanlyServiceController::class, 'index'])->name('business.services.index');
    Route::get('/business/services/{slug}', [\App\Http\Controllers\MeanlyServiceController::class, 'show'])->name('business.services.show');
    Route::get('/partner-landing', fn () => redirect()->route('business.landing'))->name('partner.landing');
    Route::get('/business/register', [\App\Http\Controllers\PartnerRegistrationController::class, 'showLegalEntity'])->name('business.register');
    Route::post('/business/register', [\App\Http\Controllers\PartnerRegistrationController::class, 'register'])->name('business.register.submit');
    Route::post('/business/register/options', [\App\Http\Controllers\PartnerRegistrationController::class, 'options'])->name('business.register.options');
    Route::post('/business/register/email/send', [\App\Http\Controllers\PartnerRegistrationController::class, 'sendBusinessEmailCode'])->name('business.register.email.send');
    Route::post('/business/register/email/verify', [\App\Http\Controllers\PartnerRegistrationController::class, 'verifyBusinessEmailCode'])->name('business.register.email.verify');
    Route::get('/legal-entities/register', fn (\Illuminate\Http\Request $request) => redirect()->route('business.register', $request->query()))->name('legal-entities.register');
    Route::post('/legal-entities/register', fn (\Illuminate\Http\Request $request) => redirect()->route('business.register', $request->query()))->name('legal-entities.register.submit');
    Route::get('/cabinet', function (\Illuminate\Http\Request $request) {
        $query = $request->getQueryString();

        return redirect('/vault'.($query ? '?'.$query : ''));
    });
    Route::get('/vault', [\App\Http\Controllers\CabinetController::class, 'index'])->name('cabinet.dashboard')->middleware(['auth']);
    Route::get('/cabinet/register', fn () => redirect()->route('meanly.simple_l1.connect', [
        'return_to' => route('cabinet.dashboard', [], false),
        'mode' => 'register',
    ]));
    Route::get('/vault/register', fn () => redirect()->route('meanly.simple_l1.connect', [
        'return_to' => route('cabinet.dashboard', [], false),
        'mode' => 'register',
    ]))->name('cabinet.register');
    Route::redirect('/cabinet/orders', '/vault')->name('cabinet.orders');
    Route::redirect('/cabinet/orders/{record}', '/vault')->name('cabinet.orders.show');
    Route::redirect('/cabinet/profile', '/vault')->name('cabinet.profile');
    Route::redirect('/cabinet/integrations', '/vault')->name('cabinet.integrations');
    Route::get('/vault/passkey-options', [\App\Http\Controllers\CabinetController::class, 'vaultPasskeyOptions'])->name('cabinet.vault.passkey.options')->middleware(['auth']);
    Route::post('/vault/passkey-confirm', [\App\Http\Controllers\CabinetController::class, 'vaultPasskeyConfirm'])->name('cabinet.vault.passkey.confirm')->middleware(['auth']);
    Route::post('/vault/lock', [\App\Http\Controllers\CabinetController::class, 'vaultLock'])->name('cabinet.vault.lock')->middleware(['auth']);
    Route::get('/cabinet/vault/passkey-options', [\App\Http\Controllers\CabinetController::class, 'vaultPasskeyOptions'])->middleware(['auth']);
    Route::post('/cabinet/vault/passkey-confirm', [\App\Http\Controllers\CabinetController::class, 'vaultPasskeyConfirm'])->middleware(['auth']);
    Route::post('/cabinet/vault/lock', [\App\Http\Controllers\CabinetController::class, 'vaultLock'])->middleware(['auth']);
    Route::redirect('/operator', '/ops')->name('partner.operator');
    Route::get('/reader', fn () => view('reader'))->name('reader');
    Route::get('/terminal', fn () => view('terminal'))->name('terminal');
    Route::redirect('/partner-old', '/partner')->name('partner.legacy');
    Route::redirect('/partner-old/{path}', '/partner')->where('path', '.*')->name('partner.legacy.deep');
    
    Route::prefix('partner')->group(function () {
        Route::get('/onboarding', [\App\Http\Controllers\PartnerRegistrationController::class, 'showOnboarding'])
            ->middleware('auth')
            ->name('partner.onboarding');

        // 🔐 Protected Dashboard
        Route::middleware(['auth', 'plane.guard', 'partner.intent'])->group(function () {
            Route::get('/', [\App\Http\Controllers\PartnerDashboardController::class, 'index'])->name('partner.dashboard');
            Route::post('/dashboard/sign', [\App\Http\Controllers\PartnerDashboardController::class, 'signAgreement'])->name('partner.dashboard.sign');
            Route::post('/dashboard/bank', [\App\Http\Controllers\PartnerDashboardController::class, 'updateBank'])->name('partner.dashboard.bank');
            Route::post('/dashboard/sandbox', [\App\Http\Controllers\PartnerDashboardController::class, 'createSandboxOrder'])->name('partner.dashboard.sandbox');
            Route::post('/dashboard/deposit-intent', [\App\Http\Controllers\PartnerDashboardController::class, 'createDepositIntent'])->name('partner.dashboard.deposit_intent');
            Route::post('/dashboard/clear-deposit-intent', [\App\Http\Controllers\PartnerDashboardController::class, 'clearDepositIntent'])->name('partner.dashboard.clear_deposit_intent');
            Route::post('/dashboard/invite-intent', [\App\Http\Controllers\PartnerDashboardController::class, 'createInviteIntent'])->name('partner.dashboard.invite_intent');
            Route::post('/dashboard/profile-update', function (\Illuminate\Http\Request $request) {
                $user = Auth::user();
                if (!$user) return response()->json(['error' => 'Unauthorized'], 401);
                
                $request->validate([
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'nullable|string|max:255',
                    'middle_name' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:255',
                ]);
                
                $user->update([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'middle_name' => $request->middle_name,
                    'phone' => $request->phone,
                ]);
                
                $seller = $user->primarySellerAccount();
                if ($seller) {
                    $seller->update([
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'middle_name' => $request->middle_name,
                        'phone' => $request->phone,
                    ]);
                }
                
                return response()->json(['success' => true, 'message' => 'Профиль успешно обновлен!']);
            })->name('partner.dashboard.profile_update');
            
            // 🌐 API Integrations / Applications CRUD Management
            Route::post('/dashboard/api-app/store', [\App\Http\Controllers\PartnerDashboardController::class, 'storeApiApp'])->name('partner.dashboard.api_app.store');
            Route::post('/dashboard/api-app/{id}/toggle', [\App\Http\Controllers\PartnerDashboardController::class, 'toggleApiApp'])->name('partner.dashboard.api_app.toggle');
            Route::post('/dashboard/api-app/{id}/delete', [\App\Http\Controllers\PartnerDashboardController::class, 'deleteApiApp'])->name('partner.dashboard.api_app.delete');

            // 🏪 B2B Shop Creation
            Route::post('/dashboard/shop/create', [\App\Http\Controllers\PartnerDashboardController::class, 'createShop'])->name('partner.dashboard.shop.create');

            // 🟡 Yandex Market Integration Management
            Route::post('/dashboard/shop/{id}/yandex-market', [\App\Http\Controllers\PartnerDashboardController::class, 'updateYandexMarket'])->name('partner.dashboard.shop.yandex_market');
            Route::post('/dashboard/shop/{id}/yandex-market/warehouses', [\App\Http\Controllers\PartnerDashboardController::class, 'getYandexMarketWarehouses'])->name('partner.dashboard.shop.yandex_market.warehouses');
            Route::post('/dashboard/shop/{id}/yandex-market/verify-legal', [\App\Http\Controllers\PartnerDashboardController::class, 'verifyYandexMarketLegalEntity'])->name('partner.dashboard.shop.yandex_market.verify_legal');

            // 🔔 Notifications Center Management
            Route::get('/dashboard/notifications', [\App\Http\Controllers\PartnerDashboardController::class, 'getNotifications'])->name('partner.dashboard.notifications');
            Route::post('/dashboard/notifications/read-all', [\App\Http\Controllers\PartnerDashboardController::class, 'readAllNotifications'])->name('partner.dashboard.notifications.read_all');
            Route::post('/dashboard/notifications/{id}/read', [\App\Http\Controllers\PartnerDashboardController::class, 'readNotification'])->name('partner.dashboard.notifications.read');

            // 🛒 B2B Provider Showcase Management
            Route::get('/dashboard/provider-catalog', [\App\Http\Controllers\PartnerDashboardController::class, 'index'])->name('partner.dashboard.provider_catalog');
            Route::get('/dashboard/provider-catalog/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getProviderCatalogData'])->name('partner.dashboard.provider_catalog.data');
            Route::get('/dashboard/storefront/products', [\App\Http\Controllers\PartnerDashboardController::class, 'getStorefrontProducts'])->name('partner.dashboard.storefront.products');
            Route::post('/dashboard/storefront/check-availability', [\App\Http\Controllers\PartnerDashboardController::class, 'checkStorefrontAvailability'])->name('partner.dashboard.storefront.check_availability');
            Route::post('/dashboard/storefront/add-to-catalog', [\App\Http\Controllers\PartnerDashboardController::class, 'addStorefrontToCatalog'])->name('partner.dashboard.storefront.add_to_catalog');
            Route::post('/dashboard/storefront/buy-once', [\App\Http\Controllers\PartnerDashboardController::class, 'buyStorefrontOnce'])->name('partner.dashboard.storefront.buy_once');
            Route::post('/dashboard/storefront/buy-options', [\App\Http\Controllers\PartnerDashboardController::class, 'buyStorefrontOptions'])->name('partner.dashboard.storefront.buy_options');

            // 📦 B2B Orders SPA Management
            Route::get('/dashboard/orders/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getOrdersData'])->name('partner.dashboard.orders.data');
            Route::post('/dashboard/orders/sync', [\App\Http\Controllers\PartnerDashboardController::class, 'syncOrders'])->name('partner.dashboard.orders.sync');
            Route::post('/dashboard/orders/sandbox', [\App\Http\Controllers\PartnerDashboardController::class, 'createSandboxOrder'])->name('partner.dashboard.orders.sandbox');
            Route::get('/dashboard/orders/{id}/details', [\App\Http\Controllers\PartnerDashboardController::class, 'getOrderDetails'])->name('partner.dashboard.orders.details');

            // 🗂️ B2B Catalog SPA Management
            Route::get('/dashboard/catalog/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getCatalogData'])->name('partner.dashboard.catalog.data');
            Route::post('/dashboard/catalog/{id}/toggle', [\App\Http\Controllers\PartnerDashboardController::class, 'toggleProductStatus'])->name('partner.dashboard.catalog.toggle');

            // 🏪 B2B Shops SPA Management
            Route::get('/dashboard/shops/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getShopsData'])->name('partner.dashboard.shops.data');
            Route::post('/dashboard/shops/{id}/toggle-active', [\App\Http\Controllers\PartnerDashboardController::class, 'toggleShopActive'])->name('partner.dashboard.shops.toggle_active');
            Route::post('/dashboard/shops/{id}/toggle-sandbox', [\App\Http\Controllers\PartnerDashboardController::class, 'toggleShopSandbox'])->name('partner.dashboard.shops.toggle_sandbox');

            // 🎫 B2B Support Tickets SPA Management
            Route::get('/dashboard/tickets/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getTicketsData'])->name('partner.dashboard.tickets.data');
            Route::post('/dashboard/tickets/create', [\App\Http\Controllers\PartnerDashboardController::class, 'createTicket'])->name('partner.dashboard.tickets.create');
            Route::get('/dashboard/tickets/{id}/details', [\App\Http\Controllers\PartnerDashboardController::class, 'getTicketDetails'])->name('partner.dashboard.tickets.details');
            Route::post('/dashboard/tickets/{id}/reply', [\App\Http\Controllers\PartnerDashboardController::class, 'replyToTicket'])->name('partner.dashboard.tickets.reply');

            // Operator and AI audit workflows moved to /ops.
            Route::get('/dashboard/operator/data', fn () => response()->json(['error' => 'Operator workspace moved to /ops.'], 410))->name('partner.dashboard.operator.data');
            Route::post('/dashboard/ai/audit', fn () => response()->json(['error' => 'AI audit moved to /ops.'], 410))->name('partner.dashboard.ai.audit');
            Route::post('/dashboard/ai/chat', fn () => response()->json(['error' => 'AI chat moved to /ops.'], 410))->name('partner.dashboard.ai.chat');

            // 📦 B2B Warehouses SPA Management
            Route::get('/dashboard/warehouses/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getWarehousesData'])->name('partner.dashboard.warehouses.data');
            Route::get('/dashboard/warehouses/{id}/stock', [\App\Http\Controllers\PartnerDashboardController::class, 'getWarehouseStock'])->name('partner.dashboard.warehouses.stock');
            Route::post('/dashboard/warehouses/create', [\App\Http\Controllers\PartnerDashboardController::class, 'createWarehouse'])->name('partner.dashboard.warehouses.create');
            Route::post('/dashboard/warehouses/{id}/toggle-active', [\App\Http\Controllers\PartnerDashboardController::class, 'toggleWarehouseActive'])->name('partner.dashboard.warehouses.toggle_active');

            // 🚀 B2B Activations SPA Management
            Route::get('/dashboard/activations/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getActivationsData'])->name('partner.dashboard.activations.data');
            Route::post('/dashboard/activations/create', [\App\Http\Controllers\PartnerDashboardController::class, 'createActivation'])->name('partner.dashboard.activations.create');
            Route::get('/dashboard/shops/{shopId}/options', [\App\Http\Controllers\PartnerDashboardController::class, 'getShopOptions'])->name('partner.dashboard.shops.options');

            // 🎫 B2B Voucher Code Registry SPA Management
            Route::get('/dashboard/vouchers/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getVouchersData'])->name('partner.dashboard.vouchers.data');
            Route::get('/dashboard/vouchers/{id}/details', [\App\Http\Controllers\PartnerDashboardController::class, 'getVoucherDetails'])->name('partner.dashboard.vouchers.details');

            // 💰 B2B Finance & Billing SPA Management
            Route::get('/dashboard/finance/data', [\App\Http\Controllers\PartnerDashboardController::class, 'getFinanceData'])->name('partner.dashboard.finance.data');
            Route::get('/dashboard/simple-layer-1/trace', [\App\Http\Controllers\PartnerDashboardController::class, 'traceSimpleLayer1'])->name('partner.dashboard.simple_layer_1.trace');
            Route::post('/dashboard/finance/deposit', [\App\Http\Controllers\PartnerDashboardController::class, 'simulateDeposit'])->name('partner.dashboard.finance.deposit');
            Route::post('/dashboard/finance/sovereign-request/options', [\App\Http\Controllers\PartnerDashboardController::class, 'sovereignBalanceRequestOptions'])->name('partner.dashboard.finance.sovereign_request.options');
            Route::post('/dashboard/finance/sovereign-request/create', [\App\Http\Controllers\PartnerDashboardController::class, 'createSovereignBalanceRequest'])->name('partner.dashboard.finance.sovereign_request.create');
            
            // 🚪 Safe Logout
            Route::post('/logout', function () {
                \Illuminate\Support\Facades\Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();
                return redirect()->route('home');
            })->name('partner.logout');
        });
        
        // 🚀 Public Registration Flow
        Route::get('/register', fn (\Illuminate\Http\Request $request) => redirect()->route('business.register', $request->query()))->name('partner.register');
        Route::post('/register', [\App\Http\Controllers\PartnerRegistrationController::class, 'register'])->name('partner.register.submit');
        Route::get('/register/enroll', [\App\Http\Controllers\PartnerRegistrationController::class, 'showEnroll'])->name('partner.register.enroll');
        Route::get('/register/offer', [\App\Http\Controllers\PartnerRegistrationController::class, 'showOffer'])->name('partner.register.offer');
        
        // 🔐 Sovereign Identity & Signing (Mixed/Session based)
        Route::post('/register/options', [\App\Http\Controllers\PartnerRegistrationController::class, 'options'])->name('partner.register.options');
        Route::post('/register/identity', [\App\Http\Controllers\PartnerRegistrationController::class, 'registerIdentity'])->name('partner.register.identity.store');
        Route::post('/register/sign', [\App\Http\Controllers\PartnerRegistrationController::class, 'signAgreement'])->name('partner.register.agreement.sign');
    });

    // 🛡️ Global Operations Command Center (/ops)
    Route::prefix('ops')->group(function () {
        Route::middleware(['auth'])->group(function () {
            Route::get('/', [\App\Http\Controllers\OpsDashboardController::class, 'index'])->name('ops.dashboard');
            
            // 📋 Global Ops AJAX endpoints for SPA tabs
            Route::get('/dashboard/partners/data', [\App\Http\Controllers\OpsDashboardController::class, 'getPartnersData'])->name('ops.dashboard.partners.data');
            Route::get('/dashboard/treasury/data', [\App\Http\Controllers\OpsDashboardController::class, 'getTreasuryData'])->name('ops.dashboard.treasury.data');
            Route::get('/dashboard/liquidity/data', [\App\Http\Controllers\OpsDashboardController::class, 'getLiquidityData'])->name('ops.dashboard.liquidity.data');
            Route::get('/dashboard/channels/data', [\App\Http\Controllers\OpsDashboardController::class, 'getChannelsData'])->name('ops.dashboard.channels.data');
            Route::get('/dashboard/growth/data', [\App\Http\Controllers\OpsDashboardController::class, 'getGrowthData'])->name('ops.dashboard.growth.data');
            Route::get('/dashboard/search-integrations/data', [\App\Http\Controllers\OpsDashboardController::class, 'getSearchIntegrationsData'])->name('ops.dashboard.search-integrations.data');
            Route::post('/dashboard/zero-layer/connect', [\App\Http\Controllers\OpsDashboardController::class, 'saveZeroLayerIntegration'])->name('ops.dashboard.zero-layer.connect');
            Route::post('/dashboard/zero-layer/{integration}/sync', [\App\Http\Controllers\OpsDashboardController::class, 'syncZeroLayerIntegration'])->name('ops.dashboard.zero-layer.sync');
            Route::post('/dashboard/search-signals/pull', [\App\Http\Controllers\OpsDashboardController::class, 'pullSearchSignals'])->name('ops.dashboard.search-signals.pull');
            Route::post('/dashboard/search-signals/analyze', [\App\Http\Controllers\OpsDashboardController::class, 'analyzeSearchSignals'])->name('ops.dashboard.search-signals.analyze');
            Route::post('/dashboard/search-signals/recommend', [\App\Http\Controllers\OpsDashboardController::class, 'recommendSearchSignals'])->name('ops.dashboard.search-signals.recommend');
            Route::post('/dashboard/search-signals/promote-zero-layer', [\App\Http\Controllers\OpsDashboardController::class, 'promoteZeroLayerSignals'])->name('ops.dashboard.search-signals.promote-zero-layer');
            Route::post('/dashboard/partners/{legalEntity}/approve', [\App\Http\Controllers\OpsDashboardController::class, 'approvePartner'])->name('ops.dashboard.partners.approve');
            Route::get('/dashboard/shops/data', [\App\Http\Controllers\OpsDashboardController::class, 'getShopsData'])->name('ops.dashboard.shops.data');
            Route::get('/dashboard/orders/data', [\App\Http\Controllers\OpsDashboardController::class, 'getOrdersData'])->name('ops.dashboard.orders.data');
            Route::get('/dashboard/operations/data', [\App\Http\Controllers\OpsDashboardController::class, 'getOperationsData'])->name('ops.dashboard.operations.data');
            Route::get('/dashboard/catalog/data', [\App\Http\Controllers\OpsDashboardController::class, 'getCatalogData'])->name('ops.dashboard.catalog.data');
            Route::get('/dashboard/inventory/data', [\App\Http\Controllers\OpsDashboardController::class, 'getInventoryData'])->name('ops.dashboard.inventory.data');
            Route::get('/dashboard/providers/data', [\App\Http\Controllers\OpsDashboardController::class, 'getProvidersData'])->name('ops.dashboard.providers.data');
            Route::post('/dashboard/providers/{provider}/sync', [\App\Http\Controllers\OpsDashboardController::class, 'syncProvider'])->name('ops.dashboard.providers.sync');
            Route::post('/dashboard/partners/{legalEntity}/top-up', [\App\Http\Controllers\OpsDashboardController::class, 'topUpPartnerBalance'])->name('ops.dashboard.partners.top-up');
            Route::post('/dashboard/providers/partners/{legalEntity}/grant-credit', [\App\Http\Controllers\OpsDashboardController::class, 'grantPartnerCredit'])->name('ops.dashboard.providers.partners.grant-credit');
            Route::post('/dashboard/providers/partners/{legalEntity}/top-up', [\App\Http\Controllers\OpsDashboardController::class, 'topUpPartnerBalance'])->name('ops.dashboard.providers.partners.top-up');
            Route::get('/dashboard/tickets/data', [\App\Http\Controllers\OpsDashboardController::class, 'getTicketsData'])->name('ops.dashboard.tickets.data');
            Route::get('/dashboard/tickets/{id}/details', [\App\Http\Controllers\OpsDashboardController::class, 'getTicketDetails'])->name('ops.dashboard.tickets.details');
            Route::post('/dashboard/tickets/{id}/reply', [\App\Http\Controllers\OpsDashboardController::class, 'replyToTicket'])->name('ops.dashboard.tickets.reply');
            
            // 🤖 Global Operations AI & Ledger Audit
            Route::post('/dashboard/ai/audit', [\App\Http\Controllers\OpsDashboardController::class, 'runAiAudit'])->name('ops.dashboard.ai.audit');
            Route::post('/dashboard/ai/chat', [\App\Http\Controllers\OpsDashboardController::class, 'sendAiChatMessage'])->name('ops.dashboard.ai.chat');
            Route::get('/dashboard/simple-layer-1/trace', [\App\Http\Controllers\OpsDashboardController::class, 'traceSimpleLayer1'])->name('ops.dashboard.simple_layer_1.trace');
            Route::post('/dashboard/tribunal/validate-chain', [\App\Http\Controllers\TribunalDashboardController::class, 'validateChain'])->name('ops.dashboard.tribunal.validate-chain');
            Route::post('/dashboard/tribunal/chat', [\App\Http\Controllers\TribunalDashboardController::class, 'chatOracle'])->name('ops.dashboard.tribunal.chat');
            Route::post('/dashboard/theme', [\App\Http\Controllers\OpsDashboardController::class, 'updateTheme'])->name('ops.dashboard.theme');

            Route::get('/decision-console', [\App\Http\Controllers\OpsDecisionConsoleController::class, 'index'])->name('ops.decision-console');
            Route::post('/decision-console/recommendations/{recommendation}/approve', [\App\Http\Controllers\OpsDecisionConsoleController::class, 'approve'])->name('ops.decision-console.recommendations.approve');
            Route::post('/decision-console/recommendations/{recommendation}/reject', [\App\Http\Controllers\OpsDecisionConsoleController::class, 'reject'])->name('ops.decision-console.recommendations.reject');
        });
    });
    Route::redirect('/ops/{path}', '/ops')->where('path', '.*')->name('ops.legacy.deep');

    Route::redirect('/tribunal', '/ops')->name('tribunal.legacy');
    Route::redirect('/treasury', '/ops')->name('treasury.legacy');
    Route::redirect('/kernel', '/ops')->name('kernel.legacy');
    Route::redirect('/support', '/ops')->name('support.legacy');

};

foreach (array_values(array_unique(array_filter(array_merge(
    config('app.public_domains', [config('app.domain')]),
    collect(config('markets.markets', []))
        ->flatMap(fn (array $market): array => (array) ($market['domains'] ?? []))
        ->all(),
)))) as $domain) {
    Route::domain($domain)->group($meanlyPublicRoutes);
}

Route::get('/lang/{locale}', function (string $locale) {
    $resolver = app(\App\Services\LocaleResolver::class);
    $locale = $resolver->normalize($locale);

    if ($locale) {
        session(['locale' => $locale]);

        if ($user = request()->user()) {
            $resolver->persistUserLocale($user, $locale);
        }
    }

    return redirect()->back()->withHeaders(['Vary' => 'Accept-Language']);
})->name('lang.switch');

Route::get('/theme/{theme}', function (string $theme) {
    $resolver = app(\App\Services\ThemeResolver::class);
    $theme = $resolver->normalize($theme);

    if ($theme) {
        session(['theme' => $theme]);

        if ($user = request()->user()) {
            $resolver->persistUserTheme($user, $theme);
        }
    }

    return redirect()->back();
})->name('theme.switch');

// Telemetry & Redirects (Telegram Sales Channel)
Route::get('/t/{id}', [TelemetryController::class, 'telegramClick'])->name('telegram.click');

$redeemEmailSubmitThrottle = app()->environment(['local', 'testing'])
    ? 'throttle:120,1'
    : 'throttle:25,1';
$redeemResendThrottle = app()->environment(['local', 'testing'])
    ? 'throttle:60,1'
    : 'throttle:15,1';

Route::group(['middleware' => [AllowIframeForRoute::class]], function () use ($redeemEmailSubmitThrottle, $redeemResendThrottle) {
    Route::group(['prefix' => 'redeem', 'middleware' => [ApplyRedeemThemeFromQuery::class]], function () use ($redeemEmailSubmitThrottle, $redeemResendThrottle) {

        Route::get('/', fn () => redirect()->route('redeem.code', request()->query()));
        Route::get('step1', fn () => redirect()->route('redeem.code', request()->query()));
        Route::get('step2', fn () => redirect()->route('redeem.email'));
        Route::get('step3', fn () => redirect()->route('redeem.activation'));

        Route::get('code', [CodeController::class, 'getCodeView'])->name('redeem.code');
        Route::post('code', [CodeController::class, 'checkCode'])->name('redeem.code.submit')->middleware('throttle:30,1');

        Route::get('email', [CodeController::class, 'getEmailView'])->name('redeem.email');
        Route::post('email', [CodeController::class, 'checkEmail'])->name('redeem.email.submit')->middleware($redeemEmailSubmitThrottle);

        Route::get('activation', [CodeController::class, 'getViewForm'])->name('redeem.activation');
        Route::post('activation', [CodeController::class, 'sendForm'])->name('redeem.activation.submit')->middleware('throttle:30,1');
        Route::post('resend', [CodeController::class, 'resendCode'])->name('redeem.resend')->middleware($redeemResendThrottle);

        Route::get('success', [CodeController::class, 'getFinishView'])->name('redeem.success');
        Route::get('success/status', [CodeController::class, 'redeemFinishStatus'])->name('redeem.finish-status')->middleware('throttle:120,1');
    });
});
