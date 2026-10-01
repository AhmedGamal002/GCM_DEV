<?php

namespace App\Domain\Services\Exceptions;

use RuntimeException;

/**
 * FRD §1.9.1: a main service that has been used (a PO includes it) can't be
 * edited or deactivated — and neither can one with a sub-service tied to an
 * ongoing trip, since deactivating the main deactivates every sub-service.
 */
class MainServiceInUseException extends RuntimeException
{
    public static function inPurchaseOrder(): self
    {
        return new self(__('This main service is included in a purchase order, so it can\'t be edited or deactivated.'));
    }

    public static function lockedSubService(): self
    {
        return new self(__('A sub-service under this main service is tied to an ongoing trip, so the main service can\'t be deactivated.'));
    }
}
