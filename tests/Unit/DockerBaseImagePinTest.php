<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DockerBaseImagePinTest extends TestCase
{
    public function test_marketplace_dockerfiles_use_locked_base_image_digests(): void
    {
        $root = dirname(__DIR__, 2);
        $lockPath = $root.'/docker/base-images.lock.json';

        $this->assertFileExists($lockPath);

        /** @var array{images: array<string, string>} $lock */
        $lock = json_decode((string) file_get_contents($lockPath), true, 512, JSON_THROW_ON_ERROR);

        $dockerfiles = [
            $root.'/Dockerfile',
            $root.'/frontend/Dockerfile',
        ];

        foreach ($dockerfiles as $dockerfile) {
            $this->assertFileExists($dockerfile, "Missing Dockerfile: {$dockerfile}");
            $contents = (string) file_get_contents($dockerfile);

            foreach ($lock['images'] as $image => $digest) {
                if (! str_contains($contents, $image)) {
                    continue;
                }

                $this->assertStringContainsString(
                    "{$image}@{$digest}",
                    $contents,
                    "Dockerfile {$dockerfile} must pin {$image} to {$digest}",
                );
            }
        }
    }
}
