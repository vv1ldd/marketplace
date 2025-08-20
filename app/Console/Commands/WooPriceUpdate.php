<?php

namespace App\Console\Commands;

use App\Http\Controllers\WooPriceUpdateController;
use Illuminate\Console\Command;

class WooPriceUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'woo-price-update';

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

        $controller = new WooPriceUpdateController();

        $res = $controller->update()->original;

        if ($res['error']) {
            $this->error($res['error']);

            return 1;
        }

        $this->info($res['message']);

        return 0;
    }
}
