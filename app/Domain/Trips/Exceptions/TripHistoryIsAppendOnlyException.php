<?php

namespace App\Domain\Trips\Exceptions;

use LogicException;

/** A programming error, never a user error: a trip's history (trip_events) is append-only. */
class TripHistoryIsAppendOnlyException extends LogicException
{
    public static function update(): self
    {
        return new self('A trip\'s history is append-only: its rows cannot be updated.');
    }

    public static function delete(): self
    {
        return new self('A trip\'s history is append-only: its rows cannot be deleted.');
    }
}
