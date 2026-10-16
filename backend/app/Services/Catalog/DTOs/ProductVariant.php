<?php

declare(strict_types=1);

namespace App\Services\Catalog\DTOs;

class ProductVariant
{
    /**
     * @param array<string, string> $attributes e.g. ['Size' => 'L', 'Color' => 'Red']
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly float $price,
        public readonly ?float $salePrice,
        public readonly bool $inStock,
        public readonly ?string $sku = null,
        public readonly array $attributes = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'price'      => $this->price,
            'sale_price' => $this->salePrice,
            'in_stock'   => $this->inStock,
            'sku'        => $this->sku,
            'attributes' => $this->attributes,
        ];
    }
}
