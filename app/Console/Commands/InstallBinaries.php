<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class InstallBinaries extends Command
{
    protected $signature = 'app:install-binaries {--force : Overwrite existing binaries}';
    protected $description = 'Install Real-ESRGAN and VTracer using local ZIP archives';

    public function handle(): void
    {
        $os = PHP_OS_FAMILY;
        $binDir = base_path('bin');
        $realesrganBin = $binDir . '/realesrgan-ncnn-vulkan';

        if (!is_dir($binDir)) {
            mkdir($binDir, 0755, true);
        }

        $this->info("Checking for local ZIP archives...");

        // 1. Real-ESRGAN Binary (v0.2.0)
        $binZip = base_path($os === 'Darwin' ? 'realesrgan-ncnn-vulkan-v0.2.0-macos.zip' : 'realesrgan-ncnn-vulkan-v0.2.0-ubuntu.zip');
        
        if (file_exists($binZip)) {
            $this->info("Extracting binary from " . basename($binZip));
            $temp = $binDir . '/realesrgan_temp';
            if (!is_dir($temp)) mkdir($temp);
            shell_exec("unzip -o " . escapeshellarg($binZip) . " -d " . escapeshellarg($temp));
            $found = shell_exec("find {$temp} -name realesrgan-ncnn-vulkan -type f | head -n 1");
            if ($found) {
                copy(trim($found), $realesrganBin);
                chmod($realesrganBin, 0755);
                $this->info("Binary installed.");
            }
            shell_exec("rm -rf {$temp}");
        } else {
            $this->error("Binary ZIP not found! Please place " . basename($binZip) . " in project root.");
        }

        // 2. Models (v0.1.2)
        $modelsZip = base_path('realesrgan-ncnn-vulkan-v0.1.2-ubuntu.zip');
        $modelsDir = $binDir . '/models';

        if (!file_exists($modelsZip) && (!is_dir($modelsDir) || $this->option('force'))) {
            $this->info("Downloading models archive (v0.1.2)...");
            $url = 'https://github.com/xinntao/Real-ESRGAN-ncnn-vulkan/releases/download/v0.1.2/realesrgan-ncnn-vulkan-v0.1.2-ubuntu.zip';
            // Use -H "Accept: application/octet-stream" to be safe
            shell_exec("curl -L -H \"Accept: application/octet-stream\" {$url} -o " . escapeshellarg($modelsZip));
        }

        if (file_exists($modelsZip)) {
            $this->info("Extracting models from v0.1.2 archive...");
            $temp = $binDir . '/models_temp';
            if (!is_dir($temp)) mkdir($temp);
            shell_exec("unzip -o " . escapeshellarg($modelsZip) . " -d " . escapeshellarg($temp));
            $found = shell_exec("find {$temp} -name models -type d | head -n 1");
            if ($found) {
                if (!is_dir($modelsDir)) mkdir($modelsDir, 0755, true);
                shell_exec("cp -r " . trim($found) . "/* " . $modelsDir . "/");
                $this->info("Models installed.");
            }
            shell_exec("rm -rf {$temp}");
            // Optional: delete the models zip after extraction to keep root clean
            // unlink($modelsZip); 
        } else {
            $this->warn("Models ZIP (v0.1.2) not found and download failed!");
        }

        // 3. VTracer
        $vtracerZip = base_path('vtracer.zip'); // If you want to automate this too
        if (file_exists($vtracerZip)) {
            $this->info("Extracting VTracer...");
            shell_exec("unzip -o " . escapeshellarg($vtracerZip) . " -d " . escapeshellarg($binDir));
        }

        $this->info('Binary check completed.');
    }
}
