<?php

use App\Support\MarketContext;
use App\Support\PricingContext;

if (! function_exists('market')) {
    function market(): MarketContext
    {
        return app(MarketContext::class);
    }
}

if (! function_exists('pricing')) {
    function pricing(): PricingContext
    {
        return app(PricingContext::class);
    }
}
