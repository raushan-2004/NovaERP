<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a workflow status transition is invalid.
 *
 * Examples:
 *   - Purchase Request: draft → approved (must go via submitted)
 *   - Purchase Order: sent → draft (backward transitions not allowed)
 *   - Stock Transfer: completed → cancelled (terminal state)
 *
 * This exception is mapped to HTTP 422 in bootstrap/app.php.
 */
class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(string $entity, string $from, string $to)
    {
        parent::__construct(
            "Cannot transition {$entity} from '{$from}' to '{$to}'."
        );
    }
}
