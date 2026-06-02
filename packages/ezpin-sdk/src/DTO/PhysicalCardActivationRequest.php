<?php

namespace EzPin\DTO;

class PhysicalCardActivationRequest
{
    public function __construct(
        public string $barcode,
        public int $sku,
        public float $price,
        public string $referenceCode,
        public ?int $terminalId = null,
        public ?string $terminalPin = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'price' => $this->price,
            'reference_code' => $this->referenceCode,
            'terminal_id' => $this->terminalId,
            'terminal_pin' => $this->terminalPin,
        ], fn (mixed $value): bool => $value !== null);
    }
}
