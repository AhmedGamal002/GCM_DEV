<?php

namespace App\Domain\Services\Exceptions;

use RuntimeException;

/**
 * Deactivating a main service deactivates its sub-services (FRD §1.9.1), so
 * a sub-service can't be active under a deactivated main service.
 */
class MainServiceDeactivatedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('The main service is deactivated — activate it before activating this sub-service.'));
    }
}
