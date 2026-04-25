<?php

namespace App\Console\Commands;

use App\Http\Controllers\Ym\MainController as YmMainController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class WildflowToMarket extends Command
{
    protected $signature = 'app:wildflow-to-market';

    protected $description = 'Send Wildflow catalog prices and details to Yandex Market';

    public function handle()
    {
        $this->info("Starting Wildflow sync to Yandex Market...");

        $controller = new YmMainController();
        
        // Mocking Request for the controller method
        $request = new Request();
        
        try {
            $response = $controller->sendItemsWildflow($request);
            $data = $response->getData(true);

            if ($data['success']) {
                $this->info("Successfully sent " . ($data['total'] ?? 0) . " items to Yandex Market.");
                $this->info("Time spent: " . ($data['seconds_spent'] ?? 0) . "s");
            } else {
                $this->error("Failed to send some items. Error bag count: " . count($data['error_bag'] ?? []));
                foreach ($data['error_bag'] ?? [] as $error) {
                    $this->error("- " . json_encode($error));
                }
            }
        } catch (\Exception $e) {
            $this->error("An error occurred during sync: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
