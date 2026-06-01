<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PublicPricingProjectionGuardrailTest extends TestCase
{
    /**
     * Public rendering surfaces must consume PricingProjectionService output,
     * not storage price columns.
     */
    public function test_public_rendering_surfaces_do_not_access_storage_price_fields(): void
    {
        $forbiddenTokens = [
            'old_price_rub',
            'purchase_price_rub',
            'price_rub',
        ];

        $paths = [
            resource_path('views/storefront'),
            resource_path('views/catalog'),
            resource_path('views/network'),
            resource_path('views/products'),
            resource_path('views/landing.blade.php'),
            app_path('Http/Resources/Public'),
        ];

        $violations = [];

        foreach ($this->publicRenderingFiles($paths) as $file) {
            $contents = file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            foreach ($forbiddenTokens as $token) {
                if (str_contains($contents, $token)) {
                    $violations[] = $this->relativePath($file->getPathname()).' contains '.$token;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Public price rendering must use PricingProjectionService/display_price instead of storage price fields.\n"
            .implode("\n", $violations),
        );
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, SplFileInfo>
     */
    private function publicRenderingFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! file_exists($path)) {
                continue;
            }

            if (is_file($path)) {
                $files[] = new SplFileInfo($path);
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                $extension = $file->getExtension();
                if (! in_array($extension, ['php', 'blade.php'], true) && ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $files[] = $file;
            }
        }

        return $files;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
