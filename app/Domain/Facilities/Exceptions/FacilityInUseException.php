<?php

namespace App\Domain\Facilities\Exceptions;

use RuntimeException;

/**
 * A facility that is already used by a sub-service or a trip can't have its
 * environmental service or recycling efficiency changed.
 */
class FacilityInUseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('This facility is in use, so its environmental service and recycling efficiency can\'t be changed.'));
    }
}
