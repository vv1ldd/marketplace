<?php

namespace App\Console\Commands;

use App\Http\Services\GoogleTranslateService;
use App\Models\PlayStation\PlayStationAlt;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

class TranslateItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ps:translate-items';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $log = \Log::channel('translate_items')->withContext([
            'log_id' => \Str::random(),
        ]);

        $ru_region = '0f63f19f-fb73-4e9f-8f77-5a51d0d70009';
        $tr_region = '063101db-9ac0-4e48-a948-29fe7e3f8dec';

        $log->info('start en-ru translation');

        $skusToTranslate = \DB::table('play_station_alts as tr')
            ->leftJoin('play_station_alts as ru', function ($join) use ($ru_region) {
                $join->on('tr.sku', '=', 'ru.sku')
                    ->where('ru.region_id', '=', $ru_region);
            })
            ->where('tr.region_id', '=', $tr_region)
            ->whereNull('ru.id') // нет ru локализации
            ->select(
                'tr.*')
            ->where('tr.price_with_discount', '>', 0)
            ->whereNotNull('tr.data')
            ->limit(10)
            ->get();

        $log->info('found ' . count($skusToTranslate) . ' items to translate');

        if (empty($skusToTranslate)) {
            $log->info('nothing to translate');
            return;
        }

        foreach ($skusToTranslate as $item) {

            $data = json_decode($item->data, true);

            $description = '';
            $translate_desc = '';

            if (!empty($data['descriptions'])) {
                foreach ($data['descriptions'] as $desc) {
                    if ($desc['type'] === 'LONG' || $desc['type'] === 'COMPATIBILITY_NOTICE') {
                        $description .= $desc['value'];
                    }
                }
            }

            if (strlen($description)) {
                try {
                    $translate_desc = GoogleTranslateService::translate($description, 'en', 'ru');
                } catch (ConnectionException $e) {
                    $log->error($e->getMessage());
                    continue;
                }
            } else {
                continue;
            }

            if (!strlen($translate_desc)) {
                $log->info('translate_desc is empty');
                continue;
            }

            $data['descriptions'] = [
                'type' => 'LONG',
                'value' => $translate_desc
            ];

            $result = PlayStationAlt::create([
                'sku' => $item->sku,
                'concept_id' => $item->concept_id,
                'base_price' => $item->base_price,
                'price_with_discount' => $item->price_with_discount,
                'name' => $item->name,
                'region_id' => $ru_region,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $log->info('translated ' . $item->sku . ' to ru' . ' (' . $result->id . ')');
        }

        $log->info('done');

        return;
    }
}
