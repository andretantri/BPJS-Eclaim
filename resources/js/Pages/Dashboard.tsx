import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import IcdPicker, { IcdOption } from '@/Components/IcdPicker';
import { Claim, ActivityItem, ClaimPeriod } from '@/types';
import {
    FileText,
    Clock,
    AlertTriangle,
    CheckCircle2,
    Download,
    Plus,
    ArrowUpRight,
    ArrowRight
} from 'lucide-react';
import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    Legend
} from 'recharts';

interface DashboardProps {
    metrics: {
        total: number;
        menunggu: number;
        menungguPct: number;
        bermasalah: number;
        bermasalahPct: number;
        siap: number;
        siapPct: number;
        growthPct: number;
        totalRevenueMiliar: string;
        revenueGrowthPct: number;
    };
    revenueData: Array<{
        month: string;
        nilaiKlaim: number;
        dibayar: number;
    }>;
    priorityQueue: Claim[];
    recentActivities: ActivityItem[];
    period?: ClaimPeriod;
    icd10Codes: IcdOption[];
    icd9cmCodes: IcdOption[];
}

export default function Dashboard({
    metrics,
    revenueData,
    priorityQueue,
    recentActivities,
    period,
    icd10Codes,
    icd9cmCodes,
}: DashboardProps) {
    const firstRevenueMonth = revenueData[0]?.month;
    const lastRevenueMonth = revenueData[revenueData.length - 1]?.month;
    const previousRevenueMonth = revenueData[revenueData.length - 2]?.month;
    const revenueRange = firstRevenueMonth && lastRevenueMonth
        ? `${firstRevenueMonth}–${lastRevenueMonth}`
        : 'Belum ada data bulanan';
    const formatActivityTime = (timestamp: string): string => {
        const date = new Date(timestamp);
        return Number.isNaN(date.getTime())
            ? '-'
            : new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' }).format(date);
    };

    const [addModalOpen, setAddModalOpen] = useState(false);
    const [formData, setFormData] = useState({
        patient_name: '',
        rm_no: '',
        gender: 'Perempuan',
        age: 45,
        kis_no: '',
        sep_no: '',
        care_type: '',
        admission_date: '',
        discharge_date: '',
        dpjp: '',
        primary_diagnosis_code: '',
        secondary_diagnosis_codes: [] as string[],
        procedure_codes: [] as string[],
        estimated_cost: 0,
        initial_status: 'menunggu_verifikasi',
        issue_note: '',
    });

    const handleCreateClaim = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(route('klaim.store'), formData, {
            onSuccess: () => {
                setAddModalOpen(false);
            },
        });
    };

    return (
        <AuthenticatedLayout breadcrumbCurrent="Dashboard">
            <Head title="Dashboard Casemix" />

            <div className="space-y-6">
                {/* Page Title & Quick Actions */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            MANAJEMEN KLAIM BPJS
                        </div>
                        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">
                            Dashboard casemix
                        </h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Pantau kesiapan klaim dan potensi pendapatan rumah sakit dalam satu ruang kerja.
                        </p>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <a
                            href={route('dashboard.summary.download')}
                            className="px-3.5 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-xs font-medium hover:bg-slate-50 transition flex items-center gap-1.5 shadow-sm"
                        >
                            <Download className="w-4 h-4 text-slate-500" />
                            <span>Unduh ringkasan</span>
                        </a>
                        <button
                            type="button"
                            onClick={() => setAddModalOpen(true)}
                            className="px-3.5 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-medium transition flex items-center gap-1.5 shadow-sm"
                        >
                            <Plus className="w-4 h-4" />
                            <span>+ Tambah Klaim Baru</span>
                        </button>
                    </div>
                </div>

                {/* Period info bar */}
                <div className="flex items-center justify-between text-xs text-slate-500 py-1 border-b border-slate-200/80">
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-slate-700">{period?.nama_periode ?? 'Belum ada periode aktif'}</span>
                        <span className="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500" />
                        <span className="text-emerald-700 text-[11px] font-medium bg-emerald-50 px-2 py-0.5 rounded">
                            Periode berjalan
                        </span>
                    </div>
                    <div>Diperbarui hari ini · Sistem ClaimGuard</div>
                </div>

                {/* 4 Top Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    {/* Total Klaim */}
                    <div className="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm relative overflow-hidden group hover:border-slate-300 transition">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-medium text-slate-500">Total klaim</span>
                            <div className="p-1.5 rounded-lg bg-teal-50 text-teal-600">
                                <FileText className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline gap-1.5">
                            <span className="text-2xl font-bold text-slate-900">{metrics.total.toLocaleString('id-ID')}</span>
                            <span className="text-xs text-slate-400">klaim</span>
                        </div>
                        <div className="mt-2 text-[11px] text-emerald-600 flex items-center gap-1 font-medium">
                            <span>↑ {metrics.growthPct}%</span>
                            <span className="text-slate-400 font-normal">dari periode sebelumnya</span>
                        </div>
                    </div>

                    {/* Menunggu Verifikasi */}
                    <div className="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm relative overflow-hidden group hover:border-slate-300 transition">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-medium text-slate-500">Menunggu verifikasi</span>
                            <div className="p-1.5 rounded-lg bg-sky-50 text-sky-600">
                                <Clock className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline gap-1.5">
                            <span className="text-2xl font-bold text-slate-900">{metrics.menunggu.toLocaleString('id-ID')}</span>
                            <span className="text-xs text-slate-400">klaim</span>
                        </div>
                        <div className="mt-2 text-[11px] text-slate-500 font-medium">
                            <span>{metrics.menungguPct}%</span> dari seluruh klaim
                        </div>
                    </div>

                    {/* Klaim Bermasalah */}
                    <Link
                        href={route('klaim.index', { status: 'bermasalah' })}
                        className="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm relative overflow-hidden group hover:border-amber-300 transition block"
                    >
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-medium text-slate-500">Klaim bermasalah</span>
                            <div className="p-1.5 rounded-lg bg-amber-50 text-amber-600">
                                <AlertTriangle className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline gap-1.5">
                            <span className="text-2xl font-bold text-amber-600">{metrics.bermasalah.toLocaleString('id-ID')}</span>
                            <span className="text-xs text-slate-400">klaim</span>
                        </div>
                        <div className="mt-2 text-[11px] text-amber-600 font-medium flex items-center justify-between">
                            <span>Perlu tindak lanjut tim casemix</span>
                            <span className="text-xs">→</span>
                        </div>
                    </Link>

                    {/* Siap Diajukan */}
                    <Link
                        href={route('klaim.index', { status: 'siap_diajukan' })}
                        className="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm relative overflow-hidden group hover:border-emerald-300 transition block"
                    >
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs font-medium text-slate-500">Siap diajukan</span>
                            <div className="p-1.5 rounded-lg bg-emerald-50 text-emerald-600">
                                <CheckCircle2 className="w-4 h-4" />
                            </div>
                        </div>
                        <div className="flex items-baseline gap-1.5">
                            <span className="text-2xl font-bold text-emerald-600">{metrics.siap.toLocaleString('id-ID')}</span>
                            <span className="text-xs text-slate-400">klaim</span>
                        </div>
                        <div className="mt-2 text-[11px] text-emerald-600 font-medium">
                            <span>{metrics.siapPct}%</span> telah memenuhi validasi
                        </div>
                    </Link>
                </div>

                {/* Middle Charts & Distribution Section */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Estimasi Nilai Klaim & Pendapatan Chart */}
                    <div className="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm">
                        <div className="flex items-start justify-between mb-4">
                            <div>
                                <h3 className="font-bold text-slate-800 text-sm">Estimasi nilai klaim & pendapatan</h3>
                                <p className="text-[11px] text-slate-400">{revenueRange} · Nilai dalam miliar rupiah</p>
                                <div className="mt-2 flex items-baseline gap-2">
                                    <span className="text-2xl font-extrabold text-slate-900">Rp{metrics.totalRevenueMiliar} miliar</span>
                                    <span className="text-[11px] text-emerald-600 font-medium">
                                        {previousRevenueMonth && lastRevenueMonth
                                            ? `↑ ${metrics.revenueGrowthPct}% dibanding ${previousRevenueMonth} · ${lastRevenueMonth}`
                                            : 'Belum ada perbandingan bulanan'}
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-center gap-3 text-xs text-slate-500">
                                <div className="flex items-center gap-1.5">
                                    <span className="w-2.5 h-2.5 rounded-sm bg-teal-600" />
                                    <span className="text-[11px]">Nilai klaim</span>
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <span className="w-2.5 h-2.5 rounded-sm bg-teal-200" />
                                    <span className="text-[11px]">Dibayar</span>
                                </div>
                            </div>
                        </div>

                        {/* Responsive Recharts Bar Chart */}
                        <div className="h-52 w-full pt-2">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={revenueData} margin={{ top: 10, right: 10, left: -20, bottom: 0 }}>
                                    <XAxis dataKey="month" tick={{ fontSize: 11, fill: '#64748b' }} axisLine={false} tickLine={false} />
                                    <YAxis tick={{ fontSize: 10, fill: '#94a3b8' }} axisLine={false} tickLine={false} unit="M" />
                                    <Tooltip
                                        formatter={(val: number) => [`Rp${val} Miliar`, '']}
                                        contentStyle={{ backgroundColor: '#ffffff', borderRadius: '8px', border: '1px solid #e2e8f0', fontSize: '11px' }}
                                    />
                                    <Bar dataKey="nilaiKlaim" fill="#0d9488" radius={[4, 4, 0, 0]} name="Nilai klaim" barSize={18} />
                                    <Bar dataKey="dibayar" fill="#99f6e4" radius={[4, 4, 0, 0]} name="Dibayar" barSize={18} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    </div>

                    {/* Kesiapan Pengajuan Card */}
                    <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm flex flex-col justify-between">
                        <div>
                            <h3 className="font-bold text-slate-800 text-sm">Kesiapan pengajuan</h3>
                            <p className="text-[11px] text-slate-400">Distribusi {metrics.total.toLocaleString('id-ID')} klaim · {period?.nama_periode ?? 'tanpa periode aktif'}</p>

                            <div className="mt-4 flex items-baseline gap-2">
                                <span className="text-3xl font-extrabold text-slate-900">{metrics.siapPct}%</span>
                                <span className="text-xs text-slate-500 font-medium">siap diajukan</span>
                            </div>

                            {/* Multi-segment Progress Bar */}
                            <div className="w-full h-3 bg-slate-100 rounded-full overflow-hidden mt-3 flex">
                                <div
                                    className="bg-teal-500 h-full transition-all duration-500"
                                    style={{ width: `${metrics.siapPct}%` }}
                                    title={`Siap: ${metrics.siapPct}%`}
                                />
                                <div
                                    className="bg-sky-400 h-full transition-all duration-500"
                                    style={{ width: `${metrics.menungguPct}%` }}
                                    title={`Menunggu: ${metrics.menungguPct}%`}
                                />
                                <div
                                    className="bg-amber-400 h-full transition-all duration-500"
                                    style={{ width: `${metrics.bermasalahPct}%` }}
                                    title={`Bermasalah: ${metrics.bermasalahPct}%`}
                                />
                            </div>

                            {/* Breakdown list */}
                            <div className="mt-5 space-y-3">
                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-teal-500" />
                                        <span className="text-slate-600">Siap diajukan</span>
                                    </div>
                                    <div className="font-medium text-slate-800">
                                        {metrics.siap.toLocaleString('id-ID')} <span className="text-slate-400 font-normal">({metrics.siapPct}%)</span>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-sky-400" />
                                        <span className="text-slate-600">Menunggu verifikasi</span>
                                    </div>
                                    <div className="font-medium text-slate-800">
                                        {metrics.menunggu.toLocaleString('id-ID')} <span className="text-slate-400 font-normal">({metrics.menungguPct}%)</span>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between text-xs">
                                    <div className="flex items-center gap-2">
                                        <span className="w-2.5 h-2.5 rounded-full bg-amber-400" />
                                        <span className="text-slate-600">Bermasalah</span>
                                    </div>
                                    <div className="font-medium text-slate-800">
                                        {metrics.bermasalah.toLocaleString('id-ID')} <span className="text-slate-400 font-normal">({metrics.bermasalahPct}%)</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Priority Notice */}
                        <div className="mt-4 p-3 bg-amber-50/70 border border-amber-200/60 rounded-lg text-[11px] text-amber-800 flex items-start gap-2">
                            <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                            <span>Prioritaskan <strong className="font-semibold">{metrics.bermasalah} klaim bermasalah</strong> sebelum penutupan periode.</span>
                        </div>
                    </div>
                </div>

                {/* Bottom Grid: Antrean Prioritas & Aktivitas Terbaru */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Antrean Prioritas Table */}
                    <div className="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm">
                        <div className="flex items-center justify-between mb-3">
                            <div>
                                <h3 className="font-bold text-slate-800 text-sm">Antrean prioritas</h3>
                                <p className="text-[11px] text-slate-400">Klaim yang memerlukan perhatian petugas</p>
                            </div>
                            <Link href={route('klaim.index')} className="text-xs text-teal-600 font-medium hover:text-teal-700 flex items-center gap-1 transition">
                                <span>Lihat seluruh klaim</span>
                                <ArrowRight className="w-3.5 h-3.5" />
                            </Link>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="text-[11px] text-slate-400 uppercase border-b border-slate-100 font-medium">
                                        <th className="py-2.5 font-medium">PASIEN / NO. RM</th>
                                        <th className="py-2.5 font-medium">TEMUAN UTAMA</th>
                                        <th className="py-2.5 font-medium">STATUS</th>
                                        <th className="py-2.5 font-medium text-right">ESTIMASI</th>
                                        <th className="py-2.5 font-medium text-center">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {priorityQueue.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50 transition">
                                            <td className="py-3">
                                                <div className="font-semibold text-slate-800">{item.patient?.nama_lengkap}</div>
                                                <div className="text-[10px] text-slate-400 font-mono">{item.patient?.no_rm}</div>
                                            </td>
                                            <td className="py-3 text-slate-600">{item.issue || '-'}</td>
                                            <td className="py-3">
                                                <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium ${
                                                    item.status === 'bermasalah' ? 'bg-amber-50 text-amber-700' : 'bg-sky-50 text-sky-700'
                                                }`}>
                                                    <span className={`w-1.5 h-1.5 rounded-full ${item.status === 'bermasalah' ? 'bg-amber-500' : 'bg-sky-500'}`} />
                                                    {item.status}
                                                </span>
                                            </td>
                                            <td className="py-3 text-right font-medium text-slate-800">
                                                Rp{Number(item.estimasi_biaya).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-3 text-center">
                                                <Link
                                                    href={route('verifikasi.show', { claim: item.id, step: 'v-claim' })}
                                                    className="text-teal-600 hover:text-teal-800 font-medium"
                                                >
                                                    Periksa →
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Aktivitas Terbaru */}
                    <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm">
                        <h3 className="font-bold text-slate-800 text-sm">Aktivitas terbaru</h3>
                        <p className="text-[11px] text-slate-400 mb-4">Jejak kerja tim casemix hari ini</p>

                        <div className="space-y-4 relative before:absolute before:left-3 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-100">
                            {recentActivities.map((log) => (
                                <div key={log.id} className="relative flex items-start gap-3 pl-6">
                                    <span className="absolute left-1.5 top-1.5 w-3 h-3 rounded-full bg-emerald-100 border-2 border-emerald-500" />
                                    <div className="flex-1">
                                        <div className="flex items-center justify-between text-[11px]">
                                            <span className="font-semibold text-slate-800">{log.action}</span>
                                            <span className="text-slate-400">{formatActivityTime(log.created_at)}</span>
                                        </div>
                                        <div className="text-[11px] text-slate-500 mt-0.5">{log.description}</div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {/* ADD CLAIM MODAL */}
            {addModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-fadeIn">
                    <div className="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 overflow-y-auto max-h-[90vh]">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Tambah Data Klaim Baru</h3>
                                <p className="text-xs text-slate-500">Daftarkan episode perawatan pasien ke sistem ClaimGuard.</p>
                            </div>
                            <button type="button" onClick={() => setAddModalOpen(false)} className="text-slate-400 hover:text-slate-600">
                                ✕
                            </button>
                        </div>

                        <form onSubmit={handleCreateClaim} className="space-y-4 text-xs">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Nama Pasien</label>
                                    <input
                                        type="text"
                                        required
                                        value={formData.patient_name}
                                        onChange={(e) => setFormData({ ...formData, patient_name: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                        placeholder="Nama lengkap pasien"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">No. Rekam Medis (RM)</label>
                                    <input
                                        type="text"
                                        required
                                        value={formData.rm_no}
                                        onChange={(e) => setFormData({ ...formData, rm_no: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Jenis Kelamin</label>
                                    <select
                                        value={formData.gender}
                                        onChange={(e) => setFormData({ ...formData, gender: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    >
                                        <option value="Perempuan">Perempuan</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Usia</label>
                                    <input
                                        type="number"
                                        value={formData.age}
                                        onChange={(e) => setFormData({ ...formData, age: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Nomor SEP</label>
                                    <input
                                        type="text"
                                        required
                                        value={formData.sep_no}
                                        onChange={(e) => setFormData({ ...formData, sep_no: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Jenis Layanan</label>
                                    <input
                                        type="text"
                                        value={formData.care_type}
                                        onChange={(e) => setFormData({ ...formData, care_type: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                            </div>

                            <div className="space-y-3">
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Diagnosis Primer (ICD-10) <span className="text-rose-600">*</span></label>
                                    <IcdPicker
                                        type="icd10"
                                        value={formData.primary_diagnosis_code ? [formData.primary_diagnosis_code] : []}
                                        onChange={(codes) => setFormData({ ...formData, primary_diagnosis_code: codes[0] ?? '' })}
                                        initialOptions={icd10Codes}
                                    />
                                </div>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label className="block text-slate-600 font-medium mb-1">Diagnosis Sekunder (ICD-10)</label>
                                        <IcdPicker
                                            type="icd10"
                                            multiple
                                            max={5}
                                            value={formData.secondary_diagnosis_codes}
                                            onChange={(codes) => setFormData({ ...formData, secondary_diagnosis_codes: codes })}
                                            initialOptions={icd10Codes}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-slate-600 font-medium mb-1">Prosedur (ICD-9-CM)</label>
                                        <IcdPicker
                                            type="icd9cm"
                                            multiple
                                            max={10}
                                            value={formData.procedure_codes}
                                            onChange={(codes) => setFormData({ ...formData, procedure_codes: codes })}
                                            initialOptions={icd9cmCodes}
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Estimasi Tarif (Rp)</label>
                                    <input
                                        type="number"
                                        value={formData.estimated_cost}
                                        onChange={(e) => setFormData({ ...formData, estimated_cost: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => setAddModalOpen(false)}
                                    className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    disabled={!formData.primary_diagnosis_code}
                                    className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold shadow-sm disabled:opacity-50"
                                >
                                    Simpan Klaim
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
