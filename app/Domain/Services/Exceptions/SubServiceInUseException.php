<?php

namespace App\Domain\Services\Exceptions;

use RuntimeException;

/**
 * FRD §1.9.2: a sub-service tied to an ongoing trip, or whose main service
 * is in a PO, can't be edited or deactivated.
 */
class SubServiceInUseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('This sub-service is tied to an ongoing trip or its main service is in a purchase order, so it can\'t be edited or deactivated.'));
    }
}
