<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Klaim Casemix</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
        }

        h1 {
            font-size: 18px;
            margin: 0 0 4px;
        }

        h2 {
            font-size: 12px;
            margin: 18px 0 8px;
            color: #0f766e;
        }

        .muted {
            color: #64748b;
        }

        .header {
            border-bottom: 2px solid #0d9488;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .cards {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        .cards td {
            width: 25%;
            border: 1px solid #e2e8f0;
            padding: 10px;
            vertical-align: top;
        }

        .label {
            color: #64748b;
            font-size: 9px;
        }

        .value {
            font-size: 14px;
            font-weight: bold;
            margin-top: 4px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data th,
        table.data td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            text-align: left;
        }

        table.data th {
            background: #f0fdfa;
            color: #115e59;
            font-size: 9px;
            text-transform: uppercase;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $rupiah = fn (float $value): string => 'Rp '.number_format($value, 0, ',', '.');
    @endphp

    <div class="header">
        <h1>Laporan Klaim BPJS &amp; Casemix</h1>
        <div class="muted">
            {{ $hospital?->nama_rs ?? 'Rumah Sakit' }} ·
            Periode: {{ $report['period']?->nama_periode ?? 'Semua periode' }} ·
            Dibuat: {{ now()->translatedFormat('d F Y H:i') }} WIB
        </div>
    </div>

    <h2>Ringkasan Eksekutif</h2>
    <table class="cards">
        <tr>
            <td>
                <div class="label">Total Volume Klaim</div>
                <div class="value">{{ number_format($report['summary']['totalKlaim'], 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Estimasi Nilai Pengajuan</div>
                <div class="value">{{ $rupiah((float) $report['summary']['totalBiaya']) }}</div>
            </td>
            <td>
                <div class="label">Nilai Klaim Disetujui</div>
                <div class="value">{{ $rupiah((float) $report['summary']['totalDisetujui']) }}</div>
            </td>
            <td>
                <div class="label">Rasio Persetujuan</div>
                <div class="value">{{ $report['summary']['rasioPersetujuan'] }}</div>
            </td>
        </tr>
    </table>

    <h2>Distribusi Status Berkas</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Status</th>
                <th class="center">Jumlah Klaim</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['claimsByStatus'] as $status)
                <tr>
                    <td>{{ $status['name'] }}</td>
                    <td class="center">{{ $status['count'] }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="center">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Top 10 Diagnosis Primer</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Diagnosis</th>
                <th class="center">Frekuensi Kasus</th>
                <th class="right">Total Nilai Klaim</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['topDiagnoses'] as $diagnosis)
                <tr>
                    <td>{{ $diagnosis['diagnosa_primer'] }}</td>
                    <td class="center">{{ $diagnosis['count'] }}</td>
                    <td class="right">{{ $rupiah((float) $diagnosis['total_biaya']) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="center">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Tren 6 Bulan Terakhir</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Bulan</th>
                <th class="center">Total Klaim</th>
                <th class="right">Nilai Pengajuan</th>
                <th class="right">Realisasi Dibayar</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['monthlyTrend'] as $month)
                <tr>
                    <td>{{ $month['month'] }}</td>
                    <td class="center">{{ $month['totalKlaim'] }}</td>
                    <td class="right">{{ number_format($month['totalNilai'], 3, ',', '.') }} M</td>
                    <td class="right">{{ number_format($month['disetujui'], 3, ',', '.') }} M</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
