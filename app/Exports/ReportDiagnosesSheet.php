<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportDiagnosesSheet extends SafeReportSheet implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{diagnosa_primer: string, count: int, total_biaya: float}>  $diagnoses
     */
    public function __construct(private readonly array $diagnoses) {}

    public function collection(): Collection
    {
        return collect($this->diagnoses)->map(fn (array $diagnosis): array => [
            $diagnosis['diagnosa_primer'],
            $diagnosis['count'],
            $diagnosis['total_biaya'],
        ]);
    }

    public function headings(): array
    {
        return ['Diagnosis Primer', 'Frekuensi Kasus', 'Total Nilai Klaim (Rp)'];
    }

    public function title(): string
    {
        return 'Top Diagnosa';
    }
}
