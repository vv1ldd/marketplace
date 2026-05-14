<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

class CleanupSecurityArtifacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-security-artifacts {--hours=24 : Retention period in hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up sensitive temporary files (exports, CSVs, etc.) to maintain data sovereignty.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $this->info("Cleaning up security artifacts older than {$hours} hours...");

        $directories = [
            'filament_exports', // Relative to 'local' disk root (app/private)
            'exports',          // Relative to 'local' disk root
        ];

        foreach ($directories as $dir) {
            $this->cleanupDirectory($dir, $hours);
        }

        $this->info('Cleanup completed.');
    }

    protected function cleanupDirectory(string $directory, int $hours)
    {
        if (!Storage::exists($directory)) {
            return;
        }

        $files = Storage::allFiles($directory);
        $count = 0;
        $now = Carbon::now();

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));
            
            if ($lastModified->diffInHours($now) >= $hours) {
                Storage::delete($file);
                $count++;
            }
        }

        if ($count > 0) {
            $this->comment("Deleted {$count} files from {$directory}.");
        }
    }
}
