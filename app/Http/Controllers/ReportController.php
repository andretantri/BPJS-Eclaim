<?php

namespace App\Http\Controllers;

use App\Exports\ClaimReportExport;
use App\Models\Claim;
use App\Services\ClaimReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ClaimReportService $reportService) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Claim::class);

        $hospitalId = (int) $request->user()?->hospital_id;
        $periodId = $this->resolvePeriodId($request, $hospitalId);

        return Inertia::render('Reports/Index', $this->reportService->build($hospitalId, $periodId));
    }

    public function exportExcel(Request $request): SymfonyResponse
    {
        Gate::authorize('export', Claim::class);

        $hospitalId = (int) $request->user()?->hospital_id;
        $periodId = $this->resolvePeriodId($request, $hospitalId);
        $report = $this->reportService->build($hospitalId, $periodId);

        return Excel::download(
            new ClaimReportExport($report, $this->reportService->exportClaimsQuery($hospitalId, $periodId)),
            $this->fileName($report['period']?->nama_periode ?? null, 'xlsx'),
        );
    }

    public function exportPdf(Request $request): SymfonyResponse
    {
        Gate::authorize('export', Claim::class);

        $hospitalId = (int) $request->user()?->hospital_id;
        $periodId = $this->resolvePeriodId($request, $hospitalId);
        $report = $this->reportService->build($hospitalId, $periodId);

        return Pdf::loadView('exports.laporan-klaim', [
            'report' => $report,
            'hospital' => $request->user()?->hospital,
        ])
            ->setPaper('a4', 'landscape')
            ->download($this->fileName($report['period']?->nama_periode ?? null, 'pdf'));
    }

    private function resolvePeriodId(Request $request, int $hospitalId): ?int
    {
        $validated = $request->validate([
            'periode' => ['sometimes', 'nullable', 'string'],
        ]);

        return $this->reportService->resolvePeriodId($validated['periode'] ?? null, $hospitalId);
    }

    private function fileName(?string $periodName, string $extension): string
    {
        $slug = $periodName === null ? 'semua-periode' : strtolower(preg_replace('/[^a-z0-9]+/i', '-', $periodName) ?? 'periode');

        return 'laporan-klaim-'.trim($slug, '-').'-'.now()->format('Ymd').'.'.$extension;
    }
}
