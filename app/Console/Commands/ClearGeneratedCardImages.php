<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearGeneratedCardImages extends Command
{
    protected $signature = 'cards:clear-cache
                            {shop? : ID магазина (только папка sh_{id}); без аргумента — все магазины}
                            {--force : Не спрашивать подтверждение при очистке всех}';

    protected $description = 'Удалить сгенерированные JPEG карточек (*_v3.jpg) в public/img/card/sh_*. Фон bg.png не трогаем.';

    public function handle(): int
    {
        $shopArg = $this->argument('shop');
        $base = public_path('img/card');

        if (! is_dir($base)) {
            $this->warn("Каталог не найден: {$base}");

            return self::SUCCESS;
        }

        if ($shopArg === null) {
            if (! $this->option('force') && ! $this->confirm('Удалить все файлы *_v3.jpg во всех папках sh_*?', false)) {
                $this->info('Отменено.');

                return self::SUCCESS;
            }
            $dirs = glob($base.'/sh_*') ?: [];
        } else {
            $dir = $base.'/sh_'.(int) $shopArg;
            if (! is_dir($dir)) {
                $this->error("Папка не найдена: {$dir}");

                return self::FAILURE;
            }
            $dirs = [$dir];
        }

        $deleted = 0;
        foreach ($dirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            foreach (File::glob($dir.'/*_v3.jpg') ?: [] as $file) {
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }
            }
        }

        $this->info("Удалено файлов: {$deleted}");

        return self::SUCCESS;
    }
}
