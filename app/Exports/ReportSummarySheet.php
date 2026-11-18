<?php

namespace App\Exports;

use App\Models\ClaimPeriod;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportSummarySheet extends SafeReportSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle
{
    /**
     * @param  array{totalKlaim: int, totalBiaya: float, totalDisetujui: float, rasioPersetujuan: string, rasioPersetujuanValue: float}  $summary
     * @param  array<int, array{name: string, count: int, color: string}>  $claimsByStatus
     */
    public function __construct(
        private readonly array $summary,
        private readonly ?ClaimPeriod $period,
        private readonly array $claimsByStatus,
    ) {}

    public function collection(): Collection
    {
        $rows = collect([
            ['Periode', $this->period?->nama_periode ?? 'Semua periode'],
            ['Dibuat', now()->translatedFormat('d F Y H:i').' WIB'],
            ['Total klaim', $this->summary['totalKlaim']],
            ['Estimasi nilai pengajuan (Rp)', $this->summary['totalBiaya']],
            ['Nilai disetujui (Rp)', $this->summary['totalDisetujui']],
            ['Rasio persetujuan', $this->summary['rasioPersetujuan']],
        ]);

        foreach ($this->claimsByStatus as $status) {
            $rows->push(["Status: {$status['name']}", "{$status['count']} klaim"]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Metrik', 'Nilai'];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }
}
