<?php

namespace App\Console\Commands;

use App\Services\MeanlyCatalogReconciliationService;
use App\Services\MeanlyFirstPartyStorefrontService;
use Illuminate\Console\Command;

class MeanlyBootstrapStorefrontCommand extends Command
{
    protected $signature = 'meanly:bootstrap-storefront {--reconcile : Run Yandex/internal reconciliation after bootstrap} {--create-missing : Create inactive local drafts for Yandex-only offers}';

    protected $description = 'Create or update the first-party Meanly seller/shop storefront identity.';

    public function handle(MeanlyFirstPartyStorefrontService $storefront, MeanlyCatalogReconciliationService $reconciliation): int
    {
        $entity = $storefront->legalEntity();
        $shop = $storefront->shop();

        $this->info("Meanly legal entity: #{$entity->id} {$entity->short_name}");
        $this->info("Meanly shop: #{$shop->id} {$shop->name} ({$shop->domain})");

        if ($this->option('reconcile')) {
            $summary = $reconciliation->reconcile($shop, createMissing: (bool) $this->option('create-missing'));
            $this->line('Reconciliation: '.json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return self::SUCCESS;
    }
}
