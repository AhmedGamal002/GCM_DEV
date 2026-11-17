<?php

namespace App\Domain\PurchaseOrders\Quota;

use App\Domain\PurchaseOrders\Exceptions\LedgerIsAppendOnlyException;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

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
 *
 * Other append-only tables (the trip history, part 7.1) extend it and
 * override updateRefused() / deleteRefused() to throw their own exception.
 */
class AppendOnlyBuilder extends Builder
{
    protected function updateRefused(): LogicException
    {
        return LedgerIsAppendOnlyException::update();
    }

    protected function deleteRefused(): LogicException
    {
        return LedgerIsAppendOnlyException::delete();
    }

    public function update(array $values)
    {
        throw $this->updateRefused();
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw $this->updateRefused();
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        throw $this->updateRefused();
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        throw $this->updateRefused();
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        throw $this->updateRefused();
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        throw $this->updateRefused();
    }

    public function touch($column = null)
    {
        throw $this->updateRefused();
    }

    // Not defined on the Eloquent builder: without these, __call would forward
    // them to the query builder and they would run.

    public function updateOrInsert(array $attributes, $values = [])
    {
        throw $this->updateRefused();
    }

    public function updateFrom(array $values)
    {
        throw $this->updateRefused();
    }

    public function truncate()
    {
        throw $this->deleteRefused();
    }

    public function delete()
    {
        throw $this->deleteRefused();
    }

    public function forceDelete()
    {
        throw $this->deleteRefused();
    }
}
