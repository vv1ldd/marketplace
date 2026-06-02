<?php

namespace EzPin\DTO;

class OrderRequest
{
    public function __construct(
        public int $sku,
        public int $quantity,
        public float $price,
        public string $referenceCode,
        public string $terminal_pin,
        public int $terminal_id,
        public string $destination,
        public bool $preOrder = false,
        public int $deliveryType = 0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'pre_order' => $this->preOrder,
            'delivery_type' => $this->deliveryType,
            'reference_code' => $this->referenceCode,
            'terminal_id' => $this->terminal_id,
            'terminal_pin' => $this->terminal_pin,
            'destination' => $this->destination,
        ];
    }
}
