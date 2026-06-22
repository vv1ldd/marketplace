<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TonAddressCodecTest extends TestCase
{
    #[Test]
    public function it_parses_known_friendly_ton_address(): void
    {
        $codec = app(\App\Support\TonAddressCodec::class);

        $this->assertTrue($codec->isValidAddress('EQDrjaLahLkMB-hMCmkzOyBuHJ139ZUYmPHu6RRBKnbdLIYI'));
        $normalized = $codec->normalizeAddress('EQDrjaLahLkMB-hMCmkzOyBuHJ139ZUYmPHu6RRBKnbdLIYI');

        $this->assertIsString($normalized);
        $this->assertStringStartsWith('UQ', $normalized);
    }

    #[Test]
    public function it_parses_raw_ton_address(): void
    {
        $codec = app(\App\Support\TonAddressCodec::class);
        $raw = '0:'.str_repeat('ab', 32);

        $this->assertTrue($codec->isValidAddress($raw));
    }
}
