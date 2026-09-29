<?php

namespace App\Domain\Companies\Actions;

use App\Models\Company;
use App\Models\User;

/**
 * FRD V01.14 §1.11.4: deactivating / re-activating a client company is
 * (System Admin / Data Entry).
 *
 * The FRD says nothing about deactivating a company also deactivating its
 * projects or accounts, so nothing cascades — the status is only the
 * company's own. (The pre-V01.14 plan assumed a cascade; the client's FRD
 * is the source of truth.)
 */
class UpdateCompanyStatusAction
{
    public function execute(Company $company, string $status, User $actor): Company
    {
        // operational_status is outside $fillable — set explicitly.
        $company->operational_status = $status;
        $company->updated_by = $actor->id;
        $company->save();

        return $company;
    }
}
