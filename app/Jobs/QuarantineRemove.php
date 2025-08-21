<?php

namespace App\Jobs;

use App\Http\Services\YmService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QuarantineRemove implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

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
        $ym_service->quarantineRemove($this->chunk);
    }
}
