<?php

namespace App\Exports;

use App\Models\Claim;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ClaimReportExport implements Export, WithMultipleSheets
{
    /**
     * @param  array<string, mixed>  $report
     * @param  Builder<Claim>  $claims
     */
    public function __construct(
        private readonly array $report,
        private readonly Builder $claims,
    ) {}

    public function sheets(): array
    {
        return [
            new ReportSummarySheet($this->report['summary'], $this->report['period'], $this->report['claimsByStatus']),
            new ReportClaimsSheet($this->claims),
            new ReportDiagnosesSheet($this->report['topDiagnoses']),
            new ReportTrendSheet($this->report['monthlyTrend']),
        ];
    }
}
