<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Connection;
use App\Services\Security\CredentialVault;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for Connection.
 *
 * CRITICAL SECURITY INVARIANT:
 * Raw credentials (consumer_secret, access tokens, private keys) are NEVER serialized.
 * Only non-sensitive metadata and safely masked key identifiers are exposed.
 *
 * @mixin Connection
 */
class ConnectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $maskedKey = null;

        if ($this->credentials) {
            try {
                $vault = new CredentialVault();
                $creds = is_array($this->credentials)
                    ? $this->credentials
                    : $vault->decrypt((string) $this->credentials);

                $rawKey = (string) ($creds['consumer_key'] ?? $creds['ck'] ?? '');
                if ($rawKey !== '') {
                    $maskedKey = $vault->maskKey($rawKey);
                }
            } catch (\Throwable) {
                $maskedKey = '****';
            }
        }

        return [
            'id'                   => $this->id,
            'app_ulid'             => $this->app?->ulid,
            'platform'             => $this->platform,
            'site_url'             => $this->site_url,
            'status'               => $this->status,
            'consumer_key_preview' => $maskedKey,
            'last_verified_at'     => $this->last_verified_at?->toISOString(),
            'last_error_code'      => $this->last_error_code,
            'created_at'           => $this->created_at?->toISOString(),
            'updated_at'           => $this->updated_at?->toISOString(),
        ];
    }
}
