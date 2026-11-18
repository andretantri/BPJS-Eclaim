import { useEffect, useState } from 'react';
import { Head, router, usePage, usePoll } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Claim, EclaimSync, PageProps } from '@/types';
import {
    SendHorizontal,
    CheckCircle2,
    Clock,
    AlertCircle,
    RotateCw,
    ShieldCheck,
    Layers,
    FileCheck
} from 'lucide-react';

interface EclaimIndexProps {
    readyClaims: Claim[];
    syncHistory: EclaimSync[];
    stats: {
        totalSynced: number;
        totalReadyToSync: number;
        totalBatch: number;
        mode: 'bpjs' | 'simulasi_lokal';
    };
}

export default function EclaimIndex({
    readyClaims,
    syncHistory,
    stats,
}: EclaimIndexProps) {
    const { auth } = usePage<PageProps>().props;
    const [selectedClaimIds, setSelectedClaimIds] = useState<number[]>([]);
    const [isSyncing, setIsSyncing] = useState(false);
    const [retryingBatchId, setRetryingBatchId] = useState<number | null>(null);

    const canSync = auth.permissions.includes('eclaim.sync') || auth.isAdmin;
    const hasActiveBatch = syncHistory.some(
        (batch) => batch.status === 'pending' || batch.status === 'processing',
    );

    const { start: startPolling, stop: stopPolling } = usePoll(
        5000,
        { only: ['syncHistory', 'readyClaims', 'stats'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (hasActiveBatch) {
            startPolling();
        } else {
            stopPolling();
        }
    }, [hasActiveBatch]);

    const toggleSelectAll = () => {
        if (selectedClaimIds.length === readyClaims.length) {
            setSelectedClaimIds([]);
        } else {
            setSelectedClaimIds(readyClaims.map((c) => c.id));
        }
    };

    const toggleSelectClaim = (id: number) => {
        if (selectedClaimIds.includes(id)) {
            setSelectedClaimIds(selectedClaimIds.filter((cid) => cid !== id));
        } else {
            setSelectedClaimIds([...selectedClaimIds, id]);
        }
    };

    const handleSyncBatch = () => {
        setIsSyncing(true);
        router.post(
            route('eclaim.sync-batch'),
            { claim_ids: selectedClaimIds.length > 0 ? selectedClaimIds : readyClaims.map((c) => c.id) },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSyncing(false);
                    setSelectedClaimIds([]);
                },
            }
        );
    };

    const handleSyncSingle = (claimId: number) => {
        router.post(route('eclaim.sync-single', claimId), {}, { preserveScroll: true });
    };

    const handleRetryBatch = (batchId: number) => {
        setRetryingBatchId(batchId);
        router.post(route('eclaim.retry', batchId), {}, {
            preserveScroll: true,
            onFinish: () => setRetryingBatchId(null),
        });
    };

    const batchStatusBadge = (status: EclaimSync['status']) => {
        switch (status) {
            case 'pending':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600">
                        <Clock className="h-3.5 w-3.5" />
                        Menunggu antrean
                    </span>
                );
            case 'processing':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700">
                        <RotateCw className="h-3.5 w-3.5 animate-spin" />
                        Diproses
                    </span>
                );
            case 'failed':
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700">
                        <AlertCircle className="h-3.5 w-3.5" />
                        Gagal
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700">
                        <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
                        Selesai
                    </span>
                );
        }
    };

    return (
        <AuthenticatedLayout breadcrumbCurrent="Sync E-Claim BPJS">
            <Head title="Sync E-Claim BPJS - ClaimGuard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <div className="text-[11px] font-semibold text-teal-700 tracking-wider uppercase mb-1">
                            INTEGRASI GATEWAY BPJS KESEHATAN
                        </div>
                        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Sync E-Claim (INA-CBGs)</h2>
                        <p className="text-xs text-slate-500 mt-0.5">
                            Kirim data klaim yang telah lolos verifikasi 6 tahap ke server E-Claim BPJS secara batch.
                        </p>
                    </div>

                    {canSync ? (
                        <button
                            type="button"
                            onClick={handleSyncBatch}
                            disabled={isSyncing || readyClaims.length === 0}
                            className="px-4 py-2.5 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 text-white rounded-lg text-xs font-semibold transition flex items-center gap-2 shadow-sm"
                        >
                            <SendHorizontal className={`w-4 h-4 ${isSyncing ? 'animate-bounce' : ''}`} />
                            <span>{isSyncing ? 'Menambahkan ke Antrean...' : `Kirim Batch (${selectedClaimIds.length > 0 ? selectedClaimIds.length : readyClaims.length} Klaim)`}</span>
                        </button>
                    ) : (
                        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
                            Pengiriman E-Claim memerlukan izin <span className="font-semibold text-slate-700">eclaim.sync</span> (verifikator medis / admin).
                        </div>
                    )}
                </div>

                {stats.mode === 'simulasi_lokal' && (
                    <div className="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900">
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <div className="text-xs">
                            <div className="font-semibold">Mode simulasi lokal</div>
                            <p className="mt-0.5 text-amber-800">
                                Kredensial BPJS belum dikonfigurasi. Batch yang masuk antrean hanya mencatat
                                simulasi lokal dan tidak mengklaim klaim telah diterima BPJS.
                            </p>
                        </div>
                    </div>
                )}

                {/* Metric Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                        <div className="p-2.5 rounded-lg bg-teal-50 text-teal-600">
                            <FileCheck className="w-5 h-5" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500">Siap Dikirim</span>
                            <div className="text-xl font-bold text-slate-900">{readyClaims.length} klaim</div>
                        </div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                        <div className="p-2.5 rounded-lg bg-emerald-50 text-emerald-600">
                            <CheckCircle2 className="w-5 h-5" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500">Telah Disinkronkan</span>
                            <div className="text-xl font-bold text-emerald-600">{stats.totalSynced} klaim</div>
                        </div>
                    </div>

                    <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
                        <div className="p-2.5 rounded-lg bg-sky-50 text-sky-600">
                            <Layers className="w-5 h-5" />
                        </div>
                        <div>
                            <span className="text-xs text-slate-500">Total Pengiriman Batch</span>
                            <div className="text-xl font-bold text-slate-900">{stats.totalBatch} batch</div>
                        </div>
                    </div>
                </div>

                {/* Ready to Sync Table */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden space-y-3 p-5">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h3 className="font-bold text-slate-800 text-sm">Daftar Klaim Siap Sinkronisasi</h3>
                            <p className="text-[11px] text-slate-400">Pilih klaim yang akan di-bundle ke dalam batch pengiriman E-Claim.</p>
                        </div>
                        {readyClaims.length > 0 && (
                            <button
                                type="button"
                                onClick={toggleSelectAll}
                                className="text-xs font-semibold text-teal-600 hover:text-teal-800"
                            >
                                {selectedClaimIds.length === readyClaims.length ? 'Batalkan Semua' : 'Pilih Semua'}
                            </button>
                        )}
                    </div>

                    {readyClaims.length === 0 ? (
                        <div className="py-8 text-center text-slate-400 text-xs">
                            Semua klaim yang siap diajukan telah tersinkronisasi ke server E-Claim.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                    <tr className="text-[11px] text-slate-400 uppercase border-b border-slate-100 font-medium">
                                        <th className="py-2.5 px-3 font-medium w-8">
                                            <input
                                                type="checkbox"
                                                checked={selectedClaimIds.length === readyClaims.length && readyClaims.length > 0}
                                                onChange={toggleSelectAll}
                                                className="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                            />
                                        </th>
                                        <th className="py-2.5 px-3 font-medium">PASIEN / NO. RM</th>
                                        <th className="py-2.5 px-3 font-medium">NO. SEP</th>
                                        <th className="py-2.5 px-3 font-medium">DIAGNOSIS (ICD-10)</th>
                                        <th className="py-2.5 px-3 font-medium">KODE CBG</th>
                                        <th className="py-2.5 px-3 font-medium text-right">TARIF INA-CBGS</th>
                                        <th className="py-2.5 px-3 font-medium text-center">STATUS SYNC</th>
                                        <th className="py-2.5 px-3 font-medium text-center">AKSI</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {readyClaims.map((claim) => (
                                        <tr key={claim.id} className="hover:bg-slate-50 transition">
                                            <td className="py-3 px-3">
                                                <input
                                                    type="checkbox"
                                                    checked={selectedClaimIds.includes(claim.id)}
                                                    onChange={() => toggleSelectClaim(claim.id)}
                                                    className="rounded border-slate-300 text-teal-600 focus:ring-teal-500"
                                                />
                                            </td>
                                            <td className="py-3 px-3">
                                                <div className="font-semibold text-slate-800">{claim.patient?.nama_lengkap}</div>
                                                <div className="text-[10px] text-slate-400 font-mono">{claim.patient?.no_rm}</div>
                                            </td>
                                            <td className="py-3 px-3 font-mono text-slate-600">{claim.no_sep}</td>
                                            <td className="py-3 px-3 text-slate-700">{claim.diagnosa_primer}</td>
                                            <td className="py-3 px-3 font-mono font-bold text-teal-700">{claim.grouper_code || '-'}</td>
                                            <td className="py-3 px-3 text-right font-bold text-slate-900">
                                                Rp{Number(claim.estimasi_biaya).toLocaleString('id-ID')}
                                            </td>
                                            <td className="py-3 px-3 text-center">
                                                {claim.is_synced_eclaim ? (
                                                    <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700">Tersinkron</span>
                                                ) : (
                                                    <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-50 text-amber-700">Antrean Sync</span>
                                                )}
                                            </td>
                                            <td className="py-3 px-3 text-center">
                                                {canSync ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => handleSyncSingle(claim.id)}
                                                        className="text-teal-600 hover:text-teal-800 font-medium"
                                                    >
                                                        Sync Sekarang
                                                    </button>
                                                ) : (
                                                    <span className="text-[10px] text-slate-400">Perlu izin sinkron</span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {/* Batch Sync History */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
                    <h3 className="font-bold text-slate-800 text-sm">Riwayat Batch Pengiriman E-Claim</h3>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs">
                            <thead>
                                <tr className="text-[11px] text-slate-400 uppercase border-b border-slate-100 font-medium">
                                    <th className="py-2.5 font-medium">KODE BATCH</th>
                                    <th className="py-2.5 font-medium">WAKTU PENGIRIMAN</th>
                                    <th className="py-2.5 font-medium">PENGIRIM</th>
                                    <th className="py-2.5 font-medium text-center">JUMLAH KLAIM</th>
                                    <th className="py-2.5 font-medium text-center">STATUS</th>
                                    <th className="py-2.5 font-medium text-center">AKSI</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {syncHistory.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="py-8 text-center text-slate-400">
                                            Belum ada batch sinkronisasi.
                                        </td>
                                    </tr>
                                ) : syncHistory.map((batch) => (
                                    <tr key={batch.id} className="hover:bg-slate-50 transition">
                                        <td className="py-3 font-mono font-bold text-slate-800">{batch.batch_code}</td>
                                        <td className="py-3 text-slate-600">
                                            {batch.started_at || batch.created_at
                                                ? new Date(batch.started_at || batch.created_at || '').toLocaleString('id-ID')
                                                : '-'}
                                        </td>
                                        <td className="py-3 text-slate-700">{batch.initiator?.name || '-'}</td>
                                        <td className="py-3 text-center">
                                            <span className="font-semibold">{batch.success_count}</span>
                                            <span className="text-slate-400"> / {batch.total_items}</span>
                                            {batch.failed_count > 0 && (
                                                <span className="ml-1 text-[11px] font-semibold text-rose-600">
                                                    ({batch.failed_count} gagal)
                                                </span>
                                            )}
                                        </td>
                                        <td className="py-3 text-center">{batchStatusBadge(batch.status)}</td>
                                        <td className="py-3 text-center">
                                            {batch.status === 'failed' && canSync ? (
                                                <button
                                                    type="button"
                                                    onClick={() => handleRetryBatch(batch.id)}
                                                    disabled={retryingBatchId === batch.id}
                                                    className="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700 transition hover:bg-amber-100 disabled:opacity-50"
                                                >
                                                    <RotateCw className={`h-3.5 w-3.5 ${retryingBatchId === batch.id ? 'animate-spin' : ''}`} />
                                                    Ulangi Gagal
                                                </button>
                                            ) : (
                                                <span className="text-slate-400">-</span>
                                            )}
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
