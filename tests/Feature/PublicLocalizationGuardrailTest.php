<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PublicLocalizationGuardrailTest extends TestCase
{
    /**
     * Migrated public UI should not reintroduce hardcoded Cyrillic copy.
     *
     * Legacy files keep a baseline while they are being migrated. Each public
     * i18n migration should lower the matching baseline entry.
     */
    public function test_public_blade_surfaces_do_not_add_hardcoded_cyrillic_ui_copy(): void
    {
        $legacyBaseline = [];

        $violations = [];

        foreach ($this->publicBladeFiles() as $file) {
            $relativePath = $this->relativePath($file->getPathname());
            $hardcodedLines = $this->hardcodedCyrillicLines($file->getPathname());
            $allowedCount = $legacyBaseline[$relativePath] ?? 0;

            if (count($hardcodedLines) <= $allowedCount) {
                continue;
            }

            $violations[] = sprintf(
                '%s has %d hardcoded Cyrillic UI lines; allowed baseline is %d. First lines: %s',
                $relativePath,
                count($hardcodedLines),
                $allowedCount,
                implode(', ', array_slice($hardcodedLines, 0, 5)),
            );
        }

        $this->assertSame(
            [],
            $violations,
            "Public UI copy must use lang JSON translations instead of new hardcoded Cyrillic literals.\n"
            .implode("\n", $violations),
        );
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function publicBladeFiles(): array
    {
        $paths = [
            resource_path('views/storefront'),
            resource_path('views/catalog'),
            resource_path('views/products'),
            resource_path('views/network'),
            resource_path('views/landing.blade.php'),
        ];

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

                if (! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * @return array<int, string>
     */
    private function hardcodedCyrillicLines(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        $matches = [];

        foreach ($lines as $lineNumber => $line) {
            if (! preg_match('/[А-Яа-яЁё]/u', $line)) {
                continue;
            }

            if ($this->lineUsesTranslationHelper($line)) {
                continue;
            }

            $matches[] = (string) ($lineNumber + 1);
        }

        return $matches;
    }

    private function lineUsesTranslationHelper(string $line): bool
    {
        return preg_match('/(@lang\s*\(|__\s*\(|trans\s*\(|trans_choice\s*\()/u', $line) === 1;
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
