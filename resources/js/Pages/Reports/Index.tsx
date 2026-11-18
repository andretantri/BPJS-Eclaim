import { Head, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { ClaimPeriod } from '@/types';
import { FileSpreadsheet, FileText } from 'lucide-react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    Legend
} from 'recharts';

interface ReportsIndexProps {
    period: ClaimPeriod | null;
    selectedPeriodId: number | null;
    periods: ClaimPeriod[];
    claimsByStatus: Array<{ name: string; count: number; color: string }>;
    topDiagnoses: Array<{ diagnosa_primer: string; count: number; total_biaya: number }>;
    monthlyTrend: Array<{ month: string; totalKlaim: number; totalNilai: number; disetujui: number }>;
    summary: {
        totalKlaim: number;
        totalBiaya: number;
        totalDisetujui: number;
        rasioPersetujuan: string;
        rasioPersetujuanValue: number;
    };
}

export default function ReportsIndex({
    period,
    selectedPeriodId,
    periods,
    claimsByStatus,
    topDiagnoses,
    monthlyTrend,
    summary,
}: ReportsIndexProps) {
    const selectedPeriod = selectedPeriodId === null ? 'all' : String(selectedPeriodId);
    const exportQuery = { periode: selectedPeriod };
    const rasio = summary.rasioPersetujuanValue;
    const rasioKeterangan = rasio >= 90
        ? 'Sangat baik (Target > 90%)'
        : rasio >= 75
            ? 'Cukup baik (Target > 90%)'
            : 'Perlu perbaikan (Target > 90%)';

    return (
        <AuthenticatedLayout breadcrumbCurrent="Pelaporan Klaim">
            <Head title="Pelaporan & Analisis Casemix - ClaimGuard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            ANALISIS DAN PELAPORAN
                        </div>
                        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Pelaporan Klaim Casemix</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Evaluasi efektivitas verifikasi, tren diagnosa, dan rasio persetujuan klaim BPJS Kesehatan.
                        </p>
                    </div>

                    <div className="flex flex-col sm:flex-row sm:items-center gap-2">
                        <select
                            value={selectedPeriod}
                            onChange={(event) => router.get(route('laporan.index'), { periode: event.target.value }, {
                                preserveState: true,
                                preserveScroll: true,
                            })}
                            className="px-3 py-2.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 shadow-sm focus:border-teal-500 focus:ring-teal-500"
                            aria-label="Pilih periode laporan"
                        >
                            <option value="all">Semua periode</option>
                            {periods.map((item) => (
                                <option key={item.id} value={item.id}>{item.nama_periode}</option>
                            ))}
                        </select>

                        <a
                            href={route('laporan.export.excel', exportQuery)}
                            className="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold transition flex items-center justify-center gap-2 shadow-sm"
                        >
                            <FileSpreadsheet className="w-4 h-4" />
                            <span>Excel</span>
                        </a>

                        <a
                            href={route('laporan.export.pdf', exportQuery)}
                            className="px-4 py-2.5 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold transition flex items-center justify-center gap-2 shadow-sm"
                        >
                            <FileText className="w-4 h-4 text-rose-500" />
                            <span>PDF</span>
                        </a>
                    </div>
                </div>

                {/* 4 Summary Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <span className="text-xs text-slate-500 font-medium">Total Volume Klaim</span>
                        <div className="text-2xl font-bold text-slate-900 mt-1">{summary.totalKlaim.toLocaleString('id-ID')}</div>
                        <div className="mt-1 text-[11px] text-emerald-600">Periode {period?.nama_periode || 'Semua periode'}</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <span className="text-xs text-slate-500 font-medium">Estimasi Nilai Pengajuan</span>
                        <div className="text-2xl font-bold text-teal-700 mt-1">
                            Rp{(summary.totalBiaya / 1000000).toFixed(1)} Juta
                        </div>
                        <div className="mt-1 text-[11px] text-slate-400">Total tarif INA-CBGs</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <span className="text-xs text-slate-500 font-medium">Nilai Klaim Disetujui</span>
                        <div className="text-2xl font-bold text-emerald-600 mt-1">
                            Rp{(summary.totalDisetujui / 1000000).toFixed(1)} Juta
                        </div>
                        <div className="mt-1 text-[11px] text-emerald-600">Lolos verifikasi akhir</div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                        <span className="text-xs text-slate-500 font-medium">Rasio Persetujuan Klaim</span>
                        <div className="text-2xl font-bold text-slate-900 mt-1">{summary.rasioPersetujuan}</div>
                        <div className={`mt-1 text-[11px] ${rasio >= 90 ? 'text-emerald-600' : rasio >= 75 ? 'text-amber-600' : 'text-rose-600'}`}>
                            {rasioKeterangan}
                        </div>
                    </div>
                </div>

                {/* Charts Row */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Trend Bulanan */}
                    <div className="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                        <div>
                            <h3 className="font-bold text-slate-800 text-sm">Tren Nilai Klaim vs Realisasi Pembayaran</h3>
                            <p className="text-[11px] text-slate-400">Perbandingan per bulan (dalam Miliar Rupiah)</p>
                        </div>

                        <div className="h-56 w-full pt-2">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={monthlyTrend} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                    <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#64748b' }} axisLine={false} tickLine={false} />
                                    <YAxis tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} unit="M" />
                                    <Tooltip
                                        formatter={(val: number) => [`Rp${val} Miliar`, '']}
                                        contentStyle={{ backgroundColor: '#ffffff', borderRadius: '8px', border: '1px solid #e2e8f0', fontSize: '11px' }}
                                    />
                                    <Legend wrapperStyle={{ fontSize: '11px', paddingTop: '8px' }} />
                                    <Bar dataKey="totalNilai" fill="#0d9488" radius={[4, 4, 0, 0]} name="Nilai Pengajuan" barSize={18} />
                                    <Bar dataKey="disetujui" fill="#99f6e4" radius={[4, 4, 0, 0]} name="Realisasi Dibayar" barSize={18} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Distribusi Status */}
                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-sm space-y-4">
                        <h3 className="font-bold text-slate-800 text-sm">Distribusi Status Berkas</h3>
                        <div className="space-y-4 pt-2">
                            {claimsByStatus.map((item) => (
                                <div key={item.name} className="space-y-1 text-xs">
                                    <div className="flex justify-between font-medium">
                                        <span className="text-slate-700">{item.name}</span>
                                        <span className="font-bold">{item.count} klaim</span>
                                    </div>
                                    <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                                        <div
                                            className="h-full rounded-full transition-all duration-500"
                                            style={{
                                                backgroundColor: item.color,
                                                width: `${summary.totalKlaim > 0 ? (item.count / summary.totalKlaim) * 100 : 0}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Top Diagnoses Table */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <h3 className="font-bold text-slate-800 text-sm">Top Diagnosis Primer Terbanyak</h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="text-[11px] text-slate-400 uppercase border-b border-slate-100 font-medium">
                                    <th className="py-2.5 font-medium">KODE & DESKRIPSI DIAGNOSIS</th>
                                    <th className="py-2.5 font-medium text-center">FREKUENSI KASUS</th>
                                    <th className="py-2.5 font-medium text-right">TOTAL NILAI KLAIM</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {topDiagnoses.map((diag, idx) => (
                                    <tr key={idx} className="hover:bg-slate-50 transition">
                                        <td className="py-3 font-semibold text-slate-800">{diag.diagnosa_primer}</td>
                                        <td className="py-3 text-center">
                                            <span className="px-2 py-0.5 rounded-full text-xs font-bold bg-teal-50 text-teal-700">
                                                {diag.count} kasus
                                            </span>
                                        </td>
                                        <td className="py-3 text-right font-bold text-slate-900">
                                            Rp{Number(diag.total_biaya).toLocaleString('id-ID')}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
