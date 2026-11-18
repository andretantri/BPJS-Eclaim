import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { NotificationItem } from '@/types';
import { AlertTriangle, Bell, Check, CheckCircle2 } from 'lucide-react';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface NotificationPaginator {
    data: NotificationItem[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
}

interface NotificationsIndexProps {
    notifications: NotificationPaginator;
    unreadCount: number;
}

export default function NotificationsIndex({ notifications, unreadCount }: NotificationsIndexProps) {
    const markAllAsRead = () => {
        router.post(route('notifikasi.mark-read'), {}, { preserveScroll: true });
    };

    const markAsRead = (notification: NotificationItem) => {
        router.post(route('notifikasi.read', notification.id), {}, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout breadcrumbCurrent="Notifikasi">
            <Head title="Notifikasi - ClaimGuard" />

            <div className="space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="mb-1 text-[11px] font-semibold uppercase tracking-wider text-teal-700">
                            PUSAT NOTIFIKASI CASEMIX
                        </div>
                        <h2 className="text-2xl font-bold tracking-tight text-slate-900">Notifikasi</h2>
                        <p className="mt-0.5 text-xs text-slate-500">
                            {unreadCount > 0
                                ? `${unreadCount} notifikasi belum dibaca.`
                                : 'Semua notifikasi telah dibaca.'}
                        </p>
                    </div>

                    {unreadCount > 0 && (
                        <button
                            type="button"
                            onClick={markAllAsRead}
                            className="flex items-center gap-2 self-start rounded-lg border border-teal-200 bg-teal-50 px-4 py-2.5 text-xs font-semibold text-teal-700 transition hover:bg-teal-100"
                        >
                            <Check className="h-4 w-4" />
                            Tandai semua dibaca
                        </button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    {notifications.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 px-6 py-16 text-center">
                            <Bell className="h-8 w-8 text-slate-300" />
                            <p className="text-sm font-medium text-slate-500">Belum ada notifikasi.</p>
                            <p className="text-xs text-slate-400">
                                Notifikasi verifikasi dan sinkronisasi akan muncul di sini.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {notifications.data.map((notification) => (
                                <li key={notification.id}>
                                    <button
                                        type="button"
                                        onClick={() => markAsRead(notification)}
                                        className={`flex w-full items-start gap-3 px-5 py-4 text-left transition hover:bg-slate-50 ${
                                            notification.is_read ? '' : 'bg-teal-50/40'
                                        }`}
                                    >
                                        <span
                                            className={`mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${
                                                notification.tipe === 'action'
                                                    ? 'bg-amber-100 text-amber-700'
                                                    : 'bg-teal-100 text-teal-700'
                                            }`}
                                        >
                                            {notification.tipe === 'action' ? (
                                                <AlertTriangle className="h-4 w-4" />
                                            ) : (
                                                <CheckCircle2 className="h-4 w-4" />
                                            )}
                                        </span>

                                        <span className="min-w-0 flex-1">
                                            <span className="flex flex-wrap items-center gap-2">
                                                <span className="text-sm font-semibold text-slate-900">
                                                    {notification.judul}
                                                </span>
                                                {!notification.is_read && (
                                                    <span className="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-800">
                                                        Baru
                                                    </span>
                                                )}
                                                <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                                                    {notification.prioritas}
                                                </span>
                                            </span>
                                            <span className="mt-1 block text-xs leading-relaxed text-slate-600">
                                                {notification.pesan}
                                            </span>
                                            <span className="mt-1 block text-[11px] text-slate-400">
                                                {new Date(notification.created_at).toLocaleString('id-ID')}
                                            </span>
                                        </span>

                                        {!notification.is_read && (
                                            <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-teal-500" />
                                        )}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {notifications.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-center gap-1.5">
                        {notifications.links.map((link, index) => (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url ?? '#'}
                                preserveScroll
                                className={`rounded-lg border px-3 py-1.5 text-xs font-medium transition ${
                                    link.active
                                        ? 'border-teal-600 bg-teal-600 text-white'
                                        : 'border-slate-200 bg-white text-slate-600 hover:border-teal-300 hover:text-teal-700'
                                } ${link.url ? '' : 'pointer-events-none opacity-40'}`}
                            >
                                {link.label}
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
