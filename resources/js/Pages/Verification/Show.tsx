import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Claim, ClaimDiagnosis, ClaimProcedure, ClaimRisk, ClaimVerification, PageProps } from '@/types';
import {
    ArrowLeft,
    CheckCircle2,
    AlertTriangle,
    FileText,
    Download,
    UploadCloud,
    Search,
    RefreshCw,
    ShieldCheck,
    Check,
    X
} from 'lucide-react';

interface VclaimResult {
    metaData?: { code?: string; message?: string };
    response?: {
        noSep?: string;
        tglSep?: string;
        noRujukan?: string;
        ppkPerujuk?: string;
        jnsPelayanan?: string;
        diagnosa?: string;
        peserta?: {
            nama?: string;
            noKartu?: string;
            jnsPeserta?: string;
            hakKelas?: string;
            statusPeserta?: { keterangan?: string };
        };
    };
    simulated?: boolean;
}

interface VerificationShowProps {
    claim: Claim;
    currentStep: 'v-claim' | 'pra-klaim' | 'icd-10' | 'icd-9' | 'risiko-klaim' | 'tinjauan-akhir';
    stepMeta: {
        title: string;
        sub: string;
        desc: string;
        order: number;
        stage?: string;
    };
    vclaimResult: VclaimResult;
    steps: string[];
    unlockedSteps: string[];
    diagnoses: ClaimDiagnosis[];
    procedures: ClaimProcedure[];
    isReadyForSubmission: boolean;
}

export default function VerificationShow({
    claim,
    currentStep,
    stepMeta,
    vclaimResult,
    unlockedSteps,
    diagnoses,
    procedures,
    isReadyForSubmission,
}: VerificationShowProps) {
    const { auth } = usePage<PageProps>().props;
    const canVerify = auth.permissions.includes('claims.verify') || auth.isAdmin;
    const canApprove = auth.permissions.includes('claims.approve') || auth.isAdmin;

    const [previewDoc, setPreviewDoc] = useState<{ title: string; filename: string; url?: string | null } | null>(null);
    const [sepInput, setSepInput] = useState(claim.no_sep);
    const [isCheckingSep, setIsCheckingSep] = useState(false);
    const [resumeFile, setResumeFile] = useState<File | null>(null);
    const [resumeNote, setResumeNote] = useState('');
    const [revisionNote, setRevisionNote] = useState('');
    const [processingStage, setProcessingStage] = useState<string | null>(null);
    const [riskDrafts, setRiskDrafts] = useState<Record<number, { status: string; catatan: string }>>({});

    const stepsList = [
        { id: 'v-claim', label: 'BPJS V-Claim', num: 1 },
        { id: 'pra-klaim', label: 'Pra-Klaim', num: 2 },
        { id: 'icd-10', label: 'ICD-10', num: 3 },
        { id: 'icd-9', label: 'ICD-9-CM', num: 4 },
        { id: 'risiko-klaim', label: 'Risiko Klaim', num: 5 },
        { id: 'tinjauan-akhir', label: 'Tinjauan Akhir', num: 6 },
    ];

    const stageByStep: Record<VerificationShowProps['currentStep'], ClaimVerification['tahap']> = {
        'v-claim': 'vclaim',
        'pra-klaim': 'pra_klaim',
        'icd-10': 'icd10',
        'icd-9': 'icd9cm',
        'risiko-klaim': 'risiko',
        'tinjauan-akhir': 'tinjauan_akhir',
    };

    const verificationFor = (tahap: ClaimVerification['tahap']) =>
        claim.verifications?.find((item) => item.tahap === tahap);
    const isStageComplete = (tahap: ClaimVerification['tahap']) =>
        ['lolos', 'lengkap'].includes(verificationFor(tahap)?.status ?? '');
    const summaryStatus = (tahap: ClaimVerification['tahap'], successText: string) =>
        isStageComplete(tahap)
            ? `✓ ${successText}`
            : `⚠️ ${verificationFor(tahap)?.catatan || 'Belum terverifikasi'}`;

    const isStepUnlocked = (id: string) => unlockedSteps.includes(id);

    const stageBadge = (status?: string) => {
        if (['lolos', 'lengkap'].includes(status ?? '')) {
            return { text: 'Lolos', className: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
        }

        if (status === 'gagal' || status === 'tidak_lengkap' || status === 'perlu_revisi') {
            return { text: 'Perlu tindak lanjut', className: 'bg-amber-100 text-amber-800 border-amber-200' };
        }

        return { text: 'Belum divalidasi', className: 'bg-slate-100 text-slate-600 border-slate-200' };
    };

    const runValidation = (
        routeName: 'verifikasi.validate-icd10' | 'verifikasi.validate-icd9' | 'verifikasi.evaluate-risks',
        stage: string,
    ) => {
        router.post(route(routeName, claim.id), {}, {
            preserveScroll: true,
            onStart: () => setProcessingStage(stage),
            onFinish: () => setProcessingStage(null),
        });
    };

    const submitRiskResolution = (risk: ClaimRisk) => {
        const draft = riskDrafts[risk.id];

        if (!canVerify || !draft?.catatan.trim()) {
            return;
        }

        router.post(route('verifikasi.resolve-risk', { claim: claim.id, risk: risk.id }), {
            status: draft.status,
            catatan: draft.catatan.trim(),
        }, {
            preserveScroll: true,
            onStart: () => setProcessingStage(`risk-${risk.id}`),
            onFinish: () => setProcessingStage(null),
        });
    };

    const navigationButton = (target: string, label: string) => {
        const className = 'px-4 py-2 rounded-lg text-xs font-medium flex items-center gap-1.5 shadow-sm transition';

        return isStepUnlocked(target) ? (
            <Link href={route('verifikasi.show', { claim: claim.id, step: target })} className={`${className} bg-teal-700 hover:bg-teal-800 text-white`}>
                <span>{label}</span>
                <span>→</span>
            </Link>
        ) : (
            <span className={`${className} bg-slate-100 text-slate-400 cursor-not-allowed`} title="Selesaikan tahap sebelumnya terlebih dahulu">
                <span>{label}</span>
                <span>→</span>
            </span>
        );
    };

    const vclaimVerification = verificationFor('vclaim');
    const participant = vclaimResult?.response?.peserta;
    const isSimulated = Boolean(vclaimResult?.simulated);
    const isVclaimValid = vclaimResult?.metaData?.code === '200' && isStageComplete('vclaim');
    const isVclaimConfirmedByBpjs = isVclaimValid && !isSimulated;

    const submitSepCheck = () => {
        router.post(route('verifikasi.check-sep', claim.id), { sep: sepInput }, {
            preserveScroll: true,
            onStart: () => setIsCheckingSep(true),
            onFinish: () => setIsCheckingSep(false),
        });
    };

    const handleCheckSep = (e: React.FormEvent) => {
        e.preventDefault();
        submitSepCheck();
    };

    const handleUploadSignedResume = () => {
        if (!resumeFile || !canVerify) {
            return;
        }

        const data = new FormData();
        data.append('tipe_dokumen', 'resume_medis');
        data.append('dokumen', resumeFile);
        if (resumeNote.trim() !== '') {
            data.append('catatan', resumeNote.trim());
        }

        router.post(route('verifikasi.upload-resume', claim.id), data, {
            preserveScroll: true,
            forceFormData: true,
            onFinish: () => {
                setResumeFile(null);
                setResumeNote('');
            },
        });
    };

    const handleMarkReady = () => {
        if (!canApprove || !isReadyForSubmission || claim.status === 'siap_diajukan') {
            return;
        }

        router.post(route('verifikasi.mark-ready', claim.id), {}, {
            preserveScroll: true,
        });
    };

    const handleReturnForRevision = () => {
        if (!canVerify || revisionNote.trim() === '') {
            return;
        }

        router.post(route('verifikasi.return-revision', claim.id), { catatan: revisionNote.trim() }, {
            preserveScroll: true,
            onFinish: () => setRevisionNote(''),
        });
    };

    const documentMeta = [
        { key: 'sep', tipe: 'sep', name: 'Surat Eligibilitas Peserta' },
        { key: 'rujukan', tipe: 'rujukan', name: 'Surat Rujukan FKTP' },
        { key: 'resume', tipe: 'resume_medis', name: 'Resume Medis Pasien Pulang' },
        { key: 'billing', tipe: 'rincian_biaya', name: 'Rincian Biaya Rumah Sakit' },
        { key: 'penunjang', tipe: 'penunjang', name: 'Hasil Pemeriksaan Penunjang' },
    ];

    const docs = documentMeta.map((meta) => {
        const doc = claim.documents?.find((item) => item.tipe_dokumen === meta.tipe);
        const isComplete = doc
            ? ['lengkap', 'lolos'].includes(doc.status_verifikasi)
            : false;

        return {
            ...meta,
            id: doc?.id,
            check: doc?.catatan_verifikasi ?? (isComplete ? 'Berkas terverifikasi' : 'Berkas perlu dilengkapi'),
            file: doc?.nama_file ?? 'Belum diunggah',
            size: doc?.ukuran_file ? `${Math.round(doc.ukuran_file / 1024)} KB` : '-',
            status: isComplete,
            note: doc?.catatan_verifikasi,
            url: doc?.path_file && doc.id
                ? route('verifikasi.document', { claim: claim.id, document: doc.id })
                : null,
        };
    });

    const completedDocsCount = docs.filter((doc) => doc.status).length;
    const isAllDocsComplete = completedDocsCount === docs.length;
    const activeRisks = claim.risks?.filter((risk) =>
        !['terselesaikan', 'diterima_risiko'].includes(risk.status_tindak_lanjut),
    ) ?? [];

    return (
        <AuthenticatedLayout breadcrumbCurrent={stepMeta.title}>
            <Head title={`${stepMeta.title} - ${claim.patient?.nama_lengkap} (${claim.nomor_klaim})`} />

            <div className="space-y-5">
                {/* Workflow Top Bar */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Link
                            href={route('klaim.index')}
                            className="px-2.5 py-1.5 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-xs font-medium flex items-center gap-1.5 transition"
                        >
                            <ArrowLeft className="w-3.5 h-3.5" />
                            <span>Kembali ke daftar</span>
                        </Link>
                        <span className="text-xs text-slate-400">|</span>
                        <span className="text-xs text-slate-500 font-medium">{stepMeta.sub}</span>
                    </div>

                    <div className="flex items-center gap-2">
                        <a
                            href={route('dashboard.summary.download')}
                            className="px-2.5 py-1.5 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-lg text-xs font-medium flex items-center gap-1.5 transition"
                        >
                            <Download className="w-3.5 h-3.5 text-slate-500" />
                            <span>Unduh ringkasan</span>
                        </a>
                    </div>
                </div>

                {/* Workflow Page Title */}
                <div>
                    <h2 className="text-2xl font-bold text-slate-900 tracking-tight">{stepMeta.title}</h2>
                    <p className="text-xs text-slate-500 mt-0.5">{stepMeta.desc}</p>
                </div>

                {/* Selected Patient Info Card */}
                <div className="bg-white p-4 rounded-xl border border-slate-200/90 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-lg bg-teal-100/70 border border-teal-200 text-teal-800 font-bold flex items-center justify-center text-sm shrink-0">
                            {claim.patient?.nama_lengkap?.split(' ').map((n) => n[0]).join('').substring(0, 2).toUpperCase()}
                        </div>
                        <div>
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-slate-900 text-sm">{claim.patient?.nama_lengkap}</span>
                                <span className="text-xs text-slate-500">{claim.patient?.jenis_kelamin} · {claim.patient?.usia} tahun</span>
                            </div>
                            <div className="text-[11px] text-slate-500 flex flex-wrap items-center gap-2 mt-0.5">
                                <span>{claim.patient?.no_rm}</span>
                                <span>·</span>
                                <span>{claim.nomor_klaim}</span>
                                <span>·</span>
                                <span>SEP <strong className="font-medium text-slate-700 font-mono">{claim.no_sep}</strong></span>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-6 text-xs border-t md:border-t-0 pt-2 md:pt-0 border-slate-100">
                        <div>
                            <span className="text-slate-400 text-[10px] block">Perawatan & Tanggal</span>
                            <span className="font-medium text-slate-700">{claim.care_type} · {claim.tanggal_masuk}</span>
                        </div>
                        <div>
                            <span className="text-slate-400 text-[10px] block">Diagnosis</span>
                            <span className="font-medium text-slate-700">{claim.diagnosa_primer}</span>
                        </div>
                        <div>
                            <span className="text-slate-400 text-[10px] block">Estimasi Klaim</span>
                            <span className="font-bold text-teal-700">Rp{Number(claim.estimasi_biaya).toLocaleString('id-ID')}</span>
                        </div>
                        <div>
                            <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full font-medium text-xs border ${
                                claim.status === 'bermasalah' ? 'bg-amber-50 text-amber-700 border-amber-200' :
                                claim.status === 'siap_diajukan' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-sky-50 text-sky-700 border-sky-200'
                            }`}>
                                <span className={`w-1.5 h-1.5 rounded-full ${
                                    claim.status === 'bermasalah' ? 'bg-amber-500' :
                                    claim.status === 'siap_diajukan' ? 'bg-emerald-500' : 'bg-sky-500'
                                }`} />
                                <span>{claim.status}</span>
                            </span>
                        </div>
                    </div>
                </div>

                {/* STEPPER PROGRESS TABS (Interactive 6 Steps) */}
                <div className="bg-white px-2 py-2 rounded-xl border border-slate-200/90 shadow-sm flex items-center justify-between overflow-x-auto text-xs">
                    {stepsList.map((step, idx) => {
                        const isActive = step.id === currentStep;
                        const isComplete = isStageComplete(stageByStep[step.id as VerificationShowProps['currentStep']]);
                        const unlocked = isStepUnlocked(step.id);
                        const className = `flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg transition ${
                            isActive
                                ? 'bg-teal-50 text-teal-800 font-bold'
                                : isComplete && unlocked
                                ? 'text-teal-700 font-medium hover:bg-slate-50'
                                : unlocked
                                ? 'text-slate-500 hover:text-slate-800 hover:bg-slate-50'
                                : 'text-slate-400 opacity-60 cursor-not-allowed'
                        }`;
                        const content = (
                            <>
                                <span className={`w-5 h-5 rounded-full text-[11px] font-bold flex items-center justify-center ${
                                    isActive
                                        ? 'bg-teal-600 text-white'
                                        : isComplete && unlocked
                                        ? 'bg-emerald-500 text-white'
                                        : 'bg-slate-200 text-slate-700'
                                }`}>
                                    {isComplete && unlocked && !isActive ? '✓' : step.num}
                                </span>
                                <span className="whitespace-nowrap">{step.label}</span>
                            </>
                        );

                        return (
                            <div key={step.id} className="flex-1 flex items-center">
                                {unlocked ? (
                                    <Link href={route('verifikasi.show', { claim: claim.id, step: step.id })} className={className}>
                                        {content}
                                    </Link>
                                ) : (
                                    <span className={className} title="Selesaikan tahap sebelumnya terlebih dahulu">
                                        {content}
                                    </span>
                                )}
                                {idx < stepsList.length - 1 && <span className="text-slate-300 px-1">›</span>}
                            </div>
                        );
                    })}
                </div>

                {/* ========================================================= */}
                {/* STEP 1: BPJS V-CLAIM */}
                {/* ========================================================= */}
                {currentStep === 'v-claim' && (
                    <div className="space-y-5 animate-fadeIn">
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            {/* Left 2 Cols: Search SEP & Result */}
                            <div className="lg:col-span-2 space-y-5">
                                <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                                    <div>
                                        <h3 className="font-bold text-slate-800 text-sm">Pencarian SEP</h3>
                                        <p className="text-[11px] text-slate-400">Periksa nomor SEP pada klaim ini. Tanpa kredensial BPJS, hasilnya adalah simulasi lokal.</p>
                                    </div>
                                    <form onSubmit={handleCheckSep} className="flex flex-col sm:flex-row gap-3">
                                        <div className="flex-1 flex gap-2">
                                            <input
                                                type="text"
                                                value={sepInput}
                                                onChange={(e) => setSepInput(e.target.value)}
                                                className="flex-1 px-3 py-2 bg-white border border-slate-300 rounded-lg text-xs font-mono font-medium focus:ring-1 focus:ring-teal-500"
                                            />
                                            <button
                                                type="submit"
                                                disabled={isCheckingSep || !canVerify}
                                                className="px-4 py-2 bg-teal-700 hover:bg-teal-800 text-white rounded-lg text-xs font-medium flex items-center gap-1.5 transition shadow-sm disabled:opacity-50"
                                            >
                                                <Search className={`w-3.5 h-3.5 ${isCheckingSep ? 'animate-spin' : ''}`} />
                                                <span>{isCheckingSep ? 'Memeriksa...' : 'Periksa SEP'}</span>
                                            </button>
                                        </div>
                                    </form>
                                    <div className="text-[11px] text-slate-400 flex items-center gap-1.5">
                                        <ShieldCheck className="w-3.5 h-3.5 text-teal-600" />
                                        <span>Jika kredensial BPJS tersedia, pencarian memakai V-Claim; jika tidak, hasilnya simulasi lokal.</span>
                                    </div>
                                </div>

                                {/* Hasil Penelusuran SEP */}
                                <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                        <h3 className="font-bold text-slate-800 text-sm">Hasil penelusuran SEP</h3>
                                        <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium border ${
                                            isVclaimConfirmedByBpjs
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                : 'bg-amber-50 text-amber-700 border-amber-200'
                                        }`}>
                                            <span className={`w-1.5 h-1.5 rounded-full ${isVclaimConfirmedByBpjs ? 'bg-emerald-500' : 'bg-amber-500'}`} />
                                            {isVclaimConfirmedByBpjs ? 'SEP valid' : (isVclaimValid ? 'Data lokal' : 'Belum terverifikasi')}
                                            {isSimulated && ' · simulasi lokal'}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 text-xs">
                                        <div>
                                            <span className="text-[11px] text-slate-400 block">Nomor SEP</span>
                                            <span className="font-mono font-medium text-slate-800">{vclaimResult?.response?.noSep || claim.no_sep}</span>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-slate-400 block">Tanggal terbit SEP</span>
                                            <span className="font-medium text-slate-800">{vclaimResult?.response?.tglSep || claim.tanggal_masuk}</span>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-slate-400 block">Nomor rujukan</span>
                                            <span className="font-mono text-slate-700">{vclaimResult?.response?.noRujukan || claim.no_rujukan || '-'}</span>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-slate-400 block">Fasilitas perujuk</span>
                                            <span className="font-medium text-slate-700">{vclaimResult?.response?.ppkPerujuk || claim.patient?.faskes_tingkat_pertama || '-'}</span>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-slate-400 block">Jenis pelayanan / kelas</span>
                                            <span className="font-medium text-slate-700">{vclaimResult?.response?.jnsPelayanan || claim.care_type}</span>
                                        </div>
                                        <div>
                                            <span className="text-[11px] text-slate-400 block">Diagnosis pada SEP</span>
                                            <span className="font-medium text-slate-700">{vclaimResult?.response?.diagnosa || claim.diagnosa_primer}</span>
                                        </div>
                                    </div>

                                    <div className={`p-3 rounded-lg flex items-start gap-2 text-xs ${
                                        isVclaimConfirmedByBpjs
                                            ? 'bg-emerald-50/70 border border-emerald-200/70 text-emerald-900'
                                            : 'bg-amber-50/70 border border-amber-200/70 text-amber-900'
                                    }`}>
                                        <CheckCircle2 className={`w-4 h-4 shrink-0 mt-0.5 ${isVclaimConfirmedByBpjs ? 'text-emerald-600' : 'text-amber-600'}`} />
                                        <div>
                                            <strong className="font-semibold block">
                                                {isVclaimValid
                                                    ? (isSimulated ? 'Data SEP dari database lokal (simulasi)' : 'Data SEP sesuai dengan episode pasien')
                                                    : 'SEP belum terverifikasi'}
                                            </strong>
                                            <span className={`text-[11px] ${isVclaimValid ? 'text-emerald-700' : 'text-amber-700'}`}>
                                                {vclaimVerification?.catatan || vclaimResult?.metaData?.message || 'Periksa SEP untuk menyimpan hasil verifikasi.'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Right Col: Verifikasi Kepesertaan */}
                            <div className="space-y-5">
                                <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                        <div>
                                            <h3 className="font-bold text-slate-800 text-sm">Verifikasi kepesertaan</h3>
                                            <p className="text-[10px] text-slate-400">
                                                {vclaimVerification?.verified_at
                                                    ? `Terverifikasi · ${new Date(vclaimVerification.verified_at).toLocaleString('id-ID')}`
                                                    : 'Belum ada rekaman verifikasi'}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={submitSepCheck}
                                            disabled={!canVerify || isCheckingSep}
                                            className="text-xs text-teal-600 hover:text-teal-700 font-medium flex items-center gap-1 disabled:opacity-50"
                                        >
                                            <RefreshCw className={`w-3.5 h-3.5 ${isCheckingSep ? 'animate-spin' : ''}`} />
                                            Periksa ulang
                                        </button>
                                    </div>

                                    <div className={`p-3 border rounded-lg flex items-center gap-3 ${
                                        isVclaimConfirmedByBpjs
                                            ? 'bg-emerald-50 border-emerald-200'
                                            : 'bg-amber-50 border-amber-200'
                                    }`}>
                                        <div className={`w-8 h-8 rounded-full text-white flex items-center justify-center font-bold ${
                                            isVclaimConfirmedByBpjs ? 'bg-emerald-500' : 'bg-amber-500'
                                        }`}>
                                            {isVclaimConfirmedByBpjs ? '✓' : '!'}
                                        </div>
                                        <div>
                                            <h4 className={`font-bold text-xs ${isVclaimConfirmedByBpjs ? 'text-emerald-900' : 'text-amber-900'}`}>
                                                {participant?.statusPeserta?.keterangan
                                                    ? `Peserta ${participant.statusPeserta.keterangan.toLowerCase()}`
                                                    : isVclaimConfirmedByBpjs ? 'Peserta aktif' : 'Status peserta belum dikonfirmasi'}
                                            </h4>
                                            <p className={`text-[11px] ${isVclaimConfirmedByBpjs ? 'text-emerald-700' : 'text-amber-700'}`}>
                                                {participant?.hakKelas
                                                    ? `Hak layanan ${participant.hakKelas}`
                                                    : 'Periksa SEP untuk memuat data kepesertaan'}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="space-y-2 text-xs">
                                        <div className="flex justify-between">
                                            <span className="text-slate-400">Nama peserta</span>
                                            <span className="font-semibold text-slate-800">{participant?.nama || claim.patient?.nama_lengkap}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-400">No. kartu BPJS</span>
                                            <span className="font-mono text-slate-700">{participant?.noKartu || claim.patient?.no_kis || '-'}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-400">Segmen peserta</span>
                                            <span className="font-medium text-slate-700">{participant?.jnsPeserta || claim.patient?.segmen_peserta || '-'}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-slate-400">Hak kelas</span>
                                            <span className="font-medium text-slate-700">{participant?.hakKelas || claim.kelas_rawat}</span>
                                        </div>
                                    </div>

                                    <div className="pt-3 border-t border-slate-100 space-y-2 text-xs text-slate-700">
                                        <div className="flex items-center gap-2">
                                            <span className={`w-4 h-4 rounded-full flex items-center justify-center text-[10px] ${isVclaimConfirmedByBpjs ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500'}`}>
                                                {isVclaimConfirmedByBpjs ? '✓' : '-'}
                                            </span>
                                            <span>Identitas pasien sesuai</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className={`w-4 h-4 rounded-full flex items-center justify-center text-[10px] ${isVclaimConfirmedByBpjs ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500'}`}>
                                                {isVclaimConfirmedByBpjs ? '✓' : '-'}
                                            </span>
                                            <span>Status aktif pada tanggal pelayanan</span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className={`w-4 h-4 rounded-full flex items-center justify-center text-[10px] ${isVclaimValid ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-500'}`}>
                                                {isVclaimValid ? '✓' : '-'}
                                            </span>
                                            <span>
                                                {vclaimResult?.simulated
                                                    ? 'Pencocokan berdasarkan simulasi lokal'
                                                    : 'Pencocokan berdasarkan V-Claim BPJS'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Bottom Actions */}
                        <div className="p-4 bg-white rounded-xl border border-slate-200/90 shadow-sm flex items-center justify-between">
                            <span className="text-xs text-slate-500">
                                {isVclaimValid
                                    ? `Tahap 1 selesai · ${vclaimResult?.simulated ? 'Terverifikasi via simulasi lokal.' : 'Terkonfirmasi pada V-Claim BPJS.'}`
                                    : 'Periksa SEP untuk menyimpan hasil verifikasi tahap 1.'}
                            </span>
                            {navigationButton('pra-klaim', 'Lanjut ke Pra-Klaim')}
                        </div>
                    </div>
                )}

                {/* ========================================================= */}
                {/* STEP 2: VERIFIKASI PRA-KLAIM */}
                {/* ========================================================= */}
                {currentStep === 'pra-klaim' && (
                    <div className="space-y-5 animate-fadeIn">
                        {/* Top Alert */}
                        <div className={`p-4 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-4 ${
                            isAllDocsComplete ? 'bg-emerald-50/80 border-emerald-200 text-emerald-900' : 'bg-amber-50/80 border-amber-200 text-amber-900'
                        }`}>
                            <div className="flex items-start gap-3">
                                <AlertTriangle className={`w-5 h-5 shrink-0 mt-0.5 ${isAllDocsComplete ? 'text-emerald-600' : 'text-amber-600'}`} />
                                <div>
                                    <strong className="font-bold text-xs block">
                                        {isAllDocsComplete
                                            ? 'Semua dokumen wajib telah lengkap (5/5)'
                                            : `${docs.length - completedDocsCount} dokumen belum lengkap — pengajuan klaim ditahan`}
                                    </strong>
                                    <p className="text-[11px] opacity-90 mt-0.5">
                                        {isAllDocsComplete
                                            ? 'Seluruh berkas wajib tersedia dan telah diverifikasi.'
                                            : 'Lengkapi berkas yang belum tersedia untuk melanjutkan verifikasi.'}
                                    </p>
                                </div>
                            </div>
                            {!isAllDocsComplete && canVerify && (
                                <div className="flex flex-col gap-2 sm:items-end">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            onChange={(event) => setResumeFile(event.target.files?.[0] ?? null)}
                                            className="text-[11px] max-w-[220px] file:mr-2 file:rounded-lg file:border-0 file:bg-white file:px-2.5 file:py-1.5 file:text-[11px] file:font-semibold file:text-amber-700"
                                        />
                                        <button
                                            type="button"
                                            onClick={handleUploadSignedResume}
                                            disabled={!resumeFile}
                                            className="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-medium rounded-lg shrink-0 flex items-center gap-1.5 shadow-sm transition disabled:opacity-50"
                                        >
                                            <UploadCloud className="w-3.5 h-3.5" />
                                            <span>Unggah revisi</span>
                                        </button>
                                    </div>
                                    <input
                                        type="text"
                                        value={resumeNote}
                                        onChange={(event) => setResumeNote(event.target.value)}
                                        placeholder="Catatan opsional untuk verifikator"
                                        className="w-full sm:w-[300px] px-2.5 py-1.5 border border-amber-200 rounded-lg text-[11px] bg-white/80"
                                    />
                                    <span className="text-[10px] text-amber-700">PDF/JPG/PNG · maksimal 5 MB.</span>
                                </div>
                            )}
                        </div>

                        {/* Documents Table Card */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h3 className="font-bold text-slate-800 text-sm">Dokumen pendukung</h3>
                                    <p className="text-[11px] text-slate-400">5 dokumen wajib · PDF/JPG/PNG · Maksimum 5 MB per berkas</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs font-semibold text-slate-700">{completedDocsCount} dari 5 lengkap</span>
                                    <div className="w-24 bg-slate-100 h-2 rounded-full overflow-hidden">
                                        <div
                                            className="bg-teal-500 h-full transition-all duration-500"
                                            style={{ width: `${(completedDocsCount / 5) * 100}%` }}
                                        />
                                    </div>
                                    <span className="text-[11px] font-semibold text-teal-600">
                                        {Math.round((completedDocsCount / 5) * 100)}%
                                    </span>
                                </div>
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs">
                                    <thead>
                                        <tr className="text-[11px] text-slate-400 uppercase border-b border-slate-100 font-medium">
                                            <th className="py-2.5 font-medium">DOKUMEN / BERKAS</th>
                                            <th className="py-2.5 font-medium">PEMERIKSAAN</th>
                                            <th className="py-2.5 font-medium">STATUS</th>
                                            <th className="py-2.5 font-medium text-right">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {docs.map((doc) => (
                                            <tr key={doc.key} className="hover:bg-slate-50/70">
                                                <td className="py-3 pr-2">
                                                    <div className="flex items-center gap-2.5">
                                                        <FileText className="w-5 h-5 text-red-500 shrink-0" />
                                                        <div>
                                                            <div className="font-semibold text-slate-800">{doc.name}</div>
                                                            <div className="text-[10px] text-slate-400">{doc.file} · {doc.size}</div>
                                                            {doc.note && (
                                                                <div className="text-[10px] text-amber-600 mt-0.5">{doc.note}</div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="py-3 text-slate-600">{doc.check}</td>
                                                <td className="py-3">
                                                    {doc.status ? (
                                                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-600">
                                                            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" /> Lengkap
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600">
                                                            <span className="w-1.5 h-1.5 rounded-full bg-amber-500" /> Perlu revisi
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="py-3 text-right">
                                                    {doc.url ? (
                                                        <button
                                                            type="button"
                                                            onClick={() => setPreviewDoc({ title: doc.name, filename: doc.file, url: doc.url })}
                                                            className="text-teal-600 hover:text-teal-800 font-medium"
                                                        >
                                                            Lihat berkas
                                                        </button>
                                                    ) : (
                                                        <span className="text-[10px] text-slate-400">Belum ada berkas</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Bottom Actions */}
                        <div className="p-4 bg-white rounded-xl border border-slate-200/90 shadow-sm flex items-center justify-between">
                            <span className="text-xs text-slate-500">
                                {isAllDocsComplete ? `${docs.length} dokumen lengkap · Memenuhi syarat pengajuan.` : `Lengkapi ${docs.length - completedDocsCount} dokumen sebelum diajukan.`}
                            </span>
                            {navigationButton('icd-10', 'Lanjut ke ICD-10')}
                        </div>
                    </div>
                )}

                {/* ========================================================= */}
                {/* STEP 3: VALIDASI ICD-10 */}
                {/* ========================================================= */}
                {currentStep === 'icd-10' && (
                    <div className="space-y-5 animate-fadeIn">
                        <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                            <div className="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                <div>
                                    <h3 className="font-bold text-slate-800 text-sm">Validasi Kode Diagnosis (ICD-10)</h3>
                                    <p className="text-xs text-slate-500">Periksa kode terhadap referensi ICD-10 aktif dan dokumentasi klinis yang tersedia.</p>
                                </div>
                                <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border ${stageBadge(verificationFor('icd10')?.status).className}`}>
                                    {stageBadge(verificationFor('icd10')?.status).text}
                                </span>
                            </div>

                            {diagnoses.length === 0 ? (
                                <div className="p-4 rounded-xl border border-slate-200 bg-slate-50 text-xs text-slate-600">
                                    Belum ada kode diagnosis tersimpan pada klaim ini.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {diagnoses.map((diagnosis) => (
                                        <div key={diagnosis.id} className={`p-4 rounded-xl border flex items-start justify-between gap-3 ${
                                            diagnosis.tipe === 'primer' ? 'border-teal-200 bg-teal-50/40' : 'border-slate-200 bg-slate-50/50'
                                        }`}>
                                            <div>
                                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                                    Diagnosis {diagnosis.tipe}
                                                </span>
                                                <h4 className="font-bold text-slate-900 text-sm mt-0.5">
                                                    <span className="font-mono">{diagnosis.kode_icd}</span> · {diagnosis.deskripsi}
                                                </h4>
                                                {diagnosis.catatan_validasi && (
                                                    <p className="text-[11px] text-amber-700 mt-1">{diagnosis.catatan_validasi}</p>
                                                )}
                                            </div>
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border shrink-0 ${
                                                diagnosis.is_valid
                                                    ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                                    : 'bg-amber-100 text-amber-800 border-amber-200'
                                            }`}>
                                                {diagnosis.is_valid ? 'Sesuai referensi' : 'Perlu diperbaiki'}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {verificationFor('icd10')?.catatan && (
                                <p className="text-[11px] text-slate-600 border-t border-slate-100 pt-3">
                                    Hasil validasi: {verificationFor('icd10')?.catatan}
                                </p>
                            )}
                        </div>

                        <div className="p-4 bg-white rounded-xl border border-slate-200/90 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <button
                                type="button"
                                onClick={() => runValidation('verifikasi.validate-icd10', 'icd10')}
                                disabled={!canVerify || processingStage === 'icd10'}
                                className="px-4 py-2 bg-teal-700 hover:bg-teal-800 text-white rounded-lg text-xs font-medium shadow-sm disabled:opacity-50"
                            >
                                {processingStage === 'icd10' ? 'Memvalidasi…' : 'Jalankan validasi ICD-10'}
                            </button>
                            {navigationButton('icd-9', 'Lanjut ke ICD-9-CM')}
                        </div>
                    </div>
                )}

                {/* ========================================================= */}
                {/* STEP 4: VALIDASI ICD-9-CM */}
                {/* ========================================================= */}
                {currentStep === 'icd-9' && (
                    <div className="space-y-5 animate-fadeIn">
                        <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                            <div className="flex items-center justify-between gap-3 border-b border-slate-100 pb-3">
                                <div>
                                    <h3 className="font-bold text-slate-800 text-sm">Validasi Kode Prosedur (ICD-9-CM)</h3>
                                    <p className="text-xs text-slate-500">Periksa kode tindakan terhadap referensi ICD-9-CM aktif dan dokumentasi tindakan.</p>
                                </div>
                                <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border ${stageBadge(verificationFor('icd9cm')?.status).className}`}>
                                    {stageBadge(verificationFor('icd9cm')?.status).text}
                                </span>
                            </div>

                            {procedures.length === 0 ? (
                                <div className="p-4 rounded-xl border border-slate-200 bg-slate-50 text-xs text-slate-600">
                                    Belum ada kode prosedur tersimpan pada klaim ini.
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {procedures.map((procedure) => (
                                        <div key={procedure.id} className="p-4 rounded-xl border border-slate-200 bg-slate-50/50 flex items-start justify-between gap-3">
                                            <div>
                                                <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500">Prosedur / Tindakan</span>
                                                <h4 className="font-bold text-slate-900 text-sm mt-0.5">
                                                    <span className="font-mono">{procedure.kode_prosedur}</span> · {procedure.deskripsi}
                                                </h4>
                                                {procedure.catatan_validasi && (
                                                    <p className="text-[11px] text-amber-700 mt-1">{procedure.catatan_validasi}</p>
                                                )}
                                            </div>
                                            <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border shrink-0 ${
                                                procedure.is_valid
                                                    ? 'bg-emerald-100 text-emerald-800 border-emerald-200'
                                                    : 'bg-amber-100 text-amber-800 border-amber-200'
                                            }`}>
                                                {procedure.is_valid ? 'Sesuai referensi' : 'Perlu diperbaiki'}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {verificationFor('icd9cm')?.catatan && (
                                <p className="text-[11px] text-slate-600 border-t border-slate-100 pt-3">
                                    Hasil validasi: {verificationFor('icd9cm')?.catatan}
                                </p>
                            )}
                        </div>

                        <div className="p-4 bg-white rounded-xl border border-slate-200/90 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <button
                                type="button"
                                onClick={() => runValidation('verifikasi.validate-icd9', 'icd9cm')}
                                disabled={!canVerify || processingStage === 'icd9cm'}
                                className="px-4 py-2 bg-teal-700 hover:bg-teal-800 text-white rounded-lg text-xs font-medium shadow-sm disabled:opacity-50"
                            >
                                {processingStage === 'icd9cm' ? 'Memvalidasi…' : 'Jalankan validasi ICD-9-CM'}
                            </button>
                            {navigationButton('risiko-klaim', 'Lanjut ke Deteksi Risiko')}
                        </div>
                    </div>
                )}

                {/* ========================================================= */}
                {/* STEP 5: DETEKSI RISIKO KLAIM */}
                {/* ========================================================= */}
                {currentStep === 'risiko-klaim' && (
                    <div className="space-y-5 animate-fadeIn">
                        <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                                <div>
                                    <h3 className="font-bold text-slate-800 text-sm">Penapisan Risiko & Potensi Sengketa</h3>
                                    <p className="text-xs text-slate-400">Tetapkan tindak lanjut untuk setiap temuan sebelum tinjauan akhir.</p>
                                </div>
                                <span className={`px-2.5 py-1 rounded-full text-xs font-semibold border ${
                                    activeRisks.length === 0 ? 'bg-emerald-100 text-emerald-800 border-emerald-200' : 'bg-amber-100 text-amber-800 border-amber-200'
                                }`}>
                                    {activeRisks.length === 0 ? '0 Risiko Aktif' : `${activeRisks.length} Risiko Aktif`}
                                </span>
                            </div>

                            {activeRisks.length === 0 ? (
                                <div className="p-4 rounded-xl border border-emerald-200 bg-emerald-50/50 flex items-center gap-3 text-xs text-emerald-800">
                                    <CheckCircle2 className="w-5 h-5 text-emerald-600 shrink-0" />
                                    <div>
                                        <h4 className="font-bold text-emerald-900">Tidak ada temuan risiko aktif</h4>
                                        <p className="text-emerald-700 mt-0.5">Jalankan penapisan untuk menyimpan hasil verifikasi tahap ini.</p>
                                    </div>
                                </div>
                            ) : activeRisks.map((risk) => {
                                const draft = riskDrafts[risk.id] ?? { status: 'terselesaikan', catatan: '' };

                                return (
                                    <div key={risk.id} className="p-4 rounded-xl border border-amber-200 bg-amber-50/50 space-y-3 text-xs">
                                        <div className="flex items-center justify-between gap-3">
                                            <div className="flex items-center gap-2">
                                                <span className="px-2 py-0.5 bg-amber-200 text-amber-900 rounded font-bold text-[10px]">{risk.kode_temuan}</span>
                                                <h4 className="font-bold text-amber-900">{risk.judul_temuan}</h4>
                                            </div>
                                            <span className="text-[11px] font-semibold text-amber-700 shrink-0">Tingkat: {risk.tingkat_risiko}</span>
                                        </div>
                                        <p className="text-amber-800 leading-relaxed">{risk.deskripsi_temuan}</p>
                                        {risk.rekomendasi && (
                                            <p className="text-[11px] text-amber-700">Rekomendasi: {risk.rekomendasi}</p>
                                        )}
                                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-2 border-t border-amber-200/70">
                                            <select
                                                value={draft.status}
                                                onChange={(event) => setRiskDrafts({
                                                    ...riskDrafts,
                                                    [risk.id]: { ...draft, status: event.target.value },
                                                })}
                                                disabled={!canVerify}
                                                className="px-2 py-1.5 border border-amber-200 rounded-lg bg-white text-[11px]"
                                            >
                                                <option value="terselesaikan">Tandai terselesaikan</option>
                                                <option value="diterima_risiko">Terima risiko</option>
                                            </select>
                                            <input
                                                type="text"
                                                value={draft.catatan}
                                                onChange={(event) => setRiskDrafts({
                                                    ...riskDrafts,
                                                    [risk.id]: { ...draft, catatan: event.target.value },
                                                })}
                                                placeholder="Catatan tindak lanjut (wajib)"
                                                disabled={!canVerify}
                                                className="sm:col-span-2 px-2 py-1.5 border border-amber-200 rounded-lg bg-white text-[11px]"
                                            />
                                        </div>
                                        <div className="flex items-center justify-between gap-2">
                                            <Link
                                                href={route('verifikasi.show', { claim: claim.id, step: 'pra-klaim' })}
                                                className="text-[11px] text-amber-700 hover:text-amber-900 font-medium"
                                            >
                                                Periksa kembali berkas di Pra-Klaim
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => submitRiskResolution(risk)}
                                                disabled={!canVerify || draft.catatan.trim() === '' || processingStage === `risk-${risk.id}`}
                                                className="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-[11px] font-medium disabled:opacity-50"
                                            >
                                                {processingStage === `risk-${risk.id}` ? 'Menyimpan…' : 'Simpan tindak lanjut'}
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}

                            {verificationFor('risiko')?.catatan && (
                                <p className="text-[11px] text-slate-600 border-t border-slate-100 pt-3">
                                    Hasil penapisan: {verificationFor('risiko')?.catatan}
                                </p>
                            )}

                            {(claim.risks ?? []).some((risk) => ['terselesaikan', 'diterima_risiko'].includes(risk.status_tindak_lanjut)) && (
                                <div className="border-t border-slate-100 pt-3 space-y-2">
                                    <p className="text-[11px] font-semibold text-slate-600">Riwayat tindak lanjut</p>
                                    {(claim.risks ?? [])
                                        .filter((risk) => ['terselesaikan', 'diterima_risiko'].includes(risk.status_tindak_lanjut))
                                        .map((risk) => (
                                            <p key={risk.id} className="text-[11px] text-slate-500">
                                                <span className="font-mono font-medium text-slate-700">{risk.kode_temuan}</span>
                                                {' · '}
                                                {risk.status_tindak_lanjut === 'terselesaikan' ? 'Terselesaikan' : 'Diterima risiko'}
                                                {risk.catatan_tindak_lanjut ? ` · ${risk.catatan_tindak_lanjut}` : ''}
                                            </p>
                                        ))}
                                </div>
                            )}
                        </div>

                        <div className="p-4 bg-white rounded-xl border border-slate-200/90 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <button
                                type="button"
                                onClick={() => runValidation('verifikasi.evaluate-risks', 'risiko')}
                                disabled={!canVerify || processingStage === 'risiko'}
                                className="px-4 py-2 bg-teal-700 hover:bg-teal-800 text-white rounded-lg text-xs font-medium shadow-sm disabled:opacity-50"
                            >
                                {processingStage === 'risiko' ? 'Memproses…' : 'Jalankan penapisan risiko'}
                            </button>
                            {navigationButton('tinjauan-akhir', 'Lanjut ke Tinjauan Akhir')}
                        </div>
                    </div>
                )}

                {/* ========================================================= */}
                {/* STEP 6: TINJAUAN AKHIR & APPROVAL */}
                {/* ========================================================= */}
                {currentStep === 'tinjauan-akhir' && (
                    <div className="space-y-5 animate-fadeIn">
                        {/* Readiness Banner */}
                        <div className={`p-4 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-4 ${
                            isReadyForSubmission ? 'bg-emerald-50/80 border-emerald-200' : 'bg-amber-50/80 border-amber-200'
                        }`}>
                            <div>
                                <h3 className={`font-bold text-sm ${isReadyForSubmission ? 'text-emerald-900' : 'text-amber-900'}`}>
                                    {isReadyForSubmission ? 'Seluruh tahap verifikasi terpenuhi' : 'Belum memenuhi seluruh tahap verifikasi'}
                                </h3>
                                <p className={`text-[11px] mt-0.5 ${isReadyForSubmission ? 'text-emerald-700' : 'text-amber-700'}`}>
                                    {isReadyForSubmission
                                        ? 'Klaim dapat ditandai siap diajukan, lalu dikirim melalui portal E-Claim.'
                                        : `${docs.length - completedDocsCount} dokumen belum lengkap · ${activeRisks.length} risiko aktif · periksa hasil verifikasi tiap tahap.`}
                                </p>
                            </div>
                            {!isReadyForSubmission && (
                                <Link
                                    href={route('verifikasi.show', { claim: claim.id, step: 'pra-klaim' })}
                                    className="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold rounded-lg shadow-sm transition shrink-0"
                                >
                                    Lengkapi Berkas
                                </Link>
                            )}
                        </div>

                        {/* Summary checklist */}
                        <div className="bg-white p-5 rounded-xl border border-slate-200/90 shadow-sm space-y-4">
                            <div>
                                <h3 className="font-bold text-slate-800 text-sm">Ringkasan Validasi 6 Tahap</h3>
                                <p className="text-[11px] text-slate-400 mt-0.5">Alur QA internal rumah sakit, bukan urutan wajib yang ditetapkan BPJS/Kemenkes.</p>
                            </div>

                            <div className="divide-y divide-slate-100 text-xs">
                                {[
                                    { label: '1. BPJS V-Claim & Kepesertaan', tahap: 'vclaim' as const, success: vclaimResult?.simulated ? 'Lolos (simulasi lokal)' : 'Lolos (V-Claim)' },
                                    { label: '2. Kelengkapan Berkas Pra-Klaim', tahap: 'pra_klaim' as const, success: `${completedDocsCount}/${docs.length} Dokumen Lengkap` },
                                    {
                                        label: '3. Validasi Diagnosis (ICD-10)',
                                        tahap: 'icd10' as const,
                                        success: verificationFor('icd10')?.detail_hasil?.primer
                                            ? `Primer ${verificationFor('icd10')?.detail_hasil?.primer}`
                                            : 'Belum ada hasil validasi',
                                    },
                                    {
                                        label: '4. Validasi Prosedur (ICD-9-CM)',
                                        tahap: 'icd9cm' as const,
                                        success: verificationFor('icd9cm')?.detail_hasil?.total
                                            ? `${verificationFor('icd9cm')?.detail_hasil?.total} kode prosedur`
                                            : 'Belum ada hasil validasi',
                                    },
                                    {
                                        label: '5. Penapisan Risiko Klaim',
                                        tahap: 'risiko' as const,
                                        success: verificationFor('risiko')?.detail_hasil?.aktif === 0
                                            ? 'Bebas risiko aktif'
                                            : `${verificationFor('risiko')?.detail_hasil?.aktif ?? activeRisks.length} risiko aktif`,
                                    },
                                    {
                                        label: '6. Tinjauan Akhir',
                                        tahap: 'tinjauan_akhir' as const,
                                        success: isReadyForSubmission ? 'Siap diajukan' : 'Menunggu keputusan',
                                    },
                                ].map((row) => {
                                    const statusText = summaryStatus(row.tahap, row.success);
                                    return (
                                        <div key={row.tahap} className="py-2.5 flex items-center justify-between gap-4">
                                            <span className="font-medium text-slate-700">{row.label}</span>
                                            <span className={`font-semibold text-right ${statusText.startsWith('✓') ? 'text-emerald-600' : 'text-amber-600'}`}>
                                                {statusText}
                                            </span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>

                        {/* Approval Action Bar */}
                        <div className="p-4 bg-white rounded-xl border border-slate-200/90 shadow-sm space-y-3">
                            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                                <div className="flex-1">
                                    <label htmlFor="revision-note" className="text-[11px] font-semibold text-slate-700">
                                        Catatan revisi <span className="text-rose-600">*</span>
                                    </label>
                                    <textarea
                                        id="revision-note"
                                        value={revisionNote}
                                        onChange={(event) => setRevisionNote(event.target.value)}
                                        maxLength={500}
                                        rows={2}
                                        placeholder="Jelaskan perbaikan yang dibutuhkan unit rawat inap / DPJP"
                                        className="mt-1 w-full px-3 py-2 border border-slate-300 rounded-lg text-xs focus:border-teal-500 focus:ring-teal-500"
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={handleReturnForRevision}
                                    disabled={!canVerify || revisionNote.trim() === ''}
                                    className="px-4 py-2 border border-slate-300 hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-medium transition disabled:opacity-50 shrink-0"
                                >
                                    Kembalikan untuk Revisi
                                </button>
                            </div>
                            <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-end gap-2 border-t border-slate-100 pt-3">
                                <Link
                                    href={route('eclaim.index')}
                                    className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-medium transition text-center"
                                >
                                    Ke Portal Sync E-Claim
                                </Link>
                                <button
                                    type="button"
                                    onClick={handleMarkReady}
                                    disabled={claim.status === 'siap_diajukan' || !isReadyForSubmission || !canApprove}
                                    className={`px-5 py-2 rounded-lg text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-sm ${
                                        claim.status === 'siap_diajukan'
                                            ? 'bg-emerald-600 text-white cursor-default'
                                            : 'bg-teal-600 hover:bg-teal-700 text-white disabled:opacity-50'
                                    }`}
                                >
                                    <Check className="w-4 h-4" />
                                    <span>{claim.status === 'siap_diajukan' ? 'Sudah Ditandai Siap Diajukan ✓' : 'Tandai Siap Diajukan'}</span>
                                </button>
                            </div>
                            {!canApprove && (
                                <p className="text-[10px] text-slate-500 text-right">Izin <code>claims.approve</code> diperlukan untuk menandai klaim siap diajukan.</p>
                            )}
                        </div>
                    </div>
                )}
            </div>

            {/* DOCUMENT PREVIEW MODAL */}
            {previewDoc && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs animate-fadeIn">
                    <div className="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 space-y-4">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                            <div>
                                <h3 className="font-bold text-slate-900 text-sm">{previewDoc.title}</h3>
                                <p className="text-[11px] text-slate-400">{previewDoc.filename}</p>
                            </div>
                            <button type="button" onClick={() => setPreviewDoc(null)} className="text-slate-400 hover:text-slate-600">✕</button>
                        </div>

                        {previewDoc.url ? (
                            <div className="space-y-3">
                                <iframe
                                    src={previewDoc.url}
                                    title={previewDoc.title}
                                    className="w-full h-[420px] rounded-xl border border-slate-200 bg-slate-50"
                                />
                                <p className="text-[11px] text-slate-500">
                                    Menampilkan berkas asli yang tersimpan pada penyimpanan privat aplikasi.
                                </p>
                            </div>
                        ) : (
                            <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600">
                                Dokumen ini belum memiliki berkas asli. Unggah revisi dokumen untuk menyimpan berkas yang dapat dibuka di sini.
                            </div>
                        )}

                        <div className="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                onClick={() => setPreviewDoc(null)}
                                className="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg text-xs font-semibold"
                            >
                                Tutup Pratinjau
                            </button>
                            {previewDoc.url && (
                                <a
                                    href={previewDoc.url}
                                    className="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-xs font-semibold inline-flex items-center gap-1.5"
                                >
                                    <Download className="w-3.5 h-3.5" />
                                    Unduh berkas
                                </a>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
