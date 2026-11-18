import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import IcdPicker, { IcdOption } from '@/Components/IcdPicker';
import { Claim, PageProps } from '@/types';
import {
    Search,
    Download,
    Plus,
    Eye,
    Edit2,
    Trash2,
    Calendar,
    Filter,
    ArrowRight,
    AlertTriangle,
    Save
} from 'lucide-react';

const codeFromLabel = (label?: string | null): string => label?.trim().split(/\s+/)[0] ?? '';

const yearFromDate = (value: string): string => {
    const year = new Date(value).getFullYear();
    return Number.isNaN(year) ? '-' : String(year);
};

const codesFromLabel = (label?: string | null): string[] =>
    (label ?? '')
        .split(',')
        .map((item) => codeFromLabel(item))
        .filter(Boolean);

interface ClaimsIndexProps {
    claims: {
        data: Claim[];
        current_page: number;
        last_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    selectedClaim: Claim | null;
    filters: {
        status: string;
        search: string;
    };
    counts: {
        all: number;
        menunggu: number;
        bermasalah: number;
        siap: number;
    };
    icd10Codes: IcdOption[];
    icd9cmCodes: IcdOption[];
}

export default function ClaimsIndex({
    claims,
    selectedClaim: initialSelectedClaim,
    filters,
    counts,
    icd10Codes,
    icd9cmCodes,
}: ClaimsIndexProps) {
    const { currentPeriod } = usePage<PageProps>().props;
    const [selectedClaim, setSelectedClaim] = useState<Claim | null>(initialSelectedClaim);
    const [search, setSearch] = useState(filters.search || '');
    const [editModalOpen, setEditModalOpen] = useState(false);
    const [addModalOpen, setAddModalOpen] = useState(false);
    const [selectedStatus, setSelectedStatus] = useState<string>(selectedClaim?.status || 'menunggu_verifikasi');

    // Add Claim form state
    const [addFormData, setAddFormData] = useState({
        patient_name: '',
        rm_no: '',
        gender: 'Perempuan',
        age: 40,
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

    // Edit form state
    const [editFormData, setEditFormData] = useState({
        patient_name: selectedClaim?.patient?.nama_lengkap || '',
        rm_no: selectedClaim?.patient?.no_rm || '',
        gender: selectedClaim?.patient?.jenis_kelamin || 'Perempuan',
        age: selectedClaim?.patient?.usia || 40,
        kis_no: selectedClaim?.patient?.no_kis || '',
        sep_no: selectedClaim?.no_sep || '',
        care_type: selectedClaim?.care_type || '',
        admission_date: selectedClaim?.tanggal_masuk || '',
        discharge_date: selectedClaim?.tanggal_pulang || '',
        dpjp: selectedClaim?.dpjp_nama || '',
        primary_diagnosis_code: selectedClaim?.diagnoses?.find((item) => item.tipe === 'primer')?.kode_icd
            ?? codeFromLabel(selectedClaim?.diagnosa_primer),
        secondary_diagnosis_codes: selectedClaim?.diagnoses
            ?.filter((item) => item.tipe === 'sekunder')
            .map((item) => item.kode_icd)
            ?? codesFromLabel(selectedClaim?.diagnosa_sekunder === '-' ? '' : selectedClaim?.diagnosa_sekunder),
        procedure_codes: selectedClaim?.procedures?.map((item) => item.kode_prosedur)
            ?? codesFromLabel(selectedClaim?.prosedur_utama === '-' ? '' : selectedClaim?.prosedur_utama),
        estimated_cost: selectedClaim?.estimasi_biaya || 0,
        status: selectedClaim?.status || 'menunggu_verifikasi',
        issue_note: selectedClaim?.issue || '',
    });

    const openEdit = (claim: Claim) => {
        setSelectedClaim(claim);
        setEditFormData({
            patient_name: claim.patient?.nama_lengkap || '',
            rm_no: claim.patient?.no_rm || '',
            gender: claim.patient?.jenis_kelamin || 'Perempuan',
            age: claim.patient?.usia || 40,
            kis_no: claim.patient?.no_kis || '',
            sep_no: claim.no_sep || '',
            care_type: claim.care_type || '',
            admission_date: claim.tanggal_masuk || '',
            discharge_date: claim.tanggal_pulang || '',
            dpjp: claim.dpjp_nama || '',
            primary_diagnosis_code: claim.diagnoses?.find((item) => item.tipe === 'primer')?.kode_icd
                ?? codeFromLabel(claim.diagnosa_primer),
            secondary_diagnosis_codes: claim.diagnoses
                ?.filter((item) => item.tipe === 'sekunder')
                .map((item) => item.kode_icd)
                ?? codesFromLabel(claim.diagnosa_sekunder === '-' ? '' : claim.diagnosa_sekunder),
            procedure_codes: claim.procedures?.map((item) => item.kode_prosedur)
                ?? codesFromLabel(claim.prosedur_utama === '-' ? '' : claim.prosedur_utama),
            estimated_cost: claim.estimasi_biaya || 0,
            status: claim.status || 'menunggu_verifikasi',
            issue_note: claim.issue || '',
        });
        setEditModalOpen(true);
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('klaim.index'), { status: filters.status, search }, { preserveState: true });
    };

    const handleFilterStatus = (status: string) => {
        router.get(route('klaim.index'), { status, search }, { preserveState: true });
    };

    const handleQuickStatusChange = () => {
        if (!selectedClaim) return;
        router.patch(route('klaim.status', selectedClaim.id), { status: selectedStatus }, {
            onSuccess: () => {
                setSelectedClaim({ ...selectedClaim, status: selectedStatus as any });
            },
        });
    };

    const handleDelete = (claim: Claim) => {
        if (confirm(`Yakin ingin menghapus klaim ${claim.nomor_klaim} (${claim.patient.nama_lengkap})?`)) {
            router.delete(route('klaim.destroy', claim.id));
        }
    };

    const handleCreateSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(route('klaim.store'), addFormData, {
            onSuccess: () => setAddModalOpen(false),
        });
    };

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedClaim) return;
        router.put(route('klaim.update', selectedClaim.id), editFormData, {
            onSuccess: () => setEditModalOpen(false),
        });
    };

    return (
        <AuthenticatedLayout breadcrumbCurrent="Klaim Pasien">
            <Head title="Klaim Pasien - ClaimGuard" />

            <div className="space-y-5">
                {/* Header & Action Buttons */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            MANAJEMEN KLAIM BPJS
                        </div>
                        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Klaim pasien</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Kelola episode perawatan, periksa berkas, dan tindak lanjuti status klaim BPJS.
                        </p>
                    </div>
                    <div className="flex items-center gap-2.5">
                        <a
                            href={route('klaim.export')}
                            className="px-3.5 py-2 bg-white border border-slate-300 text-slate-700 rounded-lg text-xs font-medium hover:bg-slate-50 transition flex items-center gap-1.5 shadow-sm"
                        >
                            <Download className="w-4 h-4 text-slate-500" />
                            <span>Ekspor CSV</span>
                        </a>
                        <button
                            type="button"
                            onClick={() => setAddModalOpen(true)}
                            className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-medium transition flex items-center gap-1.5 shadow-sm"
                        >
                            <Plus className="w-4 h-4" />
                            <span>+ Tambah Klaim</span>
                        </button>
                    </div>
                </div>

                {/* Filter Tabs */}
                <div className="flex items-center gap-2 border-b border-slate-200 pb-2 overflow-x-auto text-xs">
                    <button
                        type="button"
                        onClick={() => handleFilterStatus('all')}
                        className={`px-3 py-1.5 rounded-lg font-medium transition ${
                            filters.status === 'all' ? 'bg-slate-200 text-slate-900 font-semibold' : 'text-slate-600 hover:bg-slate-100'
                        }`}
                    >
                        Semua klaim <span className="ml-1 text-slate-500">{counts.all}</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterStatus('menunggu_verifikasi')}
                        className={`px-3 py-1.5 rounded-lg font-medium transition ${
                            filters.status === 'menunggu_verifikasi' ? 'bg-slate-200 text-slate-900 font-semibold' : 'text-slate-600 hover:bg-slate-100'
                        }`}
                    >
                        Menunggu <span className="ml-1 text-slate-500">{counts.menunggu}</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterStatus('bermasalah')}
                        className={`px-3 py-1.5 rounded-lg font-medium transition ${
                            filters.status === 'bermasalah' ? 'bg-slate-200 text-slate-900 font-semibold' : 'text-slate-600 hover:bg-slate-100'
                        }`}
                    >
                        Bermasalah <span className="ml-1 text-amber-600 font-semibold">{counts.bermasalah}</span>
                    </button>
                    <button
                        type="button"
                        onClick={() => handleFilterStatus('siap_diajukan')}
                        className={`px-3 py-1.5 rounded-lg font-medium transition ${
                            filters.status === 'siap_diajukan' ? 'bg-slate-200 text-slate-900 font-semibold' : 'text-slate-600 hover:bg-slate-100'
                        }`}
                    >
                        Siap diajukan <span className="ml-1 text-emerald-600 font-semibold">{counts.siap}</span>
                    </button>
                </div>

                {/* Main Claims Layout: Table + Detail Sidebar */}
                <div className="flex flex-col lg:flex-row gap-6 items-start">
                    {/* Claims Table Card */}
                    <div className="flex-1 w-full bg-white rounded-xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col">
                        {/* Table Controls */}
                        <div className="p-3.5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50">
                            <form onSubmit={handleSearch} className="relative flex-1 min-w-[240px]">
                                <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama, no. RM, atau no. SEP..."
                                    className="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-500"
                                />
                            </form>
                            <div className="flex items-center gap-2">
                                <div className="flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs text-slate-700">
                                    <Calendar className="w-3.5 h-3.5 text-slate-500" />
                                    <span>{currentPeriod?.nama_periode ?? 'Tanpa periode aktif'}</span>
                                </div>
                            </div>
                        </div>

                        {/* Table */}
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="text-[11px] text-slate-400 uppercase bg-slate-50/60 border-b border-slate-200/80 font-medium tracking-wider">
                                        <th className="py-2.5 px-4 font-medium">PASIEN / ID PASIEN</th>
                                        <th className="py-2.5 px-4 font-medium">NOMOR SEP</th>
                                        <th className="py-2.5 px-4 font-medium">TGL. MASUK</th>
                                        <th className="py-2.5 px-4 font-medium">DIAGNOSIS</th>
                                        <th className="py-2.5 px-4 font-medium">STATUS KLAIM</th>
                                        <th className="py-2.5 px-3 font-medium text-center">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {claims.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={6} className="py-8 text-center text-slate-400">
                                                Tidak ada data klaim yang sesuai dengan kriteria.
                                            </td>
                                        </tr>
                                    ) : (
                                        claims.data.map((item) => {
                                            const isSelected = selectedClaim?.id === item.id;
                                            return (
                                                <tr
                                                    key={item.id}
                                                    onClick={() => setSelectedClaim(item)}
                                                    className={`hover:bg-slate-50 transition cursor-pointer ${
                                                        isSelected ? 'bg-teal-50/50' : ''
                                                    }`}
                                                >
                                                    <td className="py-3.5 px-4">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-semibold text-slate-900">{item.patient?.nama_lengkap}</span>
                                                            <span className="text-teal-600 font-bold">→</span>
                                                        </div>
                                                        <div className="text-[10px] text-slate-400 font-mono">{item.patient?.no_rm}</div>
                                                    </td>
                                                    <td className="py-3.5 px-4 font-mono text-slate-600 text-xs">
                                                        {item.no_sep}
                                                    </td>
                                                    <td className="py-3.5 px-4">
                                                        <div className="font-medium text-slate-800">{item.tanggal_masuk}</div>
                                                        <div className="text-[10px] text-slate-400">{yearFromDate(item.tanggal_masuk)}</div>
                                                    </td>
                                                    <td className="py-3.5 px-4">
                                                        <div className="font-bold text-slate-800 font-mono text-[11px]">{item.diagnosa_primer.split(' ')[0]}</div>
                                                        <div className="text-[11px] text-slate-500">{item.diagnosa_primer.substring(item.diagnosa_primer.indexOf(' ') + 1)}</div>
                                                    </td>
                                                    <td className="py-3.5 px-4">
                                                        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold ${
                                                            item.status === 'bermasalah' ? 'bg-amber-50 text-amber-700' :
                                                            item.status === 'siap_diajukan' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700'
                                                        }`}>
                                                            <span className={`w-1.5 h-1.5 rounded-full ${
                                                                item.status === 'bermasalah' ? 'bg-amber-500' :
                                                                item.status === 'siap_diajukan' ? 'bg-emerald-500' : 'bg-sky-500'
                                                            }`} />
                                                            {item.status}
                                                        </span>
                                                    </td>
                                                    <td className="py-3.5 px-3 text-center" onClick={(e) => e.stopPropagation()}>
                                                        <div className="flex items-center justify-center gap-1">
                                                            <Link
                                                                href={route('verifikasi.show', { claim: item.id, step: 'v-claim' })}
                                                                className="p-1.5 text-teal-600 hover:text-teal-800 hover:bg-teal-50 rounded transition"
                                                                title="Buka Verifikasi"
                                                            >
                                                                <Eye className="w-4 h-4" />
                                                            </Link>
                                                            <button
                                                                type="button"
                                                                onClick={() => openEdit(item)}
                                                                className="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded transition"
                                                                title="Edit Klaim"
                                                            >
                                                                <Edit2 className="w-4 h-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                onClick={() => handleDelete(item)}
                                                                className="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded transition"
                                                                title="Hapus Klaim"
                                                            >
                                                                <Trash2 className="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination Bar */}
                        <div className="p-3.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500 bg-white">
                            <span>Menampilkan {claims.from || 0}-{claims.to || 0} dari {claims.total} klaim</span>
                            <div className="flex items-center gap-1">
                                {claims.links.map((link, idx) => (
                                    <Link
                                        key={idx}
                                        href={link.url || '#'}
                                        preserveState
                                        className={`px-2.5 py-1 rounded text-xs transition ${
                                            link.active ? 'bg-teal-600 text-white font-bold' : 'text-slate-600 hover:bg-slate-100'
                                        } ${!link.url ? 'opacity-40 pointer-events-none' : ''}`}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Detail Klaim Pasien (Right Drawer / Panel) */}
                    {selectedClaim && (
                        <div className="w-full lg:w-96 bg-white rounded-xl border border-slate-200/90 shadow-sm p-5 space-y-4 shrink-0">
                            <div className="flex items-start justify-between border-b border-slate-100 pb-3">
                                <h3 className="font-bold text-slate-800 text-sm">Detail klaim pasien</h3>
                                <span className="text-xs text-slate-400 font-mono">{selectedClaim.nomor_klaim}</span>
                            </div>

                            {/* Patient Header Mini Card */}
                            <div className="flex items-start gap-3">
                                <div className="w-10 h-10 rounded-lg bg-teal-100/70 border border-teal-200 text-teal-800 font-bold flex items-center justify-center text-sm shrink-0">
                                    {selectedClaim.patient?.nama_lengkap?.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase()}
                                </div>
                                <div className="flex-1 overflow-hidden">
                                    <h4 className="font-bold text-slate-900 text-sm leading-tight truncate">
                                        {selectedClaim.patient?.nama_lengkap}
                                    </h4>
                                    <p className="text-[11px] text-slate-500 mt-0.5 truncate">
                                        {selectedClaim.patient?.jenis_kelamin} · {selectedClaim.patient?.usia} tahun · {selectedClaim.patient?.no_rm}
                                    </p>
                                    <div className="mt-1">
                                        <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold ${
                                            selectedClaim.status === 'bermasalah' ? 'bg-amber-50 text-amber-700' :
                                            selectedClaim.status === 'siap_diajukan' ? 'bg-emerald-50 text-emerald-700' : 'bg-sky-50 text-sky-700'
                                        }`}>
                                            <span className={`w-1.5 h-1.5 rounded-full ${
                                                selectedClaim.status === 'bermasalah' ? 'bg-amber-500' :
                                                selectedClaim.status === 'siap_diajukan' ? 'bg-emerald-500' : 'bg-sky-500'
                                            }`} />
                                            <span>{selectedClaim.status}</span>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Meta Details */}
                            <div className="space-y-2.5 text-xs pt-2 border-t border-slate-100">
                                <div className="flex justify-between">
                                    <span className="text-slate-400">Nomor klaim</span>
                                    <span className="font-semibold text-slate-800">{selectedClaim.nomor_klaim}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-slate-400">Nomor SEP</span>
                                    <span className="font-medium text-slate-700 font-mono text-[11px]">{selectedClaim.no_sep}</span>
                                </div>
                                <div className="grid grid-cols-2 gap-2 pt-1">
                                    <div>
                                        <span className="text-[11px] text-slate-400 block">Jenis perawatan</span>
                                        <span className="font-medium text-slate-700 text-xs">{selectedClaim.care_type}</span>
                                    </div>
                                    <div>
                                        <span className="text-[11px] text-slate-400 block">Masuk / pulang</span>
                                        <span className="font-medium text-slate-700 text-xs">{selectedClaim.tanggal_masuk} / {selectedClaim.tanggal_pulang}</span>
                                    </div>
                                </div>
                                <div className="pt-1">
                                    <span className="text-[11px] text-slate-400 block">DPJP</span>
                                    <span className="font-medium text-slate-700 text-xs">{selectedClaim.dpjp_nama || '-'}</span>
                                </div>
                                <div className="pt-1">
                                    <span className="text-[11px] text-slate-400 block">Diagnosis primer / sekunder</span>
                                    <span className="font-medium text-slate-700 text-xs">{selectedClaim.diagnosa_primer} / {selectedClaim.diagnosa_sekunder}</span>
                                </div>
                                <div className="pt-1">
                                    <span className="text-[11px] text-slate-400 block">Estimasi nilai klaim</span>
                                    <span className="font-bold text-slate-900 text-sm text-teal-700">
                                        Rp{Number(selectedClaim.estimasi_biaya).toLocaleString('id-ID')}
                                    </span>
                                </div>
                            </div>

                            {/* Issue Alert Note */}
                            {selectedClaim.issue && (
                                <div className={`p-3 rounded-lg text-xs ${
                                    selectedClaim.status === 'bermasalah' ? 'bg-amber-50 border border-amber-200/70 text-amber-800' :
                                    selectedClaim.status === 'siap_diajukan' ? 'bg-emerald-50 border border-emerald-200/70 text-emerald-800' :
                                    'bg-sky-50 border border-sky-200/70 text-sky-800'
                                }`}>
                                    <div className="flex items-center gap-1.5 font-semibold mb-1">
                                        <AlertTriangle className="w-4 h-4 shrink-0" />
                                        <span>{selectedClaim.issue}</span>
                                    </div>
                                    <p className="text-[11px] leading-relaxed opacity-90">{selectedClaim.issue_desc}</p>
                                </div>
                            )}

                            {/* Quick Status Change */}
                            <div className="pt-2 border-t border-slate-100">
                                <label className="block text-[11px] font-medium text-slate-500 mb-1">Ubah status klaim (simulasi)</label>
                                <div className="flex gap-2">
                                    <select
                                        value={selectedStatus}
                                        onChange={(e) => setSelectedStatus(e.target.value)}
                                        className="flex-1 px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-700"
                                    >
                                        <option value="Bermasalah">Bermasalah</option>
                                        <option value="Menunggu">Menunggu</option>
                                        <option value="Siap diajukan">Siap diajukan</option>
                                    </select>
                                    <button
                                        type="button"
                                        onClick={handleQuickStatusChange}
                                        className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium flex items-center gap-1"
                                    >
                                        <Save className="w-3.5 h-3.5" />
                                        Simpan
                                    </button>
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="pt-3 border-t border-slate-100 space-y-2">
                                <Link
                                    href={route('verifikasi.show', { claim: selectedClaim.id, step: 'v-claim' })}
                                    className="w-full px-4 py-2.5 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-medium transition flex items-center justify-center gap-2 shadow-sm"
                                >
                                    <span>Buka verifikasi klaim</span>
                                    <ArrowRight className="w-4 h-4" />
                                </Link>
                                <button
                                    type="button"
                                    onClick={() => openEdit(selectedClaim)}
                                    className="w-full px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium transition flex items-center justify-center gap-1.5"
                                >
                                    <Edit2 className="w-3.5 h-3.5" />
                                    <span>Edit Rincian Klaim</span>
                                </button>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* EDIT CLAIM MODAL */}
            {editModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-fadeIn">
                    <div className="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 overflow-y-auto max-h-[90vh]">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Edit Data Klaim</h3>
                                <p className="text-xs text-slate-500">Perbarui rincian episode pasien {selectedClaim?.nomor_klaim}.</p>
                            </div>
                            <button type="button" onClick={() => setEditModalOpen(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <form onSubmit={handleEditSubmit} className="space-y-4 text-xs">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Nama Pasien</label>
                                    <input
                                        type="text"
                                        value={editFormData.patient_name}
                                        onChange={(e) => setEditFormData({ ...editFormData, patient_name: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">No. RM</label>
                                    <input
                                        type="text"
                                        value={editFormData.rm_no}
                                        onChange={(e) => setEditFormData({ ...editFormData, rm_no: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Jenis Kelamin</label>
                                    <select
                                        value={editFormData.gender}
                                        onChange={(e) => setEditFormData({ ...editFormData, gender: e.target.value as 'Perempuan' | 'Laki-laki' })}
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
                                        value={editFormData.age}
                                        onChange={(e) => setEditFormData({ ...editFormData, age: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Nomor SEP</label>
                                    <input
                                        type="text"
                                        value={editFormData.sep_no}
                                        onChange={(e) => setEditFormData({ ...editFormData, sep_no: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">No. KIS</label>
                                    <input
                                        type="text"
                                        value={editFormData.kis_no}
                                        onChange={(e) => setEditFormData({ ...editFormData, kis_no: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Jenis Layanan</label>
                                    <input
                                        type="text"
                                        value={editFormData.care_type}
                                        onChange={(e) => setEditFormData({ ...editFormData, care_type: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Status Klaim</label>
                                    <select
                                        value={editFormData.status}
                                        onChange={(e) => setEditFormData({ ...editFormData, status: e.target.value as Claim['status'] })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    >
                                        <option value="baru">Baru</option>
                                        <option value="menunggu_verifikasi">Menunggu verifikasi</option>
                                        <option value="bermasalah">Bermasalah</option>
                                        <option value="siap_diajukan">Siap diajukan</option>
                                        <option value="diajukan">Diajukan</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Tanggal Masuk</label>
                                    <input
                                        type="date"
                                        value={editFormData.admission_date}
                                        onChange={(e) => setEditFormData({ ...editFormData, admission_date: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Tanggal Pulang</label>
                                    <input
                                        type="date"
                                        value={editFormData.discharge_date}
                                        onChange={(e) => setEditFormData({ ...editFormData, discharge_date: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">DPJP</label>
                                    <input
                                        type="text"
                                        value={editFormData.dpjp}
                                        onChange={(e) => setEditFormData({ ...editFormData, dpjp: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Estimasi Biaya (Rp)</label>
                                    <input
                                        type="number"
                                        value={editFormData.estimated_cost}
                                        onChange={(e) => setEditFormData({ ...editFormData, estimated_cost: Number(e.target.value) })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                            </div>

                            <div className="space-y-3">
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Diagnosis Primer (ICD-10) <span className="text-rose-600">*</span></label>
                                    <IcdPicker
                                        type="icd10"
                                        value={editFormData.primary_diagnosis_code ? [editFormData.primary_diagnosis_code] : []}
                                        onChange={(codes) => setEditFormData({ ...editFormData, primary_diagnosis_code: codes[0] ?? '' })}
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
                                            value={editFormData.secondary_diagnosis_codes}
                                            onChange={(codes) => setEditFormData({ ...editFormData, secondary_diagnosis_codes: codes })}
                                            initialOptions={icd10Codes}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-slate-600 font-medium mb-1">Prosedur (ICD-9-CM)</label>
                                        <IcdPicker
                                            type="icd9cm"
                                            multiple
                                            max={10}
                                            value={editFormData.procedure_codes}
                                            onChange={(codes) => setEditFormData({ ...editFormData, procedure_codes: codes })}
                                            initialOptions={icd9cmCodes}
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Catatan Issue</label>
                                    <input
                                        type="text"
                                        value={editFormData.issue_note}
                                        onChange={(e) => setEditFormData({ ...editFormData, issue_note: e.target.value })}
                                        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs"
                                    />
                                </div>
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex justify-between items-center">
                                <button
                                    type="button"
                                    onClick={() => selectedClaim && handleDelete(selectedClaim)}
                                    className="px-3 py-2 text-red-600 hover:bg-red-50 rounded-lg text-xs font-medium"
                                >
                                    Hapus Klaim
                                </button>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setEditModalOpen(false)}
                                        className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={!editFormData.primary_diagnosis_code}
                                        className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold shadow-sm disabled:opacity-50"
                                    >
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* ADD CLAIM MODAL */}
            {addModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-fadeIn">
                    <div className="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-4 overflow-y-auto max-h-[90vh]">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 className="font-bold text-slate-900 text-base">Tambah Klaim Baru</h3>
                                <p className="text-xs text-slate-500">Pilih diagnosis dan prosedur dari referensi ICD yang aktif.</p>
                            </div>
                            <button type="button" onClick={() => setAddModalOpen(false)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        <form onSubmit={handleCreateSubmit} className="space-y-4 text-xs">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Nama Pasien</label>
                                    <input type="text" required value={addFormData.patient_name} onChange={(e) => setAddFormData({ ...addFormData, patient_name: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">No. RM</label>
                                    <input type="text" required value={addFormData.rm_no} onChange={(e) => setAddFormData({ ...addFormData, rm_no: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Jenis Kelamin</label>
                                    <select value={addFormData.gender} onChange={(e) => setAddFormData({ ...addFormData, gender: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs">
                                        <option value="Perempuan">Perempuan</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Usia</label>
                                    <input type="number" value={addFormData.age} onChange={(e) => setAddFormData({ ...addFormData, age: Number(e.target.value) })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Nomor SEP</label>
                                    <input type="text" required value={addFormData.sep_no} onChange={(e) => setAddFormData({ ...addFormData, sep_no: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">No. KIS</label>
                                    <input type="text" value={addFormData.kis_no} onChange={(e) => setAddFormData({ ...addFormData, kis_no: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs font-mono" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Jenis Layanan</label>
                                    <input type="text" required value={addFormData.care_type} onChange={(e) => setAddFormData({ ...addFormData, care_type: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">DPJP</label>
                                    <input type="text" value={addFormData.dpjp} onChange={(e) => setAddFormData({ ...addFormData, dpjp: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Tanggal Masuk</label>
                                    <input type="date" required value={addFormData.admission_date} onChange={(e) => setAddFormData({ ...addFormData, admission_date: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Tanggal Pulang</label>
                                    <input type="date" value={addFormData.discharge_date} onChange={(e) => setAddFormData({ ...addFormData, discharge_date: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Estimasi Biaya (Rp)</label>
                                    <input type="number" value={addFormData.estimated_cost} onChange={(e) => setAddFormData({ ...addFormData, estimated_cost: Number(e.target.value) })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                            </div>

                            <div className="space-y-3">
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Diagnosis Primer (ICD-10) <span className="text-rose-600">*</span></label>
                                    <IcdPicker
                                        type="icd10"
                                        value={addFormData.primary_diagnosis_code ? [addFormData.primary_diagnosis_code] : []}
                                        onChange={(codes) => setAddFormData({ ...addFormData, primary_diagnosis_code: codes[0] ?? '' })}
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
                                            value={addFormData.secondary_diagnosis_codes}
                                            onChange={(codes) => setAddFormData({ ...addFormData, secondary_diagnosis_codes: codes })}
                                            initialOptions={icd10Codes}
                                        />
                                    </div>
                                    <div>
                                        <label className="block text-slate-600 font-medium mb-1">Prosedur (ICD-9-CM)</label>
                                        <IcdPicker
                                            type="icd9cm"
                                            multiple
                                            max={10}
                                            value={addFormData.procedure_codes}
                                            onChange={(codes) => setAddFormData({ ...addFormData, procedure_codes: codes })}
                                            initialOptions={icd9cmCodes}
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="block text-slate-600 font-medium mb-1">Catatan Issue</label>
                                    <input type="text" value={addFormData.issue_note} onChange={(e) => setAddFormData({ ...addFormData, issue_note: e.target.value })} className="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs" />
                                </div>
                            </div>

                            <div className="pt-3 border-t border-slate-100 flex justify-end gap-2">
                                <button type="button" onClick={() => setAddModalOpen(false)} className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium">Batal</button>
                                <button type="submit" disabled={!addFormData.primary_diagnosis_code} className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold shadow-sm disabled:opacity-50">Simpan Klaim</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
