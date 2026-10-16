<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Catalog\Mappers\WooProductMapper;
use Tests\Fixtures\WooFixtures;
use Tests\TestCase;

class WooProductMapperTest extends TestCase
{
    private WooProductMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new WooProductMapper();
    }

    public function test_maps_woocommerce_product_to_common_product_dto(): void
    {
        $raw = WooFixtures::product(101, 'Organic Rose Mist');
        $product = $this->mapper->map($raw, 'INR');

        $this->assertEquals('101', $product->id);
        $this->assertEquals('Organic Rose Mist', $product->title);
        $this->assertEquals(899.0, $product->price);
        $this->assertEquals(899.0, $product->salePrice);
        $this->assertEquals('INR', $product->currency);
        $this->assertTrue($product->inStock);
        $this->assertCount(2, $product->images);
        $this->assertEquals('https://acme-organics.com/wp-content/uploads/serum-front.jpg', $product->images[0]);
        $this->assertCount(2, $product->variants);
        $this->assertEquals('102', $product->variants[0]->id);
        $this->assertEquals([12, 15], $product->categoryIds);
        $this->assertEquals('https://acme-organics.com/product/101', $product->url);
    }

    public function test_sanitizes_malicious_html_scripts_and_event_handlers_in_description(): void
    {
        $maliciousHtml = '<p>Safe text with <b>bold</b> and <a href="https://good.com">link</a>.</p>'
            . '<script>alert("hacked")</script>'
            . '<img src="https://example.com/pic.jpg" onerror="stealCookies()">'
            . '<iframe src="https://evil.com"></iframe>'
            . '<a href="javascript:doBadThings()">Click</a>';

        $sanitized = $this->mapper->sanitizeHtml($maliciousHtml);

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('alert(', $sanitized);
        $this->assertStringNotContainsString('onerror', $sanitized);
        $this->assertStringNotContainsString('<iframe', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);

        // Safe elements are preserved
        $this->assertStringContainsString('Safe text with <b>bold</b>', $sanitized);
        $this->assertStringContainsString('https://good.com', $sanitized);
    }
}
