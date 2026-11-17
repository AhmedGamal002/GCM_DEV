<?php

namespace App\Domain\Trips\Support;

use App\Domain\PurchaseOrders\Quota\AppendOnlyBuilder;
use App\Domain\Trips\Exceptions\TripHistoryIsAppendOnlyException;
use LogicException;

/** The query builder of TripEvent: the quota ledger's append-only builder, with the trip history's own exception. */
class TripEventBuilder extends AppendOnlyBuilder
{
    protected function updateRefused(): LogicException
    {
        return TripHistoryIsAppendOnlyException::update();
    }

    protected function deleteRefused(): LogicException
    {
        return TripHistoryIsAppendOnlyException::delete();
    }
}
