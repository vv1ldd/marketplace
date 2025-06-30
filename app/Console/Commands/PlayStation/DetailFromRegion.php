<?php

namespace App\Console\Commands\PlayStation;

use App\Http\Controllers\PlayStation\MainController as PsMainController;
use App\Http\Controllers\Ym\MainController as YmMainController;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PlaystationStoreApi\Exception\ResponseException;

class DetailFromRegion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ps:detail-from-region {region_id=063101db-9ac0-4e48-a948-29fe7e3f8dec}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Обновить данные по региону';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('memory_limit', '256M');

        $region_id = $this->argument('region_id');

        $this->info("Передан регион $region_id");

        $log = \Log::channel('play_stations_observer')->withContext([
            'log_id' => Str::random(),
            'region_id' => $region_id,
        ]);

        $request = new Request();

        $request->merge(['id' => $region_id]);

        $controller = new PsMainController();

        $this->info("Создаем задание в очереди на получение данных для региона $region_id");

        try {
            $result = $controller->detailFromRegion($request);
        } catch (ResponseException $e) {
            $log->error("detailFromRegion", ['exception' => $e->getMessage()]);
            $this->error("detailFromRegion ОШИБКА: " . $e->getMessage());
            return 1;
        }

        $result = $result->getData(true);

        $log->debug("detailFromRegion result", ['result' => $result]);

        $this->info("detailFromRegion успешно создано заданий в очереди {$result['queued']}");

        $this->info("Создаем задания на обновления цен");

        $request = new Request();

        $request->merge([
            'price_region_id' => $region_id,
            'lang_region_id' => '0f63f19f-fb73-4e9f-8f77-5a51d0d70009',
        ]);

        $controller = new YmMainController();

        try {
            $result = $controller->prepareToUpdatePriceItems($request);
        } catch (ConnectionException $e) {
            $log->error("prepareToUpdatePriceItems", ['exception' => $e->getMessage()]);
            $this->error("prepareToUpdatePriceItems ОШИБКА: " . $e->getMessage());
            return 1;
        }

        $result = $result->getData(true);

        $log->debug("prepareToUpdatePriceItems result", ['result' => $result]);

        $this->info("prepareToUpdatePriceItems успешно создано заданий в очереди {$result['queued']}");


        return 0;
    }
}
