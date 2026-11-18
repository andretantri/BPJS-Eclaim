import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Claim } from '@/types';
import { ArrowRight, Search, ClipboardList } from 'lucide-react';

interface SelectPatientProps {
    claims: {
        data: Claim[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { q: string };
    selectedStep: string;
    selectedStepLabel: string;
}

const statusBadge = (status: Claim['status']): { text: string; className: string } => {
    switch (status) {
        case 'menunggu_verifikasi':
            return { text: 'Menunggu verifikasi', className: 'bg-amber-50 text-amber-700' };
        case 'bermasalah':
            return { text: 'Bermasalah', className: 'bg-rose-50 text-rose-700' };
        case 'siap_diajukan':
            return { text: 'Siap diajukan', className: 'bg-teal-50 text-teal-700' };
        case 'diajukan':
            return { text: 'Diajukan', className: 'bg-sky-50 text-sky-700' };
        case 'disetujui':
            return { text: 'Disetujui', className: 'bg-emerald-50 text-emerald-700' };
        case 'ditolak':
            return { text: 'Ditolak', className: 'bg-rose-50 text-rose-700' };
        default:
            return { text: 'Baru', className: 'bg-slate-100 text-slate-600' };
    }
};

const formatDate = (value: string): string => {
    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? '-'
        : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

export default function SelectPatient({
    claims,
    filters,
    selectedStep,
    selectedStepLabel,
}: SelectPatientProps) {
    const [search, setSearch] = useState(filters.q ?? '');

    return (
        <AuthenticatedLayout breadcrumbCurrent="Pilih Pasien Verifikasi">
            <Head title="Pilih Pasien Verifikasi - ClaimGuard" />

            <div className="space-y-6">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            ALUR VERIFIKASI 6 TAHAP
                        </div>
                        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Pilih pasien untuk diverifikasi</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Cari pasien lebih dulu, lalu klik Proses untuk membuka wizard tahap{' '}
                            <span className="font-semibold text-slate-700">{selectedStepLabel}</span>.
                        </p>
                    </div>
                    <div className="inline-flex items-center gap-2 self-start rounded-full border border-teal-200 bg-teal-50 px-3 py-1.5 text-[11px] font-semibold text-teal-800">
                        <ClipboardList className="h-3.5 w-3.5" />
                        Tahap: {selectedStepLabel}
                    </div>
                </div>

                <div className="bg-white rounded-xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col">
                    <form
                        method="get"
                        action={route('verifikasi.select', { step: selectedStep })}
                        className="p-3.5 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50"
                    >
                        <div className="relative flex-1 min-w-[240px]">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                name="q"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Cari nama pasien, no. RM, atau no. SEP..."
                                className="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-9 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-500"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg bg-teal-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-700"
                        >
                            Cari
                        </button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="border-b border-slate-100 text-[11px] font-medium uppercase text-slate-400">
                                    <th className="px-3 py-2.5 font-medium">PASIEN</th>
                                    <th className="px-3 py-2.5 font-medium">NO. SEP</th>
                                    <th className="px-3 py-2.5 font-medium">DIAGNOSIS</th>
                                    <th className="px-3 py-2.5 font-medium">TANGGAL MASUK</th>
                                    <th className="px-3 py-2.5 font-medium">STATUS</th>
                                    <th className="px-3 py-2.5 text-center font-medium">AKSI</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {claims.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-10 text-center text-slate-400">
                                            Tidak ada pasien yang cocok dengan pencarian.
                                        </td>
                                    </tr>
                                ) : (
                                    claims.data.map((claim) => {
                                        const badge = statusBadge(claim.status);

                                        return (
                                            <tr key={claim.id} className="transition hover:bg-slate-50">
                                                <td className="px-3 py-3">
                                                    <div className="font-semibold text-slate-800">{claim.patient?.nama_lengkap ?? '-'}</div>
                                                    <div className="font-mono text-[10px] text-slate-400">{claim.patient?.no_rm ?? '-'}</div>
                                                </td>
                                                <td className="px-3 py-3 font-mono text-slate-600">{claim.no_sep}</td>
                                                <td className="px-3 py-3 text-slate-700">{claim.diagnosa_primer}</td>
                                                <td className="px-3 py-3 text-slate-600">{formatDate(claim.tanggal_masuk)}</td>
                                                <td className="px-3 py-3">
                                                    <span className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${badge.className}`}>
                                                        {badge.text}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3 text-center">
                                                    <Link
                                                        href={route('verifikasi.show', { claim: claim.id, step: selectedStep })}
                                                        className="inline-flex items-center gap-1 rounded-lg bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-700 transition hover:bg-teal-100"
                                                    >
                                                        Proses
                                                        <ArrowRight className="h-3.5 w-3.5" />
                                                    </Link>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 bg-white p-3.5 text-xs text-slate-500">
                        <span>
                            Menampilkan {claims.from ?? 0}-{claims.to ?? 0} dari {claims.total} klaim
                        </span>
                        <div className="flex items-center gap-1">
                            {claims.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    preserveState
                                    className={`rounded px-2.5 py-1 text-xs transition ${
                                        link.active ? 'bg-teal-600 font-bold text-white' : 'text-slate-600 hover:bg-slate-100'
                                    } ${!link.url ? 'pointer-events-none opacity-40' : ''}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
