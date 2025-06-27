<?php

namespace App\Jobs;

use App\Http\Services\YmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ItemsYmShow implements ShouldQueue
{
    use Queueable;

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
        $ym_service->offerShow($this->chunk);
    }
}
