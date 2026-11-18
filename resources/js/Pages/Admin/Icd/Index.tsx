import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CircleCheck, Plus, Search } from 'lucide-react';

interface IcdCodeRow {
    id: number;
    kode: string;
    deskripsi_id: string;
    deskripsi_en?: string | null;
    kategori?: string | null;
    is_active: boolean;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface AdminIcdIndexProps {
    type: 'icd10' | 'icd9cm';
    label: string;
    codes: Paginated<IcdCodeRow>;
    filters: { q: string };
    exactMatchExists: boolean;
}

export default function AdminIcdIndex({
    type,
    label,
    codes,
    filters,
    exactMatchExists,
}: AdminIcdIndexProps) {
    const [search, setSearch] = useState(filters.q ?? '');

    const tabs: Array<{ key: 'icd10' | 'icd9cm'; label: string }> = [
        { key: 'icd10', label: 'ICD-10' },
        { key: 'icd9cm', label: 'ICD-9-CM' },
    ];

    return (
        <AuthenticatedLayout breadcrumbCurrent={`Daftar ${label}`}>
            <Head title={`Daftar ${label} - ClaimGuard`} />

            <div className="space-y-6">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            REFERENSI KODING
                        </div>
                        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Daftar {label}</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Cari kode untuk mengecek keberadaannya. Jika belum ada, tambahkan kode baru di sini. Hanya kode aktif yang muncul di form klaim.
                        </p>
                    </div>
                    <Link
                        href={route('admin.icd.create', { type })}
                        className="self-start inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-700"
                    >
                        <Plus className="h-4 w-4" />
                        Tambah Kode
                    </Link>
                </div>

                <div className="flex gap-1 rounded-xl border border-slate-200 bg-white p-1 w-fit shadow-sm">
                    {tabs.map((tab) => (
                        <Link
                            key={tab.key}
                            href={route('admin.icd.index', { type: tab.key })}
                            className={`rounded-lg px-4 py-1.5 text-xs font-semibold transition ${
                                type === tab.key
                                    ? 'bg-teal-600 text-white shadow-sm'
                                    : 'text-slate-500 hover:bg-slate-50'
                            }`}
                        >
                            {tab.label}
                        </Link>
                    ))}
                </div>

                {filters.q !== '' && (
                    <div
                        className={`flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-lg border px-3.5 py-2.5 text-xs ${
                            exactMatchExists
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-amber-200 bg-amber-50 text-amber-800'
                        }`}
                    >
                        <div className="flex items-center gap-2">
                            <CircleCheck className={`h-4 w-4 ${exactMatchExists ? 'text-emerald-600' : 'text-amber-500'}`} />
                            <span>
                                {exactMatchExists
                                    ? `Kode ${filters.q.toUpperCase()} sudah ada pada referensi ${label}.`
                                    : `Kode ${filters.q.toUpperCase()} belum ditemukan pada referensi ${label}.`}
                            </span>
                        </div>
                        {! exactMatchExists && (
                            <Link
                                href={route('admin.icd.create', { type, kode: filters.q.toUpperCase() })}
                                className="self-start inline-flex items-center gap-1 font-semibold text-amber-900 underline-offset-2 hover:underline"
                            >
                                <Plus className="h-3.5 w-3.5" />
                                Tambahkan kode ini
                            </Link>
                        )}
                    </div>
                )}

                <div className="bg-white rounded-xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col">
                    <form
                        method="get"
                        action={route('admin.icd.index', { type })}
                        className="p-3.5 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50"
                    >
                        <div className="relative flex-1 min-w-[240px]">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                name="q"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Cari kode, deskripsi, atau kategori..."
                                className="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-9 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-500"
                            />
                        </div>
                        <button
                            type="submit"
                            className="rounded-lg bg-teal-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-700"
                        >
                            Cek Kode
                        </button>
                    </form>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="border-b border-slate-100 text-[11px] font-medium uppercase text-slate-400">
                                    <th className="px-3 py-2.5 font-medium">KODE</th>
                                    <th className="px-3 py-2.5 font-medium">DESKRIPSI</th>
                                    <th className="px-3 py-2.5 font-medium">KATEGORI</th>
                                    <th className="px-3 py-2.5 font-medium">STATUS</th>
                                    <th className="px-3 py-2.5 text-center font-medium">AKSI</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {codes.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={5} className="py-10 text-center text-slate-400">
                                            Tidak ada kode yang cocok. Gunakan tombol “Tambah Kode” untuk menambahkannya.
                                        </td>
                                    </tr>
                                ) : (
                                    codes.data.map((code) => (
                                        <tr key={code.id} className="transition hover:bg-slate-50">
                                            <td className="px-3 py-3 font-mono font-semibold text-slate-800">{code.kode}</td>
                                            <td className="px-3 py-3">
                                                <div className="text-slate-700">{code.deskripsi_id}</div>
                                                {code.deskripsi_en && (
                                                    <div className="text-[10px] text-slate-400">{code.deskripsi_en}</div>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-slate-500">{code.kategori ?? '-'}</td>
                                            <td className="px-3 py-3">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                                        code.is_active
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-slate-100 text-slate-500'
                                                    }`}
                                                >
                                                    {code.is_active ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-3 text-center">
                                                <Link
                                                    href={route('admin.icd.toggle', { type, icdCode: code.id })}
                                                    method="patch"
                                                    data={{ is_active: ! code.is_active }}
                                                    as="button"
                                                    className={`rounded-lg border px-2.5 py-1 text-[11px] font-semibold transition ${
                                                        code.is_active
                                                            ? 'border-slate-200 text-slate-600 hover:bg-slate-100'
                                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                    }`}
                                                >
                                                    {code.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 bg-white p-3.5 text-xs text-slate-500">
                        <span>
                            Menampilkan {codes.from ?? 0}-{codes.to ?? 0} dari {codes.total} kode
                        </span>
                        <div className="flex items-center gap-1">
                            {codes.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url || '#'}
                                    preserveState
                                    className={`rounded px-2.5 py-1 text-xs transition ${
                                        link.active ? 'bg-teal-600 font-bold text-white' : 'text-slate-600 hover:bg-slate-100'
                                    } ${! link.url ? 'pointer-events-none opacity-40' : ''}`}
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
