<?php

declare(strict_types=1);

namespace App\Services\Notifications;

class DeepLinkValidator
{
    private const ALLOWED_PATTERNS = [
        '/^product:[a-zA-Z0-9_\-]+$/',
        '/^order:[a-zA-Z0-9_\-]+$/',
        '/^category:[a-zA-Z0-9_\-]+$/',
    ];

    /**
     * Validates that a deep link strictly matches one of the allowed formats:
     * - product:ID
     * - order:ID
     * - category:ID
     *
     * Prevents open redirects, javascript injection, and unauthorized URL loading.
     */
    public function isValid(?string $deepLink): bool
    {
        if ($deepLink === null || trim($deepLink) === '') {
            return true; // Optional deep link is acceptable
        }

        $trimmed = trim($deepLink);
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed)) {
                return true;
            }
        }

        return false;
    }
}
