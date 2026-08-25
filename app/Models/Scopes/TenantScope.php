<?php

namespace App\Models\Scopes;

use App\Exceptions\TenantContextMissingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('tenant')) {
            throw new TenantContextMissingException($model::class);
        }

        $builder->where($model->qualifyColumn('tenant_id'), app('tenant')->id);
    }
}
