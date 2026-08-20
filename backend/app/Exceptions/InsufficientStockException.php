<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a stock operation would result in a negative balance
 * and config('nova.allow_negative_stock') is false.
 *
 * This exception is mapped to HTTP 422 in bootstrap/app.php.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(string $productSku, string $warehouseName, string $available, string $requested)
    {
        parent::__construct(
            "Insufficient stock for '{$productSku}' in warehouse '{$warehouseName}'. "
            . "Available: {$available}, Requested: {$requested}."
        );
    }
}
