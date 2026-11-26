<?php

namespace App\Domain\Reports;

use App\Models\User;

/**
 * Who may use the reports section (FRD V01.14 "التقارير والاحصائيات", part 8.4) — read from the role descriptions:
 *  - System Admin, Data Entry ("كل الصلاحيات (تصدير / اطلاع)"), Auditor ("الاطلاع والتصدير لقسم التقارير"): everything;
 *  - the client's project manager ("عرض وتصدير التقارير الخاصة بمشروعاته"): everything, within his projects;
 *  - the client's project auditor ("قسم التقارير (الاكسل فقط) الخاص بمشروعه"): the Excel only — no charts;
 *  - the driver and the contractor user: nothing (the FRD gives them no reports).
 * The rows themselves are always the trips the user sees (Trip::scopeVisibleTo).
 */
final class ReportAccess
{
    private const FULL = ['system_admin', 'data_entry', 'auditor', 'client_project_manager'];

    public static function canUse(User $user): bool
    {
        return $user->hasAnyRole([...self::FULL, 'client_project_auditor']);
    }

    /** The charts and figures of the page; the client's project auditor gets the Excel only. */
    public static function seesCharts(User $user): bool
    {
        return $user->hasAnyRole(self::FULL);
    }
}
