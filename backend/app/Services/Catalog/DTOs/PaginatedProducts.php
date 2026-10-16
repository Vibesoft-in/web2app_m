<?php

declare(strict_types=1);

namespace App\Services\Catalog\DTOs;

class PaginatedProducts
{
    /**
     * @param array<CommonProduct> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $totalPages,
        public readonly int $totalItems
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items'        => array_map(fn (CommonProduct $p) => $p->toArray(), $this->items),
            'current_page' => $this->currentPage,
            'total_pages'  => $this->totalPages,
            'total_items'  => $this->totalItems,
        ];
    }
}
