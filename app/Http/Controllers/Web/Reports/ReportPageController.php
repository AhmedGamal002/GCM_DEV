<?php

namespace App\Http\Controllers\Web\Reports;

use App\Domain\Reports\ReportAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * The reports page (FRD V01.14 "التقارير والاحصائيات", part 8.4) — a Blade shell over /api/v1/reports/*: the nine
 * filters, the figures and charts, and the trips summary (table + Excel). The client's project auditor gets the
 * filters and the Excel only; the driver and the contractor user have no reports (403).
 */
class ReportPageController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(ReportAccess::canUse($request->user()), 403);

        return view('tenant.reports.index', ['seesCharts' => ReportAccess::seesCharts($request->user())]);
    }
}
