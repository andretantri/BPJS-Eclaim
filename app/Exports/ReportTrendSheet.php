<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportTrendSheet extends SafeReportSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{month: string, totalKlaim: int, totalNilai: float, disetujui: float}>  $trend
     */
    public function __construct(private readonly array $trend) {}

    public function collection(): Collection
    {
        return collect($this->trend)->map(fn (array $month): array => [
            $month['month'],
            $month['totalKlaim'],
            $month['totalNilai'],
            $month['disetujui'],
        ]);
    }

    public function headings(): array
    {
        return ['Bulan', 'Total Klaim', 'Nilai Pengajuan (Miliar Rp)', 'Realisasi Dibayar (Miliar Rp)'];
    }

    public function title(): string
    {
        return 'Tren Bulanan';
    }
}
