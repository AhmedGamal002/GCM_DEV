<?php

namespace App\Domain\Assets\Exceptions;

use RuntimeException;

class CannotDeactivateAssetException extends RuntimeException
{
    public function __construct(string $message = 'Only a System Admin can deactivate an asset.')
    {
        parent::__construct($message);
    }
}
