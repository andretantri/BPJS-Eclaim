<?php

namespace App\Exports;

use App\Models\Claim;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportClaimsSheet extends SafeReportSheet implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private readonly Builder $claims) {}

    public function query(): Builder
    {
        return $this->claims;
    }

    public function headings(): array
    {
        return [
            'Nomor Klaim',
            'Nama Pasien',
            'No RM',
            'Nomor SEP',
            'Tanggal Masuk',
            'Tanggal Pulang',
            'Jenis Layanan',
            'Diagnosis Primer',
            'Estimasi Biaya',
            'Biaya Disetujui',
            'Status',
        ];
    }

    public function map(mixed $row): array
    {
        /** @var Claim $row */
        return [
            $row->nomor_klaim,
            $row->patient?->nama_lengkap,
            $row->patient?->no_rm,
            $row->no_sep,
            $row->tanggal_masuk?->format('Y-m-d'),
            $row->tanggal_pulang?->format('Y-m-d'),
            $row->jenis_pelayanan?->label(),
            $row->diagnosa_primer,
            (float) $row->estimasi_biaya,
            (float) $row->biaya_disetujui,
            $row->status?->label(),
        ];
    }

    public function title(): string
    {
        return 'Klaim';
    }
}
