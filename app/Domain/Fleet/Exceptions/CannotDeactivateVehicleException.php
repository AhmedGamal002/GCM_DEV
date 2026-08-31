<?php

namespace App\Domain\Fleet\Exceptions;

use RuntimeException;

class CannotDeactivateVehicleException extends RuntimeException
{
    public function __construct(string $message = 'Only a System Admin can deactivate a vehicle.')
    {
        parent::__construct($message);
    }
}
