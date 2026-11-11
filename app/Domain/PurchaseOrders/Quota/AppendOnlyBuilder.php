<?php

namespace App\Domain\PurchaseOrders\Quota;

use App\Domain\PurchaseOrders\Exceptions\LedgerIsAppendOnlyException;
use Illuminate\Database\Eloquent\Builder;

/**
 * The query builder of QuotaLedgerEntry: reads and inserts as usual, but any
 * mass update or delete throws — `QuotaLedgerEntry::where(...)->update()`
 * would otherwise skip the model events that guard single rows.
 *
 * It covers the Eloquent builder's own writes (update, upsert, touch,
 * increment, decrement, delete, forceDelete) AND the query-builder writes the
 * Eloquent builder would forward untouched through __call (truncate,
 * updateOrInsert, updateFrom, incrementEach, decrementEach). The model's
 * quiet variants (saveQuietly, deleteQuietly) skip the model events but still
 * end here, so they are refused too. Only an explicit escape — `toBase()`,
 * `getQuery()` or `DB::table('quota_ledger_entries')` — gets past it; nothing
 * in the app writes that way, and nothing should.
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

    public function touch($column = null)
    {
        throw LedgerIsAppendOnlyException::update();
    }

    // Not defined on the Eloquent builder: without these, __call would forward
    // them to the query builder and they would run.

    public function updateOrInsert(array $attributes, $values = [])
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function updateFrom(array $values)
    {
        throw LedgerIsAppendOnlyException::update();
    }

    public function truncate()
    {
        throw LedgerIsAppendOnlyException::delete();
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
