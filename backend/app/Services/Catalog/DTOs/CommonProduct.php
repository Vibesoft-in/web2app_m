<?php

declare(strict_types=1);

namespace App\Services\Catalog\DTOs;

class CommonProduct
{
    /**
     * @param array<string> $images
     * @param array<ProductVariant> $variants
     * @param array<int|string> $categoryIds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $descriptionHtml,
        public readonly float $price,
        public readonly ?float $salePrice,
        public readonly string $currency,
        public readonly array $images,
        public readonly array $variants,
        public readonly bool $inStock,
        public readonly array $categoryIds,
        public readonly string $url
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'title'            => $this->title,
            'description_html' => $this->descriptionHtml,
            'price'            => $this->price,
            'sale_price'       => $this->salePrice,
            'currency'         => $this->currency,
            'images'           => $this->images,
            'variants'         => array_map(fn (ProductVariant $v) => $v->toArray(), $this->variants),
            'in_stock'         => $this->inStock,
            'category_ids'     => $this->categoryIds,
            'url'              => $this->url,
        ];
    }
}
