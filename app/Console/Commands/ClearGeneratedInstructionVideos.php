<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearGeneratedInstructionVideos extends Command
{
    protected $signature = 'videos:clear-cache
                            {--force : Не спрашивать подтверждение}';

    protected $description = 'Удалить локально сгенерированные инструкционные видео (VideoInstructionService): storage/app/public/videos/*.mp4 и временные s*/v*/list_* рядом.';

    public function handle(): int
    {
        $publicStorage = storage_path('app/public');

        if (! is_dir($publicStorage)) {
            $this->warn("Каталог не найден: {$publicStorage}");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Удалить сгенерированные видео и временные файлы сцен?', false)) {
            $this->info('Отменено.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $dirs = [
            $publicStorage.'/videos',
        ];

        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::glob($dir.'/*.mp4') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }
            }
        }

        // Остатки после сбоя ffmpeg / сцен (VideoInstructionService)
        foreach (['s[1-4]_*.png', 'v[1-4]_*.mp4', 'list_*.txt'] as $pattern) {
            foreach (File::glob($publicStorage.'/'.$pattern) ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }
            }
        }

        $this->info("Удалено файлов: {$deleted}");
        $this->comment('URL на R2 / внешние ссылки в products.videos не трогаем — только локальные файлы.');

        return self::SUCCESS;
    }
}
