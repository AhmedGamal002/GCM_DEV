<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * FRD §6 (توصيف صفحات النظام لدور مستخدمي المتعهد): a contractor's own account
 * sees its contractor's resources and nothing else. This is the single rule
 * every contractor-facing list / stats / export applies and every policy's
 * per-record check asks — do not re-write it in a controller.
 *
 * Only the `contractor_user` role is narrowed; every other role keeps seeing
 * the whole tenant (its own Policy decides whether it may at all). A
 * contractor account with no contractor attached sees nothing rather than
 * everything.
 *
 * For models whose list is joined to another table, the column is qualified
 * (`drivers.contractor_id`) so the same scope works there.
 */
trait VisibleToContractorAccount
{
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        if (! $actor->isContractorUser()) {
            return $query;
        }

        return $actor->contractor_id === null
            ? $query->whereRaw('0 = 1')
            : $query->where($this->qualifyColumn('contractor_id'), $actor->contractor_id);
    }

    public function isVisibleTo(User $actor): bool
    {
        if (! $actor->isContractorUser()) {
            return true;
        }

        return $actor->contractor_id !== null
            && $this->contractor_id !== null
            && (int) $this->contractor_id === (int) $actor->contractor_id;
    }
}
