<?php

declare(strict_types=1);

namespace App\Services\Catalog\Mappers;

use App\Services\Catalog\DTOs\CommonProduct;
use App\Services\Catalog\DTOs\ProductVariant;

class WooProductMapper
{
    /**
     * Maps a WooCommerce product array to CommonProduct DTO.
     *
     * @param array<string, mixed> $raw
     */
    public function map(array $raw, string $currency = 'INR'): CommonProduct
    {
        $id = (string) ($raw['id'] ?? '');
        $title = (string) ($raw['name'] ?? $raw['title'] ?? '');
        $descriptionHtml = $this->sanitizeHtml((string) ($raw['description'] ?? ''));

        $price = (float) ($raw['price'] ?? $raw['regular_price'] ?? 0.0);
        $salePrice = isset($raw['sale_price']) && $raw['sale_price'] !== '' && (float) $raw['sale_price'] > 0
            ? (float) $raw['sale_price']
            : null;

        $inStock = ($raw['stock_status'] ?? 'instock') === 'instock';

        // Extract image URLs
        $images = [];
        if (isset($raw['images']) && is_array($raw['images'])) {
            foreach ($raw['images'] as $img) {
                if (isset($img['src']) && is_string($img['src'])) {
                    $images[] = $img['src'];
                }
            }
        }

        // Extract variants / attributes
        $variants = [];
        if (isset($raw['variations']) && is_array($raw['variations'])) {
            foreach ($raw['variations'] as $v) {
                if (is_array($v)) {
                    $variants[] = new ProductVariant(
                        id: (string) ($v['id'] ?? ''),
                        title: (string) ($v['name'] ?? $title),
                        price: (float) ($v['price'] ?? $price),
                        salePrice: isset($v['sale_price']) && $v['sale_price'] !== '' ? (float) $v['sale_price'] : null,
                        inStock: ($v['stock_status'] ?? 'instock') === 'instock',
                        sku: isset($v['sku']) ? (string) $v['sku'] : null,
                        attributes: is_array($v['attributes'] ?? null) ? $v['attributes'] : []
                    );
                }
            }
        }

        // Extract category IDs
        $categoryIds = [];
        if (isset($raw['categories']) && is_array($raw['categories'])) {
            foreach ($raw['categories'] as $cat) {
                if (isset($cat['id'])) {
                    $categoryIds[] = (int) $cat['id'];
                }
            }
        }

        $url = (string) ($raw['permalink'] ?? '');

        return new CommonProduct(
            id: $id,
            title: $title,
            descriptionHtml: $descriptionHtml,
            price: $price,
            salePrice: $salePrice,
            currency: $currency,
            images: $images,
            variants: $variants,
            inStock: $inStock,
            categoryIds: $categoryIds,
            url: $url
        );
    }

    /**
     * Sanitizes product description HTML to prevent XSS.
     * Strips script tags, unsafe event handlers, javascript: pseudo-protocols.
     */
    public function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        // Remove <script> tags and contents
        $sanitized = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $html);

        // Remove <iframe>, <object>, <embed>, <applet>
        $sanitized = preg_replace('#<(iframe|object|embed|applet)(.*?)>(.*?)</\1>#is', '', (string) $sanitized);

        // Remove inline event handlers (onload, onerror, onclick, etc.)
        $sanitized = preg_replace('#\s+on[a-z]+\s*=\s*(["\'][^"\']*["\']|[^\s>]+)#i', '', (string) $sanitized);

        // Remove javascript: pseudo-protocol in links or src
        $sanitized = preg_replace('#(href|src)\s*=\s*["\']\s*javascript:[^"\']*["\']#i', '', (string) $sanitized);

        // Strip dangerous tags completely
        return strip_tags((string) $sanitized, '<p><br><b><strong><i><em><u><ul><ol><li><img><span><div><h1><h2><h3><h4><h5><h6><table><thead><tbody><tr><th><td><blockquote><a>');
    }
}
