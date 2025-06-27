<?php

namespace App\Jobs;

use App\Http\Services\YmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateYmPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly array $chunk)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $ym_service = new YmService();
        $ym_service->offerPriceUpdate($this->chunk);
    }
}
