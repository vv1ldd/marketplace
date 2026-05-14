<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Provider;
use App\Models\WildflowCatalog;
use App\Models\WildflowSkuAlias;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplyShortWildflowProductSkusCommand extends Command
{
    protected $signature = 'wildflow:apply-short-product-skus
                            {--dry-run : Только показать изменения без записи в БД}
                            {--shop= : Ограничить товары, привязанные к магазину (shop id)}';

    protected $description = 'Перевести товары Wildflow на читаемый WF-{бренд}-{номинал}-{валюта}-{регион}-c{id} (и старый WF-{id}), с алиасом для заказов и Маркета';

    public function handle(): int
    {
        $providerId = Provider::query()->where('type', 'wildflow')->value('id');
        if (! $providerId) {
            $this->error('Провайдер type=wildflow не найден.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $shopId = $this->option('shop');

        $query = Product::query()
            ->where('provider_id', (int) $providerId)
            ->where(function ($q) {
                $q->where('sku', 'not like', 'WF-%')
                    ->orWhere(function ($q2) {
                        $q2->where('sku', 'like', 'WF-%')
                            ->whereRaw('(LENGTH(sku) - LENGTH(REPLACE(sku, ?, ?))) = ?', ['-', '', 1]);
                    });
            });

        if ($shopId !== null && $shopId !== '') {
            $query->whereHas('shops', fn ($q) => $q->where('shops.id', (int) $shopId));
        }

        $total = (clone $query)->count();
        $this->info("Кандидатов (длинный sku или короткий WF-{{id}}): {$total}");
        if ($total === 0) {
            return self::SUCCESS;
        }

        $migrated = 0;
        $skipped = 0;
        $orphanCount = 0;
        $orphanSamples = [];

        $query->orderBy('id')->chunkById(200, function ($products) use ($dryRun, &$migrated, &$skipped, &$orphanCount, &$orphanSamples): void {
            foreach ($products as $product) {
                $oldSku = trim((string) $product->sku);
                if ($oldSku === '') {
                    $skipped++;

                    continue;
                }
                if (str_starts_with($oldSku, 'WF-') && ! preg_match('/^WF-\d+$/', $oldSku)) {
                    $skipped++;

                    continue;
                }

                $canonical = trim((string) ($product->wildflow_catalog_sku ?? ''));
                $wf = null;
                if ($canonical !== '') {
                    $wf = WildflowCatalog::query()->where('sku', $canonical)->first();
                }
                if (! $wf) {
                    $wf = WildflowCatalog::query()->where('sku', $oldSku)->first();
                    if ($wf) {
                        $canonical = $wf->sku;
                    }
                }

                if (! $wf || $canonical === '') {
                    $orphanCount++;
                    if (count($orphanSamples) < 5) {
                        $orphanSamples[] = "#{$product->id}: {$oldSku}";
                    }

                    continue;
                }

                $newSku = $wf->suggestYmOfferSku();
                while (
                    Product::query()->where('sku', $newSku)->where('id', '!=', $product->id)->exists()
                ) {
                    $newSku = $wf->suggestYmOfferSku().'-'.Str::upper(Str::random(4));
                }

                if ($oldSku === $newSku) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("[dry-run] #{$product->id} «{$oldSku}» → «{$newSku}» (канон каталога: {$canonical})");
                    $migrated++;

                    continue;
                }

                DB::transaction(function () use ($product, $oldSku, $newSku, $canonical): void {
                    WildflowSkuAlias::query()->updateOrInsert(
                        ['alias_sku' => $oldSku],
                        [
                            'wildflow_catalog_sku' => $canonical,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );

                    $product->wildflow_catalog_sku = $canonical;
                    $product->sku = $newSku;
                    $product->save();

                    WildflowSkuAlias::syncForProduct($product);
                });

                $this->line("OK #{$product->id} «{$oldSku}» → «{$newSku}»");
                $migrated++;
            }
        });

        $this->newLine();
        $this->info($dryRun ? 'Dry-run: строк выведено: '.$migrated : 'Обновлено товаров: '.$migrated);
        if ($skipped > 0) {
            $this->comment('Прочие пропуски (пустой sku и т.п.): '.$skipped);
        }
        if ($orphanCount > 0) {
            $this->warn("Без строки wildflow_catalogs (sku не сопоставить): {$orphanCount}. Примеры: ".implode('; ', $orphanSamples).($orphanCount > 5 ? ' …' : ''));
        }
        if (! $dryRun && $migrated > 0) {
            $this->comment('На Яндекс.Маркете при необходимости переотправьте офферы (offer_id = sku). Картинки в public/img/card привязаны к sku — при смене путей может понадобиться пересбор карточек.');
        }

        return self::SUCCESS;
    }
}
