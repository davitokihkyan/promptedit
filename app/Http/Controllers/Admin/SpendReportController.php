<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Reports\SpendReport;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

final class SpendReportController extends Controller
{
    // Matches the default range of Pixelforge's usage report.
    private const int DAYS = 30;

    public function __invoke(SpendReport $report): Response
    {
        $to = Date::now('UTC')->toImmutable();
        $from = $to->subDays(self::DAYS - 1);

        return Inertia::render('admin/SpendReport', [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'report' => $report->forPeriod($from, $to),
        ]);
    }
}
