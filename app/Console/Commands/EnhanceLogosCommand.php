<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class EnhanceLogosCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:enhance-logos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enhance brand logos using Real-ESRGAN';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $brands = \App\Models\Brand::whereNotNull('logo_source')
            ->whereNull('logo_enhanced')
            ->get();

        $total = $brands->count();
        $success = 0;
        $failed = 0;
        $skipped = 0;

        $binary = base_path('bin/realesrgan-ncnn-vulkan');
        $models = base_path('bin/models');
        $enhancedDir = storage_path('app/public/media/logos/enhanced');

        if (!file_exists($enhancedDir)) {
            mkdir($enhancedDir, 0755, true);
        }

        $this->info("Starting Real-ESRGAN enhancement for $total brands...");

        foreach ($brands as $index => $brand) {
            $sourcePath = public_path($brand->logo_source);
            $slug = $brand->slug;

            if (!file_exists($sourcePath)) {
                $this->warn("[$index/$total] Skip $slug: File not found at $sourcePath");
                $skipped++;
                continue;
            }

            // Skip SVG files - they are already sharp
            if (str_ends_with(strtolower($sourcePath), '.svg')) {
                $this->line("[$index/$total] Skip $slug: SVG does not need enhancement");
                $brand->update(['logo_enhanced' => $brand->logo_source]); // Just use original
                $skipped++;
                continue;
            }

            $enhancedFilename = "{$slug}.png";
            $enhancedAbsPath = $enhancedDir . '/' . $enhancedFilename;
            $enhancedRelPath = "storage/media/logos/enhanced/{$enhancedFilename}";

            $this->output->write("[$index/$total] Enhancing $slug ... ");

            $cmd = "\"$binary\" -i " . escapeshellarg($sourcePath) . " -o " . escapeshellarg($enhancedAbsPath) . " -n realesrgan-x4plus -s 4 -f png -m " . escapeshellarg($models) . " 2>&1";
            
            exec($cmd, $output, $returnCode);

            if ($returnCode === 0 && file_exists($enhancedAbsPath)) {
                $brand->update([
                    'logo_enhanced' => $enhancedRelPath,
                    'logo_png' => $enhancedRelPath
                ]);
                $this->info("OK (2x)");
                $success++;
            } else {
                $this->error("FAILED");
                if (!empty($output)) {
                    $this->line("Error: " . implode("\n", $output));
                }
                $failed++;
            }
        }

        $this->info("\nDone!\nSuccess: $success\nFailed: $failed\nSkipped: $skipped");
    }
}
