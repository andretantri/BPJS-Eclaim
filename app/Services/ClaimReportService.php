<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\ClaimPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ClaimReportService
{
    /**
     * @return Collection<int, ClaimPeriod>
     */
    public function periods(int $hospitalId): Collection
    {
        return ClaimPeriod::query()
            ->where('hospital_id', $hospitalId)
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->orderByDesc('id')
            ->get(['id', 'nama_periode', 'bulan', 'tahun', 'status']);
    }

    public function activePeriod(int $hospitalId): ?ClaimPeriod
    {
        return ClaimPeriod::query()
            ->where('hospital_id', $hospitalId)
            ->where('status', 'aktif')
            ->orderByDesc('id')
            ->first(['id', 'nama_periode', 'bulan', 'tahun', 'status']);
    }

    public function resolvePeriodId(?string $requestedPeriod, int $hospitalId): ?int
    {
        if ($requestedPeriod === null || $requestedPeriod === '') {
            return $this->activePeriod($hospitalId)?->id;
        }

        if ($requestedPeriod === 'all') {
            return null;
        }

        if (ctype_digit($requestedPeriod) === false) {
            abort(404, 'Periode laporan tidak ditemukan.');
        }

        $periodId = (int) $requestedPeriod;
        $exists = ClaimPeriod::query()
            ->where('hospital_id', $hospitalId)
            ->whereKey($periodId)
            ->exists();

        if (! $exists) {
            abort(404, 'Periode laporan tidak ditemukan.');
        }

        return $periodId;
    }

    /**
     * @return array{
     *     period: ClaimPeriod|null,
     *     selectedPeriodId: int|null,
     *     periods: Collection<int, ClaimPeriod>,
     *     claimsByStatus: array<int, array{name: string, count: int, color: string}>,
     *     topDiagnoses: array<int, array{diagnosa_primer: string, count: int, total_biaya: float}>,
     *     monthlyTrend: array<int, array{month: string, totalKlaim: int, totalNilai: float, disetujui: float}>,
     *     summary: array{totalKlaim: int, totalBiaya: float, totalDisetujui: float, rasioPersetujuan: string, rasioPersetujuanValue: float}
     * }
     */
    public function build(int $hospitalId, ?int $periodId): array
    {
        $baseQuery = $this->claimsQuery($hospitalId, $periodId);

        $totals = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_klaim, COALESCE(SUM(estimasi_biaya), 0) as total_biaya, COALESCE(SUM(biaya_disetujui), 0) as total_disetujui')
            ->first();

        $totalKlaim = (int) ($totals?->total_klaim ?? 0);
        $totalBiaya = (float) ($totals?->total_biaya ?? 0);
        $totalDisetujui = (float) ($totals?->total_disetujui ?? 0);
        $rasioPersetujuan = $totalBiaya > 0 ? round(($totalDisetujui / $totalBiaya) * 100, 1) : 0.0;

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status as status_value, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status_value');

        $claimsByStatus = collect([
            [ClaimStatus::SiapDiajukan, '#14b8a6'],
            [ClaimStatus::MenungguVerifikasi, '#38bdf8'],
            [ClaimStatus::Bermasalah, '#fbbf24'],
        ])->map(fn (array $item): array => [
            'name' => $item[0]->label(),
            'count' => (int) ($statusCounts[$item[0]->value] ?? 0),
            'color' => $item[1],
        ])->all();

        $topDiagnoses = (clone $baseQuery)
            ->whereNotNull('diagnosa_primer')
            ->where('diagnosa_primer', '<>', '')
            ->selectRaw('diagnosa_primer, COUNT(*) as count, COALESCE(SUM(estimasi_biaya), 0) as total_biaya')
            ->groupBy('diagnosa_primer')
            ->orderByDesc('count')
            ->orderByDesc('total_biaya')
            ->limit(10)
            ->get()
            ->map(fn (Claim $claim): array => [
                'diagnosa_primer' => (string) $claim->diagnosa_primer,
                'count' => (int) $claim->count,
                'total_biaya' => (float) $claim->total_biaya,
            ])
            ->all();

        return [
            'period' => $periodId === null
                ? null
                : ClaimPeriod::query()->where('hospital_id', $hospitalId)->find($periodId),
            'selectedPeriodId' => $periodId,
            'periods' => $this->periods($hospitalId),
            'claimsByStatus' => $claimsByStatus,
            'topDiagnoses' => $topDiagnoses,
            'monthlyTrend' => $this->monthlyTrend($baseQuery),
            'summary' => [
                'totalKlaim' => $totalKlaim,
                'totalBiaya' => $totalBiaya,
                'totalDisetujui' => $totalDisetujui,
                'rasioPersetujuan' => number_format($rasioPersetujuan, 1, '.', '').'%',
                'rasioPersetujuanValue' => $rasioPersetujuan,
            ],
        ];
    }

    /**
     * @return Builder<Claim>
     */
    public function claimsQuery(int $hospitalId, ?int $periodId): Builder
    {
        return Claim::query()
            ->forHospital($hospitalId)
            ->when($periodId !== null, fn (Builder $query) => $query->where('period_id', $periodId));
    }

    /**
     * @return Builder<Claim>
     */
    public function exportClaimsQuery(int $hospitalId, ?int $periodId): Builder
    {
        return $this->claimsQuery($hospitalId, $periodId)
            ->with('patient')
            ->orderBy('claims.id');
    }

    /**
     * @param  Builder<Claim>  $baseQuery
     * @return array<int, array{month: string, totalKlaim: int, totalNilai: float, disetujui: float}>
     */
    private function monthlyTrend(Builder $baseQuery): array
    {
        $start = now()->startOfMonth()->subMonths(5)->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $rows = (clone $baseQuery)
            ->whereNotNull('tanggal_masuk')
            ->whereBetween('tanggal_masuk', [$start, $end])
            ->selectRaw('tanggal_masuk, COUNT(*) as total_klaim, COALESCE(SUM(estimasi_biaya), 0) as total_nilai, COALESCE(SUM(biaya_disetujui), 0) as disetujui')
            ->groupBy('tanggal_masuk')
            ->get()
            ->keyBy(fn (Claim $claim) => $claim->tanggal_masuk?->toDateString());

        return collect(range(5, 0))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset))
            ->map(function ($month) use ($rows): array {
                $row = $rows->get($month->toDateString());

                return [
                    'month' => $month->translatedFormat('M'),
                    'totalKlaim' => (int) ($row?->total_klaim ?? 0),
                    'totalNilai' => round((float) ($row?->total_nilai ?? 0) / 1_000_000_000, 3),
                    'disetujui' => round((float) ($row?->disetujui ?? 0) / 1_000_000_000, 3),
                ];
            })
            ->all();
    }
}
