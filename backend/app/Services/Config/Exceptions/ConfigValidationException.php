<?php

declare(strict_types=1);

namespace App\Services\Config\Exceptions;

use RuntimeException;

class ConfigValidationException extends RuntimeException
{
    /**
     * @param array<string, array<string>> $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        public readonly string $errorCode = 'CONFIG_INVALID'
    ) {
        parent::__construct($message);
    }
}
