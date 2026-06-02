<?php

namespace EzPin\DTO;

class RetailerOrderRequest
{
    public function __construct(
        public string $product_code,
        public int $quantity,
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
            'product_code' => $this->product_code,
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
