<?php

declare(strict_types=1);

namespace App\Services\Security;

use Illuminate\Support\Facades\Crypt;
use JsonException;
use RuntimeException;

class CredentialVault
{
    /**
     * Encrypts connection credentials array into an encrypted payload string.
     *
     * @param array<string, mixed> $credentials
     */
    public function encrypt(array $credentials): string
    {
        try {
            $json = json_encode($credentials, JSON_THROW_ON_ERROR);
            return Crypt::encryptString($json);
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to serialize credentials: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decrypts an encrypted payload back into credentials array.
     *
     * @return array<string, mixed>
     */
    public function decrypt(string $payload): array
    {
        try {
            $json = Crypt::decryptString($payload);
            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to decrypt connection credentials: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Produces a masked version of a key (e.g. "ck_1234567890abcdef" -> "ck_****cdef").
     */
    public function maskKey(string $key): string
    {
        $len = strlen($key);
        if ($len <= 8) {
            return '****';
        }

        $prefix = substr($key, 0, 3);
        $suffix = substr($key, -4);

        return "{$prefix}****{$suffix}";
    }

    /**
     * Masks all secret fields in credentials for safe diagnostic display.
     * Raw secret values are NEVER returned.
     *
     * @param array<string, mixed> $credentials
     * @return array<string, mixed>
     */
    public function maskCredentials(array $credentials): array
    {
        $masked = [];
        foreach ($credentials as $key => $value) {
            if (is_string($value)) {
                $masked[$key] = $this->maskKey($value);
            } else {
                $masked[$key] = '****';
            }
        }

        return $masked;
    }
}
