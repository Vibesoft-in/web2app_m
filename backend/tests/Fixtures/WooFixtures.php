<?php

declare(strict_types=1);

namespace Tests\Fixtures;

class WooFixtures
{
    /**
     * Realistic WooCommerce system status response.
     *
     * @return array<string, mixed>
     */
    public static function systemStatus(): array
    {
        return [
            'environment' => [
                'site_title'     => 'Acme Organic Botanicals',
                'home_url'       => 'https://acme-organics.com',
                'site_url'       => 'https://acme-organics.com',
                'version'        => '8.7.0',
                'wp_version'     => '6.5.2',
                'php_version'    => '8.2.14',
                'secure_connection' => true,
            ],
            'settings' => [
                'currency'            => 'INR',
                'currency_symbol'     => '₹',
                'currency_position'   => 'left',
                'thousand_separator'  => ',',
                'decimal_separator'   => '.',
                'number_of_decimals'  => 2,
            ],
        ];
    }

    /**
     * Realistic WooCommerce product list item.
     *
     * @return array<string, mixed>
     */
    public static function product(int $id = 101, string $name = 'Rose Face Serum'): array
    {
        return [
            'id'             => $id,
            'name'           => $name,
            'slug'           => 'rose-face-serum',
            'permalink'      => "https://acme-organics.com/product/{$id}",
            'type'           => 'simple',
            'status'         => 'publish',
            'featured'       => true,
            'description'    => '<p>Pure revitalizing rose serum with <strong>vitamin C</strong>.</p><script>alert("xss")</script><img src="https://images.example.com/serum.jpg" onload="bad()"/>',
            'short_description' => '<p>Daily revitalizing serum.</p>',
            'sku'            => 'RFS-001',
            'price'          => '899',
            'regular_price'  => '999',
            'sale_price'     => '899',
            'on_sale'        => true,
            'purchasable'    => true,
            'stock_status'   => 'instock',
            'stock_quantity' => 45,
            'categories'     => [
                ['id' => 12, 'name' => 'Skincare', 'slug' => 'skincare'],
                ['id' => 15, 'name' => 'Face Serums', 'slug' => 'face-serums'],
            ],
            'images' => [
                [
                    'id'   => 501,
                    'src'  => 'https://acme-organics.com/wp-content/uploads/serum-front.jpg',
                    'name' => 'Front view',
                    'alt'  => 'Rose Serum Bottle',
                ],
                [
                    'id'   => 502,
                    'src'  => 'https://acme-organics.com/wp-content/uploads/serum-box.jpg',
                    'name' => 'Packaging',
                    'alt'  => 'Rose Serum Box',
                ],
            ],
            'attributes' => [
                [
                    'id'      => 1,
                    'name'    => 'Volume',
                    'options' => ['30ml', '50ml'],
                ],
            ],
            'variations' => [
                [
                    'id'           => 102,
                    'name'         => "{$name} - 30ml",
                    'price'        => '899',
                    'regular_price' => '999',
                    'sale_price'   => '899',
                    'stock_status' => 'instock',
                    'sku'          => 'RFS-30ML',
                    'attributes'   => ['Volume' => '30ml'],
                ],
                [
                    'id'           => 103,
                    'name'         => "{$name} - 50ml",
                    'price'        => '1299',
                    'regular_price' => '1499',
                    'sale_price'   => '1299',
                    'stock_status' => 'instock',
                    'sku'          => 'RFS-50ML',
                    'attributes'   => ['Volume' => '50ml'],
                ],
            ],
        ];
    }

    /**
     * Realistic WooCommerce categories list.
     *
     * @return array<array<string, mixed>>
     */
    public static function categories(): array
    {
        return [
            [
                'id'          => 12,
                'name'        => 'Skincare',
                'slug'        => 'skincare',
                'parent'      => 0,
                'description' => 'Organic skincare products',
                'count'       => 28,
                'image'       => ['src' => 'https://acme-organics.com/cats/skincare.jpg'],
            ],
            [
                'id'          => 15,
                'name'        => 'Face Serums',
                'slug'        => 'face-serums',
                'parent'      => 12,
                'description' => 'Targeted facial serums',
                'count'       => 12,
                'image'       => ['src' => 'https://acme-organics.com/cats/serums.jpg'],
            ],
        ];
    }
}
