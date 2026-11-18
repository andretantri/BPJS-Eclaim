import { useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Hospital, PageProps, Role, User } from '@/types';
import { Pencil, Plus, Search, Trash2, UserRoundCheck } from 'lucide-react';

interface UserRow extends User {
    role?: Role;
    hospital?: Hospital;
    is_active: boolean;
    last_login_at?: string;
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

interface AdminUsersIndexProps {
    users: Paginated<UserRow>;
    roles: Role[];
    hospitals: Hospital[];
    filters: { q: string; role: string };
}

const formatDate = (value?: string): string => {
    if (! value) {
        return '-';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? '-'
        : new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

export default function AdminUsersIndex({
    users,
    roles,
    filters,
}: AdminUsersIndexProps) {
    const { auth, impersonator } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.q ?? '');
    const [role, setRole] = useState(filters.role ?? '');

    return (
        <AuthenticatedLayout breadcrumbCurrent="Manajemen Pengguna">
            <Head title="Manajemen Pengguna - ClaimGuard" />

            <div className="space-y-6">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            PENGATURAN AKSES
                        </div>
                        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Manajemen Pengguna</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Kelola akun petugas, verifikator, dan administrator. Role menentukan izin akses di seluruh aplikasi.
                        </p>
                    </div>
                    <Link
                        href={route('admin.users.create')}
                        className="self-start inline-flex items-center gap-1.5 rounded-lg bg-teal-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-teal-700"
                    >
                        <Plus className="h-4 w-4" />
                        Tambah Pengguna
                    </Link>
                </div>

                <div className="bg-white rounded-xl border border-slate-200/90 shadow-sm overflow-hidden flex flex-col">
                    <form
                        method="get"
                        action={route('admin.users.index')}
                        className="p-3.5 border-b border-slate-100 flex flex-wrap items-center gap-3 bg-slate-50/50"
                    >
                        <div className="relative flex-1 min-w-[220px]">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                            <input
                                type="text"
                                name="q"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Cari nama, email, atau NIP..."
                                className="w-full rounded-lg border border-slate-300 bg-white py-1.5 pl-9 pr-3 text-xs text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-teal-500"
                            />
                        </div>
                        <select
                            name="role"
                            value={role}
                            onChange={(event) => setRole(event.target.value)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-teal-500"
                        >
                            <option value="">Semua role</option>
                            {roles.map((item) => (
                                <option key={item.id} value={item.name}>
                                    {item.display_name}
                                </option>
                            ))}
                        </select>
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
                                    <th className="px-3 py-2.5 font-medium">NAMA / NIP</th>
                                    <th className="px-3 py-2.5 font-medium">EMAIL</th>
                                    <th className="px-3 py-2.5 font-medium">ROLE</th>
                                    <th className="px-3 py-2.5 font-medium">JABATAN</th>
                                    <th className="px-3 py-2.5 font-medium">STATUS</th>
                                    <th className="px-3 py-2.5 font-medium">LOGIN TERAKHIR</th>
                                    <th className="px-3 py-2.5 text-center font-medium">AKSI</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {users.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="py-10 text-center text-slate-400">
                                            Tidak ada pengguna yang cocok dengan pencarian.
                                        </td>
                                    </tr>
                                ) : (
                                    users.data.map((user) => (
                                        <tr key={user.id} className="transition hover:bg-slate-50">
                                            <td className="px-3 py-3">
                                                <div className="font-semibold text-slate-800">{user.name}</div>
                                                <div className="font-mono text-[10px] text-slate-400">{user.nip ?? '-'}</div>
                                            </td>
                                            <td className="px-3 py-3 text-slate-600">{user.email}</td>
                                            <td className="px-3 py-3">
                                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">
                                                    {user.role?.display_name ?? '-'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-3 text-slate-600">{user.jabatan ?? '-'}</td>
                                            <td className="px-3 py-3">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${
                                                        user.is_active
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-rose-50 text-rose-700'
                                                    }`}
                                                >
                                                    {user.is_active ? 'Aktif' : 'Nonaktif'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-3 text-slate-500">{formatDate(user.last_login_at)}</td>
                                            <td className="px-3 py-3">
                                                <div className="flex items-center justify-center gap-1.5">
                                                    <Link
                                                        href={route('admin.users.edit', user.id)}
                                                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-medium text-slate-600 transition hover:border-teal-300 hover:text-teal-700"
                                                    >
                                                        <Pencil className="h-3.5 w-3.5" />
                                                        Edit
                                                    </Link>
                                                    {user.id !== auth.user.id && impersonator === null && (
                                                        <Link
                                                            href={route('admin.users.impersonate', user.id)}
                                                            method="post"
                                                            as="button"
                                                            onBefore={() => confirm(`Login sebagai ${user.name}?`)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-teal-200 bg-teal-50 px-2 py-1 text-[11px] font-medium text-teal-700 transition hover:bg-teal-100"
                                                        >
                                                            <UserRoundCheck className="h-3.5 w-3.5" />
                                                            Login sebagai
                                                        </Link>
                                                    )}
                                                    {user.id !== auth.user.id && (
                                                        <Link
                                                            href={route('admin.users.destroy', user.id)}
                                                            method="delete"
                                                            as="button"
                                                            onBefore={() => confirm(`Hapus akun ${user.name}?`)}
                                                            className="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-[11px] font-medium text-rose-600 transition hover:bg-rose-50"
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                            Hapus
                                                        </Link>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex items-center justify-between gap-3 border-t border-slate-100 bg-white p-3.5 text-xs text-slate-500">
                        <span>
                            Menampilkan {users.from ?? 0}-{users.to ?? 0} dari {users.total} pengguna
                        </span>
                        <div className="flex items-center gap-1">
                            {users.links.map((link, index) => (
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
