<?php

namespace App\Http\Controllers;

use App\Enums\ClaimStatus;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\ClaimPeriod;
use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Claim::class);

        $hospitalId = $request->user()?->hospital_id;
        $period = ClaimPeriod::where('hospital_id', $hospitalId)->where('status', 'aktif')->first();
        $counts = $this->statusCounts($hospitalId);
        $revenueData = $this->monthlyRevenue($hospitalId);

        $total = (int) ($counts->all_count ?? 0);
        $percentage = fn (int $count): float => $total > 0 ? round(($count / $total) * 100, 1) : 0.0;

        $metrics = [
            'total' => $total,
            'menunggu' => (int) ($counts->menunggu_count ?? 0),
            'menungguPct' => $percentage((int) ($counts->menunggu_count ?? 0)),
            'bermasalah' => (int) ($counts->bermasalah_count ?? 0),
            'bermasalahPct' => $percentage((int) ($counts->bermasalah_count ?? 0)),
            'siap' => (int) ($counts->siap_count ?? 0),
            'siapPct' => $percentage((int) ($counts->siap_count ?? 0)),
            'growthPct' => $this->monthOverMonthGrowth($revenueData, 'nilaiKlaim'),
            'totalRevenueMiliar' => number_format((float) Claim::forHospital($hospitalId)->sum('estimasi_biaya') / 1_000_000_000, 2, ',', '.'),
            'revenueGrowthPct' => $this->monthOverMonthGrowth($revenueData, 'dibayar'),
        ];

        $priorityQueue = Claim::with('patient')
            ->forHospital($hospitalId)
            ->whereIn('status', [ClaimStatus::Bermasalah->value, ClaimStatus::MenungguVerifikasi->value])
            ->orderBy('id')
            ->take(5)
            ->get();

        $recentActivities = ActivityLog::with('user')
            ->whereHas('user', fn ($query) => $query->where('hospital_id', $hospitalId))
            ->orderByDesc('created_at')
            ->take(6)
            ->get();

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'revenueData' => $revenueData,
            'priorityQueue' => $priorityQueue,
            'recentActivities' => $recentActivities,
            'period' => $period,
            'icd10Codes' => Icd10Code::query()
                ->where('is_active', true)
                ->orderBy('kode')
                ->get(['id', 'kode', 'deskripsi_id', 'kategori']),
            'icd9cmCodes' => Icd9cmCode::query()
                ->where('is_active', true)
                ->orderBy('kode')
                ->get(['id', 'kode', 'deskripsi_id', 'kategori']),
        ]);
    }

    public function downloadSummary(Request $request): SymfonyResponse
    {
        Gate::authorize('export', Claim::class);

        $hospital = $request->user()?->hospital;
        $hospitalId = $hospital?->id;
        $counts = $this->statusCounts($hospitalId);
        $totalRevenue = (float) Claim::forHospital($hospitalId)->sum('estimasi_biaya');

        $content = "LAPORAN RINGKASAN KLAIM BPJS & CASEMIX\n";
        if ($hospital) {
            $content .= $hospital->nama_rs.($hospital->kota ? ' · '.$hospital->kota : '')."\n";
        }
        $content .= "Unit Casemix & Klaim BPJS\n";
        $content .= 'Tanggal: '.now()->format('d M Y H:i')." WIB\n";
        $content .= 'Periode: '.now()->translatedFormat('F Y')."\n";
        $content .= "----------------------------------------\n";
        $content .= 'Total Klaim: '.($counts->all_count ?? 0)."\n";
        $content .= 'Siap Diajukan: '.($counts->siap_count ?? 0)."\n";
        $content .= 'Menunggu Verifikasi: '.($counts->menunggu_count ?? 0)."\n";
        $content .= 'Klaim Bermasalah: '.($counts->bermasalah_count ?? 0)."\n";
        $content .= 'Estimasi Pendapatan: Rp '.number_format($totalRevenue, 0, ',', '.')."\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Ringkasan_Casemix_'.now()->format('Ym').'.txt"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function statusCounts(?int $hospitalId): object
    {
        return Claim::forHospital($hospitalId)
            ->selectRaw(
                'COUNT(*) as all_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as menunggu_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as bermasalah_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as siap_count',
                [
                    ClaimStatus::MenungguVerifikasi->value,
                    ClaimStatus::Bermasalah->value,
                    ClaimStatus::SiapDiajukan->value,
                ],
            )
            ->first();
    }

    /**
     * @return array<int, array{month: string, nilaiKlaim: float, dibayar: float}>
     */
    private function monthlyRevenue(?int $hospitalId): array
    {
        $claims = Claim::forHospital($hospitalId)
            ->whereNotNull('tanggal_masuk')
            ->orderBy('tanggal_masuk')
            ->get(['tanggal_masuk', 'estimasi_biaya', 'biaya_disetujui']);

        $months = collect(range(5, 0))->map(
            fn (int $offset) => now()->startOfMonth()->subMonths($offset),
        );

        return $months->map(function ($month) use ($claims) {
            $monthClaims = $claims->filter(
                fn (Claim $claim) => $claim->tanggal_masuk?->month === $month->month
                    && $claim->tanggal_masuk?->year === $month->year,
            );

            return [
                'month' => $month->translatedFormat('M'),
                'nilaiKlaim' => round((float) $monthClaims->sum('estimasi_biaya') / 1_000_000_000, 3),
                'dibayar' => round((float) $monthClaims->sum('biaya_disetujui') / 1_000_000_000, 3),
            ];
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $series
     */
    private function monthOverMonthGrowth(array $series, string $key): float
    {
        $count = count($series);
        if ($count < 2) {
            return 0.0;
        }

        $previous = (float) ($series[$count - 2][$key] ?? 0);
        $current = (float) ($series[$count - 1][$key] ?? 0);

        if ($previous === 0.0) {
            return 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
