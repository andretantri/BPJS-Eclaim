import { FormEventHandler } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

interface AdminIcdCreateProps {
    type: 'icd10' | 'icd9cm';
    label: string;
    prefillKode: string;
}

export default function AdminIcdCreate({ type, label, prefillKode }: AdminIcdCreateProps) {
    const form = useForm({
        type,
        kode: prefillKode,
        deskripsi_id: '',
        deskripsi_en: '',
        kategori: '',
        is_active: true,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('admin.icd.store', { type }));
    };

    return (
        <AuthenticatedLayout breadcrumbCurrent={`Tambah Kode ${label}`}>
            <Head title={`Tambah Kode ${label} - ClaimGuard`} />

            <div className="max-w-2xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            REFERENSI KODING
                        </div>
                        <h2 className="text-xl font-bold text-slate-900">Tambah kode {label}</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Kode baru langsung tersedia di picker klaim selama statusnya aktif.
                        </p>
                    </div>
                    <Link
                        href={route('admin.icd.index', { type })}
                        className="text-xs font-medium text-slate-500 hover:text-teal-700"
                    >
                        ← Kembali ke daftar
                    </Link>
                </div>

                <form onSubmit={submit} className="bg-white rounded-xl border border-slate-200/90 shadow-sm p-5 space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Kode <span className="text-rose-600">*</span></label>
                            <input
                                type="text"
                                value={form.data.kode}
                                onChange={(event) => form.setData('kode', event.target.value.toUpperCase())}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs uppercase focus:outline-none focus:ring-1 focus:ring-teal-500"
                                placeholder={type === 'icd10' ? 'Contoh: I25.1' : 'Contoh: 88.56'}
                                required
                            />
                            {form.errors.kode && <p className="mt-1 text-[11px] text-rose-600">{form.errors.kode}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Kategori</label>
                            <input
                                type="text"
                                value={form.data.kategori}
                                onChange={(event) => form.setData('kategori', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                                placeholder="Contoh: Kardiovaskular"
                            />
                            {form.errors.kategori && <p className="mt-1 text-[11px] text-rose-600">{form.errors.kategori}</p>}
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 mb-1">Deskripsi (Indonesia) <span className="text-rose-600">*</span></label>
                        <input
                            type="text"
                            value={form.data.deskripsi_id}
                            onChange={(event) => form.setData('deskripsi_id', event.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                            required
                        />
                        {form.errors.deskripsi_id && <p className="mt-1 text-[11px] text-rose-600">{form.errors.deskripsi_id}</p>}
                    </div>

                    <div>
                        <label className="block text-xs font-medium text-slate-700 mb-1">Deskripsi (Inggris)</label>
                        <input
                            type="text"
                            value={form.data.deskripsi_en}
                            onChange={(event) => form.setData('deskripsi_en', event.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                        />
                    </div>

                    <label className="flex items-center gap-2 text-xs text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) => form.setData('is_active', event.target.checked)}
                            className="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                        />
                        Aktifkan kode (langsung muncul di form klaim)
                    </label>

                    <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <Link
                            href={route('admin.icd.index', { type })}
                            className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-lg bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan Kode'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
