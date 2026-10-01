<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Base class for business-rule violations.
 *
 * These are expected, recoverable failures (an empty basket, an expired
 * coupon) as opposed to programming errors. They are rendered as 422 rather
 * than 500, and they are safe to show to the client.
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode = 'domain_error',
        private readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
