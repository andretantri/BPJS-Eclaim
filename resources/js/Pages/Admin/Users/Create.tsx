import { FormEventHandler, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Hospital, PageProps, Role } from '@/types';

interface AdminUsersCreateProps {
    roles: Role[];
    hospitals: Hospital[];
}

export default function AdminUsersCreate({ roles, hospitals }: AdminUsersCreateProps) {
    const { flash } = usePage<PageProps>().props;
    const [showPassword, setShowPassword] = useState(false);
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        nip: '',
        jabatan: '',
        role_id: roles[0]?.id ?? '',
        hospital_id: hospitals[0]?.id ?? '',
        is_active: true,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.post(route('admin.users.store'));
    };

    return (
        <AuthenticatedLayout breadcrumbCurrent="Tambah Pengguna">
            <Head title="Tambah Pengguna - ClaimGuard" />

            <div className="max-w-3xl space-y-5">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            PENGATURAN AKSES
                        </div>
                        <h2 className="text-xl font-bold text-slate-900">Tambah pengguna</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Akun baru akan terhubung ke rumah sakit Anda dan menggunakan role yang dipilih.
                        </p>
                    </div>
                    <Link
                        href={route('admin.users.index')}
                        className="text-xs font-medium text-slate-500 hover:text-teal-700"
                    >
                        ← Kembali ke daftar
                    </Link>
                </div>

                {flash.error && <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{flash.error}</div>}

                <form onSubmit={submit} className="bg-white rounded-xl border border-slate-200/90 shadow-sm p-5 space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Nama <span className="text-rose-600">*</span></label>
                            <input
                                type="text"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                                required
                            />
                            {form.errors.name && <p className="mt-1 text-[11px] text-rose-600">{form.errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Email <span className="text-rose-600">*</span></label>
                            <input
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData('email', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                                required
                            />
                            {form.errors.email && <p className="mt-1 text-[11px] text-rose-600">{form.errors.email}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">NIP</label>
                            <input
                                type="text"
                                value={form.data.nip}
                                onChange={(event) => form.setData('nip', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                            />
                            {form.errors.nip && <p className="mt-1 text-[11px] text-rose-600">{form.errors.nip}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Jabatan</label>
                            <input
                                type="text"
                                value={form.data.jabatan}
                                onChange={(event) => form.setData('jabatan', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                            />
                            {form.errors.jabatan && <p className="mt-1 text-[11px] text-rose-600">{form.errors.jabatan}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Role <span className="text-rose-600">*</span></label>
                            <select
                                value={form.data.role_id}
                                onChange={(event) => form.setData('role_id', Number(event.target.value))}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                            >
                                {roles.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.display_name}
                                    </option>
                                ))}
                            </select>
                            {form.errors.role_id && <p className="mt-1 text-[11px] text-rose-600">{form.errors.role_id}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Rumah Sakit <span className="text-rose-600">*</span></label>
                            <select
                                value={form.data.hospital_id}
                                onChange={(event) => form.setData('hospital_id', Number(event.target.value))}
                                className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                            >
                                {hospitals.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.nama_rs}
                                    </option>
                                ))}
                            </select>
                            {form.errors.hospital_id && <p className="mt-1 text-[11px] text-rose-600">{form.errors.hospital_id}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Password <span className="text-rose-600">*</span></label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={form.data.password}
                                    onChange={(event) => form.setData('password', event.target.value)}
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 pr-16 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                                    required
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(!showPassword)}
                                    className="absolute right-2 top-1.5 text-[10px] text-teal-600"
                                >
                                    {showPassword ? 'Sembunyi' : 'Lihat'}
                                </button>
                            </div>
                            {form.errors.password && <p className="mt-1 text-[11px] text-rose-600">{form.errors.password}</p>}
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">Konfirmasi Password <span className="text-rose-600">*</span></label>
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-teal-500"
                                required
                            />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-xs text-slate-700">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(event) => form.setData('is_active', event.target.checked)}
                            className="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                        />
                        Akun aktif (pengguna dapat login)
                    </label>

                    <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <Link
                            href={route('admin.users.index')}
                            className="rounded-lg bg-slate-100 px-4 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
                        >
                            Batal
                        </Link>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="rounded-lg bg-teal-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-700 disabled:opacity-50"
                        >
                            {form.processing ? 'Menyimpan...' : 'Simpan Pengguna'}
                        </button>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
