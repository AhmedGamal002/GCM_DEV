<?php

namespace App\Domain\PurchaseOrders\Quota;

use App\Domain\PurchaseOrders\Exceptions\LedgerIsAppendOnlyException;
use Illuminate\Database\Eloquent\Builder;

/**
 * The query builder of QuotaLedgerEntry: reads and inserts as usual, but any
 * mass update or delete throws — `QuotaLedgerEntry::where(...)->update()`
 * would otherwise skip the model events that guard single rows. (A raw
 * `DB::table('quota_ledger_entries')` query is still possible; nothing in
 * the app does that, and nothing should.)
 */
class AppendOnlyBuilder extends Builder
{
    public function update(array $values)
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function delete()
    {
        throw LedgerIsAppendOnlyException::delete();
    }

    public function forceDelete()
    {
        throw LedgerIsAppendOnlyException::delete();
    }
}
