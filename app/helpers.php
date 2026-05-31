<?php

use App\Support\MarketContext;

if (! function_exists('market')) {
    function market(): MarketContext
    {
        return app(MarketContext::class);
    }
}
