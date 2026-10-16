<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Services\Catalog\DTOs\CommonProduct;
use App\Services\Catalog\DTOs\PaginatedProducts;

interface ConnectorInterface
{
    /**
     * Verifies store connection, credentials, and API access permissions.
     */
    public function verify(): VerifyResult;

    /**
     * Retrieves paginated products mapped to CommonProduct DTOs.
     *
     * @param array<string, mixed> $params
     */
    public function getProducts(array $params = []): PaginatedProducts;

    /**
     * Retrieves a single product by ID.
     */
    public function getProduct(string $id): CommonProduct;

    /**
     * Retrieves store categories.
     *
     * @return array<array<string, mixed>>
     */
    public function getCategories(): array;

    /**
     * Searches products matching the given search query term.
     *
     * @return array<CommonProduct>
     */
    public function searchProducts(string $term): array;

    /**
     * Registers webhooks for store updates (product.updated, order.updated).
     *
     * @return array<string, mixed>
     */
    public function registerWebhooks(string $webhookUrl, string $secret): array;

    /**
     * Finds or creates a customer in the store platform, returning the platform customer ID.
     *
     * @param array<string, mixed> $customerData
     */
    public function findOrCreateCustomer(string $phoneOrEmail, array $customerData = []): string;

    /**
     * Revalidates cart items and generates a secure checkout URL.
     *
     * @param array<array<string, mixed>> $cartItems
     */
    public function createCheckoutUrl(array $cartItems, ?string $storeCustomerId = null): string;

    /**
     * Retrieves orders specifically for a given store customer.
     *
     * @param array<string, mixed> $params
     * @return array<array<string, mixed>>
     */
    public function getOrders(string $storeCustomerId, array $params = []): array;

    /**
     * Retrieves a single order by ID, verifying customer ownership.
     *
     * @return array<string, mixed>|null
     */
    public function getOrder(string $orderId, string $storeCustomerId): ?array;
}
