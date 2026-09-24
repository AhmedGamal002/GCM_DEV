<?php

namespace App\Domain\Vehicles\Exceptions;

use RuntimeException;

/**
 * The primary (built-in) vehicle categories — see VehicleCategory::DEFAULTS —
 * can be renamed but never deleted, whether or not anything uses them.
 */
class PrimaryVehicleCategoryException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('This is a primary category and can\'t be deleted.'));
    }
}
