<?php

declare(strict_types=1);

namespace App\Services\Security\Exceptions;

use RuntimeException;

class UrlGuardException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'URL_BLOCKED'
    ) {
        parent::__construct($message);
    }

    public static function notHttps(string $url): self
    {
        return new self("The URL '{$url}' must use secure HTTPS protocol.", 'NOT_HTTPS');
    }

    public static function blockedIp(string $ip, string $reason): self
    {
        return new self("Access to IP address '{$ip}' is blocked ({$reason}).", 'REST_BLOCKED');
    }

    public static function invalidPort(int $port): self
    {
        return new self("Port '{$port}' is not allowed. Only HTTPS port 443 is permitted.", 'REST_BLOCKED');
    }

    public static function resolutionFailed(string $host): self
    {
        return new self("Unable to resolve DNS for host '{$host}'.", 'URL_UNREACHABLE');
    }

    public static function redirectLimitExceeded(int $limit): self
    {
        return new self("Redirect limit of {$limit} hops exceeded.", 'REST_BLOCKED');
    }

    public static function responseSizeExceeded(int $maxBytes): self
    {
        return new self("Response size exceeded maximum allowed limit of {$maxBytes} bytes.", 'REST_BLOCKED');
    }

    public static function timeout(): self
    {
        return new self("Request timed out after 10 seconds.", 'URL_UNREACHABLE');
    }
}
