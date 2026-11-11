<?php

namespace App\Domain\PurchaseOrders\Exceptions;

use LogicException;

/**
 * A programming error, never a user error: the quota ledger is append-only.
 * A correction is a new row (ReleaseQuotaAction writes the reversing rows),
 * never an edit or a delete of an old one.
 */
class LedgerIsAppendOnlyException extends LogicException
{
    public static function update(): self
    {
        return new self('The quota ledger is append-only: rows cannot be updated. Write a reversing entry instead.');
    }

    public static function delete(): self
    {
        return new self('The quota ledger is append-only: rows cannot be deleted. Write a reversing entry instead.');
    }
}
