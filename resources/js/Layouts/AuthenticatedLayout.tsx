import { useState, useEffect, PropsWithChildren, ReactNode } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { NotificationItem, PageProps } from '@/types';
import {
    LayoutDashboard,
    FileText,
    CheckCircle2,
    ClipboardCheck,
    Stethoscope,
    Activity,
    AlertTriangle,
    SendHorizontal,
    BarChart3,
    Menu,
    Bell,
    Check,
    LogOut,
    HelpCircle,
    Building2,
    X,
    ExternalLink,
    Users,
    ListChecks
} from 'lucide-react';

export default function AuthenticatedLayout({
    header,
    children,
    breadcrumbCurrent = 'Dashboard',
}: PropsWithChildren<{ header?: ReactNode; breadcrumbCurrent?: string }>) {
    const page = usePage<PageProps>();
    const { auth, hospital, bpjsConfigured, claimStats, notifications, flash, impersonator } = page.props;
    const currentUrl = page.url;
    const today = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date());
    const [sidebarOpen, setSidebarOpen] = useState(
        () => typeof window === 'undefined' || window.matchMedia('(min-width: 768px)').matches,
    );
    const [notifOpen, setNotifOpen] = useState(false);
    const [notifTab, setNotifTab] = useState<'all' | 'action'>('all');
    const [helpOpen, setHelpOpen] = useState(false);
    const [toast, setToast] = useState<{ message: string; type: 'success' | 'info' | 'warning' | 'error' } | null>(null);

    // Watch flash messages
    useEffect(() => {
        if (flash.success) {
            setToast({ message: flash.success, type: 'success' });
        } else if (flash.info) {
            setToast({ message: flash.info, type: 'info' });
        } else if (flash.warning) {
            setToast({ message: flash.warning, type: 'warning' });
        } else if (flash.error) {
            setToast({ message: flash.error, type: 'error' });
        }
    }, [flash]);

    useEffect(() => {
        if (toast) {
            const timer = setTimeout(() => setToast(null), 4000);
            return () => clearTimeout(timer);
        }
    }, [toast]);

    useEffect(() => {
        const media = window.matchMedia('(min-width: 768px)');
        const handleBreakpointChange = (event: MediaQueryListEvent) => setSidebarOpen(event.matches);

        media.addEventListener('change', handleBreakpointChange);

        return () => media.removeEventListener('change', handleBreakpointChange);
    }, []);

    useEffect(() => {
        if (window.innerWidth < 768) {
            setSidebarOpen(false);
        }
    }, [currentUrl]);

    const markAllNotifications = () => {
        router.post(route('notifikasi.mark-read'), {}, {
            preserveScroll: true,
            onSuccess: () => setNotifOpen(false),
        });
    };

    const markNotificationAsRead = (notification: NotificationItem) => {
        router.post(route('notifikasi.read', notification.id), {}, {
            preserveScroll: true,
            onSuccess: () => setNotifOpen(false),
        });
    };

    const navItems = [
        { label: 'Dashboard', route: 'dashboard', icon: LayoutDashboard },
        { label: 'Klaim Pasien', route: 'klaim.index', icon: FileText, badge: (claimStats?.total ?? 0).toLocaleString('id-ID') },
    ];

    const verificationSteps = [
        { label: 'BPJS V-Claim', step: 'v-claim', href: route('verifikasi.select', { step: 'v-claim' }), icon: CheckCircle2 },
        { label: 'Verifikasi Pra-Klaim', step: 'pra-klaim', href: route('verifikasi.select', { step: 'pra-klaim' }), icon: ClipboardCheck },
        { label: 'Validasi ICD-10', step: 'icd-10', href: route('verifikasi.select', { step: 'icd-10' }), icon: Stethoscope },
        { label: 'Validasi ICD-9-CM', step: 'icd-9', href: route('verifikasi.select', { step: 'icd-9' }), icon: Activity },
        { label: 'Deteksi Risiko Klaim', step: 'risiko-klaim', href: route('verifikasi.select', { step: 'risiko-klaim' }), icon: AlertTriangle, hasDot: true },
        { label: 'Tinjauan Akhir', step: 'tinjauan-akhir', href: route('verifikasi.select', { step: 'tinjauan-akhir' }), icon: CheckCircle2 },
    ];
    const currentVerificationStep = new URLSearchParams(window.location.search).get('step')
        ?? window.location.pathname.split('/').pop()
        ?? '';

    const integrations = [
        { label: 'Sync E-Claim BPJS', route: 'eclaim.index', icon: SendHorizontal },
        { label: 'Pelaporan Klaim', route: 'laporan.index', icon: BarChart3 },
    ];

    const adminItems = auth?.isAdmin
        ? [
            { label: 'Manajemen Pengguna', routeName: 'admin.users.index', href: route('admin.users.index'), icon: Users },
            { label: 'Daftar ICD', routeName: 'admin.icd.index', href: route('admin.icd.index', { type: 'icd10' }), icon: ListChecks },
        ]
        : [];

    const filteredNotifs = notifications?.items ? (notifTab === 'all' 
        ? notifications.items 
        : notifications.items.filter(n => n.tipe === 'action' || n.prioritas === 'urgent')) : [];

    return (
        <div className="bg-slate-50 text-slate-800 font-sans antialiased flex h-screen overflow-hidden text-sm">
            {/* MOBILE SIDEBAR BACKDROP */}
            {sidebarOpen && (
                <button
                    type="button"
                    aria-label="Tutup navigasi"
                    onClick={() => setSidebarOpen(false)}
                    className="fixed inset-0 bg-slate-900/50 z-30 md:hidden"
                />
            )}

            {/* SIDEBAR NAVIGATION */}
            <aside
                className={`bg-[#0f172a] text-slate-300 flex flex-col justify-between shrink-0 transition-all duration-300 select-none border-r border-slate-800 ${
                    sidebarOpen ? 'fixed inset-y-0 left-0 w-64 z-40 md:static' : 'w-0 -ml-64 md:w-20 md:ml-0 z-30'
                }`}
            >
                <div className="flex flex-col h-full overflow-y-auto custom-scrollbar">
                    {/* App Header / Logo */}
                    <div className="p-4 border-b border-slate-800">
                        <Link href={route('dashboard')} className="flex items-center gap-2.5">
                            <div className="w-8 h-8 rounded-lg bg-teal-500/20 border border-teal-500/40 flex items-center justify-center text-teal-400 font-bold shrink-0">
                                <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div className={sidebarOpen ? 'block' : 'hidden md:hidden'}>
                                <h1 className="font-bold text-white tracking-wide text-base leading-tight">ClaimGuard</h1>
                                <p className="text-[10px] text-teal-400 font-medium tracking-wider uppercase">Casemix & Validasi</p>
                            </div>
                        </Link>

                        {/* Hospital Context Card */}
                        {sidebarOpen && hospital && (
                            <div className="mt-4 p-2.5 bg-slate-800/60 rounded-lg border border-slate-700/60 flex items-start gap-2.5">
                                <div className="mt-0.5 text-teal-400 shrink-0">
                                    <Building2 className="w-4 h-4" />
                                </div>
                                <div className="overflow-hidden">
                                    <div className="font-semibold text-white text-xs truncate">{hospital.nama_rs}</div>
                                    {hospital.kota && (
                                        <div className="text-[11px] text-slate-400 truncate">{hospital.kota}</div>
                                    )}
                                    <div className="text-[10px] text-slate-500 truncate mt-0.5">Unit Casemix & Klaim BPJS</div>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Navigation Menu */}
                    <div className="px-3 py-3 flex-1 space-y-4">
                        <div>
                            {sidebarOpen && <div className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider px-3 mb-2">RUANG KERJA</div>}
                            <nav className="space-y-1">
                                {navItems.map((item) => {
                                    const active = route().current(item.route);
                                    const Icon = item.icon;
                                    return (
                                        <Link
                                            key={item.label}
                                            href={route(item.route)}
                                            className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition group ${
                                                active
                                                    ? 'bg-[#1e293b] text-white'
                                                    : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                                            }`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <Icon className={`w-4 h-4 ${active ? 'text-teal-400' : 'text-slate-400 group-hover:text-teal-400'}`} />
                                                <span className={sidebarOpen ? 'block' : 'hidden'}>{item.label}</span>
                                            </div>
                                            {item.badge && sidebarOpen && (
                                                <span className="px-1.5 py-0.5 text-[10px] bg-slate-800 text-teal-400 font-semibold rounded">
                                                    {item.badge}
                                                </span>
                                            )}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>

                        <div>
                            {sidebarOpen && <div className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider px-3 mb-2">TAHAPAN VERIFIKASI</div>}
                            <nav className="space-y-1">
                                {verificationSteps.map((step) => {
                                    const active = currentVerificationStep === step.step;
                                    const Icon = step.icon;
                                    return (
                                        <Link
                                            key={step.label}
                                            href={step.href}
                                            className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition group ${
                                                active
                                                    ? 'bg-[#1e293b] text-white'
                                                    : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                                            }`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <Icon className={`w-4 h-4 ${active ? 'text-teal-400' : 'text-slate-400 group-hover:text-teal-400'}`} />
                                                <span className={sidebarOpen ? 'block' : 'hidden'}>{step.label}</span>
                                            </div>
                                            {step.hasDot && sidebarOpen && <span className="w-2 h-2 rounded-full bg-amber-500" />}
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>

                        <div>
                            {sidebarOpen && <div className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider px-3 mb-2">INTEGRASI & LAPORAN</div>}
                            <nav className="space-y-1">
                                {integrations.map((item) => {
                                    const active = route().current(item.route);
                                    const Icon = item.icon;
                                    return (
                                        <Link
                                            key={item.label}
                                            href={route(item.route)}
                                            className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition group ${
                                                active
                                                    ? 'bg-[#1e293b] text-white'
                                                    : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                                            }`}
                                        >
                                            <div className="flex items-center gap-3">
                                                <Icon className={`w-4 h-4 ${active ? 'text-teal-400' : 'text-slate-400 group-hover:text-teal-400'}`} />
                                                <span className={sidebarOpen ? 'block' : 'hidden'}>{item.label}</span>
                                            </div>
                                        </Link>
                                    );
                                })}
                            </nav>
                        </div>

                        {adminItems.length > 0 && (
                            <div>
                                {sidebarOpen && <div className="text-[10px] font-semibold text-slate-500 uppercase tracking-wider px-3 mb-2">PENGATURAN</div>}
                                <nav className="space-y-1">
                                    {adminItems.map((item) => {
                                        const active = route().current(item.routeName);
                                        const Icon = item.icon;
                                        return (
                                            <Link
                                                key={item.label}
                                                href={item.href}
                                                className={`w-full flex items-center justify-between px-3 py-2 rounded-lg text-xs font-medium transition group ${
                                                    active
                                                        ? 'bg-[#1e293b] text-white'
                                                        : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                                                }`}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <Icon className={`w-4 h-4 ${active ? 'text-teal-400' : 'text-slate-400 group-hover:text-teal-400'}`} />
                                                    <span className={sidebarOpen ? 'block' : 'hidden'}>{item.label}</span>
                                                </div>
                                            </Link>
                                        );
                                    })}
                                </nav>
                            </div>
                        )}

                        {/* Help box */}
                        {sidebarOpen && (
                            <div className="p-3 bg-slate-800/40 rounded-xl border border-slate-800 text-[11px]">
                                <div className="font-semibold text-white mb-1">Butuh panduan?</div>
                                <p className="text-slate-400 text-[10px] leading-relaxed mb-2">Alur verifikasi dan panduan koding untuk tim casemix.</p>
                                <button
                                    type="button"
                                    onClick={() => setHelpOpen(true)}
                                    className="inline-flex items-center gap-1 text-teal-400 font-medium hover:text-teal-300 transition text-[11px]"
                                >
                                    Buka pusat panduan ↗
                                </button>
                            </div>
                        )}
                    </div>

                    {/* User Profile Footer */}
                    <div className="p-3 border-t border-slate-800">
                        <div className="flex items-center justify-between p-2 rounded-lg hover:bg-slate-800/60 transition">
                            <div className="flex items-center gap-2.5">
                                <div className="w-8 h-8 rounded-full bg-teal-600/30 border border-teal-500/30 flex items-center justify-center text-teal-300 font-bold text-xs">
                                    {auth?.user?.name ? auth.user.name.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase() : '?'}
                                </div>
                                {sidebarOpen && (
                                    <div className="overflow-hidden">
                                        <div className="text-xs font-medium text-white leading-tight truncate">{auth?.user?.name ?? '-'}</div>
                                        {auth?.user?.jabatan && (
                                            <div className="text-[10px] text-slate-400 truncate">{auth.user.jabatan}</div>
                                        )}
                                    </div>
                                )}
                            </div>
                            {sidebarOpen && (
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="p-1 text-slate-500 hover:text-red-400 transition"
                                    title="Logout"
                                >
                                    <LogOut className="w-4 h-4" />
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </aside>

            {/* MAIN CONTENT AREA */}
            <div className="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
                {/* TOP NAVBAR */}
                <header className="h-14 bg-white border-b border-slate-200 px-4 md:px-6 flex items-center justify-between shrink-0 z-20">
                    <div className="flex items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setSidebarOpen(!sidebarOpen)}
                            className="p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition"
                            title="Toggle Sidebar"
                        >
                            <Menu className="w-5 h-5" />
                        </button>
                        <div className="flex items-center text-xs text-slate-500 gap-1.5">
                            <span>Ruang kerja casemix</span>
                            <span className="text-slate-400">›</span>
                            <span className="font-medium text-slate-800">{breadcrumbCurrent}</span>
                        </div>
                    </div>

                    {/* Header Right Badges & Tools */}
                    <div className="flex items-center gap-3">
                        <div className={`hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-medium border ${
                            bpjsConfigured
                                ? 'bg-emerald-50 border-emerald-200/80 text-emerald-800'
                                : 'bg-amber-50 border-amber-200/80 text-amber-800'
                        }`}>
                            <span className={`w-1.5 h-1.5 rounded-full ${bpjsConfigured ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse'}`} />
                            <span>{bpjsConfigured ? 'BPJS terhubung' : 'Mode simulasi · BPJS belum terkonfigurasi'}</span>
                        </div>

                        {/* Notification Bell & Dropdown */}
                        <div className="relative">
                            <button
                                type="button"
                                onClick={() => setNotifOpen(!notifOpen)}
                                className="relative p-1.5 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition focus:outline-none"
                                title="Notifikasi"
                            >
                                <Bell className="w-5 h-5" />
                                {(notifications?.unreadCount ?? 0) > 0 && (
                                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white" />
                                )}
                            </button>

                            {/* Notification Dropdown Panel */}
                            {notifOpen && (
                                <div className="absolute right-0 top-full mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-2xl border border-slate-200 z-50 animate-fadeIn overflow-hidden">
                                    <div className="p-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/80">
                                        <div className="flex items-center gap-2">
                                            <span className="font-bold text-slate-900 text-xs">Notifikasi Casemix</span>
                                            {(notifications?.unreadCount ?? 0) > 0 && (
                                                <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-teal-100 text-teal-800">
                                                    {notifications.unreadCount} Baru
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <Link
                                                href={route('notifikasi.index')}
                                                onClick={() => setNotifOpen(false)}
                                                className="text-[11px] text-slate-500 hover:text-slate-800 font-medium hover:underline"
                                            >
                                                Lihat semua
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={markAllNotifications}
                                                className="text-[11px] text-teal-600 hover:text-teal-800 font-medium hover:underline flex items-center gap-1"
                                            >
                                                <Check className="w-3.5 h-3.5" />
                                                Tandai dibaca
                                            </button>
                                        </div>
                                    </div>

                                    {/* Tabs */}
                                    <div className="flex items-center gap-2 px-3.5 py-2 border-b border-slate-100 bg-white text-[11px]">
                                        <button
                                            type="button"
                                            onClick={() => setNotifTab('all')}
                                            className={`pb-0.5 ${notifTab === 'all' ? 'font-semibold text-teal-700 border-b-2 border-teal-600' : 'text-slate-500 hover:text-slate-800'}`}
                                        >
                                            Semua ({notifications?.items?.length || 0})
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setNotifTab('action')}
                                            className={`pb-0.5 ${notifTab === 'action' ? 'font-semibold text-teal-700 border-b-2 border-teal-600' : 'text-slate-500 hover:text-slate-800'}`}
                                        >
                                            Perlu Tindakan
                                        </button>
                                    </div>

                                    {/* List */}
                                    <div className="max-h-80 overflow-y-auto divide-y divide-slate-100 custom-scrollbar text-xs">
                                        {filteredNotifs.length === 0 ? (
                                            <div className="p-6 text-center text-slate-400 text-xs">Tidak ada notifikasi.</div>
                                        ) : (
                                            filteredNotifs.map((n) => (
                                                <button
                                                    key={n.id}
                                                    type="button"
                                                    onClick={() => markNotificationAsRead(n)}
                                                    className="block w-full text-left p-3.5 hover:bg-slate-50 transition"
                                                >
                                                    <div className="flex items-start gap-3">
                                                        <div className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5 ${
                                                            n.tipe === 'action' ? 'bg-amber-100 text-amber-700' : 'bg-teal-100 text-teal-700'
                                                        }`}>
                                                            {n.tipe === 'action' ? <AlertTriangle className="w-4 h-4" /> : <CheckCircle2 className="w-4 h-4" />}
                                                        </div>
                                                        <div className="flex-1">
                                                            <div className="flex items-center justify-between">
                                                                <h5 className="font-bold text-slate-900 text-xs">{n.judul}</h5>
                                                                {!n.is_read && <span className="w-2 h-2 rounded-full bg-teal-500" />}
                                                            </div>
                                                            <p className="text-[11px] text-slate-600 mt-0.5 leading-snug">{n.pesan}</p>
                                                        </div>
                                                    </div>
                                                </button>
                                            ))
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>

                        <div className="text-xs font-medium text-slate-600 border-l border-slate-200 pl-3">
                            {today}
                        </div>
                    </div>
                </header>

                {impersonator && (
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-[11px] text-amber-900 md:px-6">
                        <span>
                            Mode <strong>Login sebagai</strong>: Anda sedang bertindak sebagai{' '}
                            <strong>{auth?.user?.name}</strong>. Login asli: {impersonator.name} ({impersonator.email}).
                        </span>
                        <Link
                            href={route('impersonation.leave')}
                            method="post"
                            as="button"
                            className="self-start rounded-lg border border-amber-300 bg-white px-2.5 py-1 font-semibold text-amber-800 transition hover:bg-amber-100"
                        >
                            Kembali ke {impersonator.name}
                        </Link>
                    </div>
                )}

                {/* WORKSPACE BODY */}
                <main className="flex-1 overflow-y-auto p-4 md:p-6 custom-scrollbar">
                    {children}
                </main>
            </div>

            {/* TOAST NOTIFICATION */}
            {toast && (
                <div className="fixed bottom-6 right-6 z-50 pointer-events-none">
                    <div className={`px-4 py-2.5 rounded-xl shadow-xl text-xs font-medium flex items-center gap-2 pointer-events-auto transition-all animate-fadeIn ${
                        toast.type === 'success' ? 'bg-teal-700 text-white' :
                        toast.type === 'warning' ? 'bg-amber-600 text-white' :
                        toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-slate-800 text-white'
                    }`}>
                        <span>{toast.type === 'success' ? '✓' : toast.type === 'warning' ? '⚠️' : 'ℹ️'}</span>
                        <span>{toast.message}</span>
                    </div>
                </div>
            )}

            {/* HELP MODAL */}
            {helpOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs animate-fadeIn">
                    <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <h3 className="font-bold text-slate-900 text-base">Pusat Panduan Casemix & Klaim</h3>
                            <button type="button" onClick={() => setHelpOpen(false)} className="text-slate-400 hover:text-slate-600">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="text-xs text-slate-600 space-y-3">
                            <p><strong>Alur Kerja 6 Tahap Verifikasi:</strong></p>
                            <ol className="list-decimal pl-4 space-y-1">
                                <li><strong>BPJS V-Claim:</strong> Verifikasi keabsahan SEP dan status kepesertaan aktif KIS.</li>
                                <li><strong>Pra-Klaim:</strong> Periksa 5 kelengkapan dokumen (SEP, Rujukan, Resume Medis, Billing, Toraks).</li>
                                <li><strong>Validasi ICD-10:</strong> Validasi kesesuaian diagnosis primer dan sekunder.</li>
                                <li><strong>Validasi ICD-9-CM:</strong> Pencocokan tindakan dan prosedur medis.</li>
                                <li><strong>Deteksi Risiko:</strong> Penapisan risiko fraud, overcoding, atau berkas tertunda.</li>
                                <li><strong>Tinjauan Akhir:</strong> Approval pengajuan dan batch sync ke INA-CBGs E-Claim.</li>
                            </ol>
                        </div>
                        <div className="pt-2 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setHelpOpen(false)}
                                className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded-lg transition"
                            >
                                Mengerti
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
