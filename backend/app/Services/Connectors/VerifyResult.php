<?php

declare(strict_types=1);

namespace App\Services\Connectors;

class VerifyResult
{
    /**
     * @param array<string, mixed> $details
     */
    public function __construct(
        public readonly bool $success,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly ?string $storeName = null,
        public readonly ?int $productsCount = null,
        public readonly ?string $currency = null,
        public readonly ?string $wcVersion = null,
        public readonly array $details = []
    ) {}

    public static function ok(
        string $storeName,
        int $productsCount,
        string $currency,
        string $wcVersion,
        array $details = []
    ): self {
        return new self(
            success: true,
            storeName: $storeName,
            productsCount: $productsCount,
            currency: $currency,
            wcVersion: $wcVersion,
            details: $details
        );
    }

    public static function fail(string $errorCode, string $errorMessage, array $details = []): self
    {
        return new self(
            success: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            details: $details
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->success) {
            return [
                'status'         => 'verified',
                'store_name'     => $this->storeName,
                'products_count' => $this->productsCount,
                'currency'       => $this->currency,
                'wc_version'     => $this->wcVersion,
                'details'        => $this->details,
            ];
        }

        return [
            'status'     => 'failed',
            'error_code' => $this->errorCode,
            'message'    => $this->errorMessage,
            'details'    => $this->details,
        ];
    }
}
