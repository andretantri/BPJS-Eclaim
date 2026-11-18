export interface Role {
    id: number;
    name: string;
    display_name: string;
    permissions?: string[];
}

export interface User {
    id: number;
    name: string;
    email: string;
    nip?: string;
    jabatan?: string;
    role?: Role;
    role_id?: number;
    hospital_id?: number;
    is_active?: boolean;
    last_login_at?: string;
    email_verified_at?: string;
}

export interface Hospital {
    id?: number;
    kode_rs?: string;
    nama_rs: string;
    alamat?: string;
    kota?: string;
    provinsi?: string;
    telepon?: string;
    email?: string;
    kode_ppk_bpjs?: string;
}

export interface Patient {
    id: number;
    no_rm: string;
    nama_lengkap: string;
    jenis_kelamin: 'Laki-laki' | 'Perempuan';
    tanggal_lahir?: string;
    usia: number;
    no_ktp?: string;
    no_kis?: string;
    alamat?: string;
    telepon?: string;
    segmen_peserta?: string;
    kelas_hak?: string;
    faskes_tingkat_pertama?: string;
    is_active_bpjs?: boolean;
}

export interface IcdOption {
    id: number;
    kode: string;
    deskripsi_id: string;
    kategori?: string | null;
}

export interface ClaimDiagnosis {
    id: number;
    claim_id: number;
    icd10_code_id?: number | null;
    kode_icd: string;
    deskripsi: string;
    tipe: 'primer' | 'sekunder';
    is_valid: boolean;
    catatan_validasi?: string | null;
    icd10_code?: IcdOption;
}

export interface ClaimProcedure {
    id: number;
    claim_id: number;
    icd9cm_code_id?: number | null;
    kode_prosedur: string;
    deskripsi: string;
    is_valid: boolean;
    catatan_validasi?: string | null;
    icd9cm_code?: IcdOption;
}

export interface ClaimDocument {
    id: number;
    claim_id: number;
    tipe_dokumen: 'sep' | 'rujukan' | 'resume_medis' | 'rincian_biaya' | 'penunjang';
    nama_file: string;
    path_file?: string;
    ukuran_file: number;
    status_verifikasi: 'belum' | 'lolos' | 'gagal' | 'lengkap' | 'tidak_lengkap' | 'perlu_revisi';
    catatan_verifikasi?: string;
    verified_at?: string;
}

export interface ClaimVerification {
    id: number;
    claim_id: number;
    tahap: 'vclaim' | 'pra_klaim' | 'icd10' | 'icd9cm' | 'risiko' | 'tinjauan_akhir';
    status: 'belum' | 'lolos' | 'gagal' | 'lengkap' | 'tidak_lengkap' | 'perlu_revisi';
    detail_hasil?: Record<string, any>;
    catatan?: string;
    verified_at?: string;
}

export interface ClaimRisk {
    id: number;
    claim_id: number;
    kode_temuan: string;
    judul_temuan: string;
    deskripsi_temuan: string;
    tingkat_risiko: 'rendah' | 'sedang' | 'tinggi' | 'kritis';
    status_tindak_lanjut: 'baru' | 'dalam_proses' | 'terselesaikan' | 'diterima_risiko';
    rekomendasi?: string;
    bukti_pendukung?: string;
    catatan_tindak_lanjut?: string | null;
}

export interface Claim {
    id: number;
    nomor_klaim: string;
    patient_id: number;
    hospital_id: number;
    no_sep: string;
    no_rujukan?: string;
    care_type: string;
    jenis_pelayanan: 'rawat_inap' | 'rawat_jalan';
    kelas_rawat?: string | null;
    tanggal_masuk: string;
    tanggal_pulang?: string;
    lama_rawat: number;
    dpjp_nama?: string | null;
    dpjp_kode?: string;
    diagnosa_primer: string;
    diagnosa_sekunder?: string;
    prosedur_utama?: string;
    estimasi_biaya: number;
    biaya_disetujui: number;
    status: 'baru' | 'menunggu_verifikasi' | 'bermasalah' | 'siap_diajukan' | 'diajukan' | 'disetujui' | 'ditolak';
    issue?: string;
    issue_desc?: string;
    tingkat_risiko?: 'rendah' | 'sedang' | 'tinggi' | 'kritis' | null;
    jumlah_temuan_risiko: number;
    doc_sep: boolean;
    doc_rujukan: boolean;
    doc_resume: boolean;
    doc_billing: boolean;
    doc_toraks: boolean;
    grouper_code?: string;
    grouper_description?: string;
    tarif_ina_cbgs?: number | null;
    is_synced_eclaim: boolean;
    synced_at?: string;
    patient: Patient;
    documents?: ClaimDocument[];
    verifications?: ClaimVerification[];
    risks?: ClaimRisk[];
    diagnoses?: ClaimDiagnosis[];
    procedures?: ClaimProcedure[];
}

export interface ActivityItem {
    id: number;
    user_id?: number;
    action: string;
    description: string;
    created_at: string;
    user?: User;
}

export interface NotificationItem {
    id: string;
    user_id?: number;
    claim_id?: number;
    tipe: 'action' | 'info';
    judul: string;
    pesan: string;
    action_url?: string;
    prioritas: 'rendah' | 'normal' | 'tinggi' | 'urgent';
    is_read: boolean;
    created_at: string;
}

export interface ClaimPeriod {
    id: number;
    nama_periode: string;
    bulan: number;
    tahun: number;
    status: 'aktif' | 'ditutup';
}

export interface EclaimSyncItem {
    id: number;
    sync_id: number;
    claim_id: number;
    status: 'success' | 'failed';
    eclaim_response_code?: string;
    eclaim_response_message?: string;
    claim: Claim;
}

export interface EclaimSync {
    id: number;
    batch_code: string;
    tipe_sync: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_items: number;
    success_count: number;
    failed_count: number;
    created_at?: string;
    started_at?: string;
    completed_at?: string;
    initiator?: User;
    items?: EclaimSyncItem[];
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        permissions: string[];
        isAdmin: boolean;
    };
    hospital: Hospital | null;
    currentPeriod: ClaimPeriod | null;
    bpjsConfigured: boolean;
    impersonator: { id: number; name: string; email: string } | null;
    claimStats: {
        total: number;
        menunggu: number;
        bermasalah: number;
        siap: number;
    };
    notifications: {
        unreadCount: number;
        items: NotificationItem[];
    };
    flash: {
        success?: string;
        info?: string;
        warning?: string;
        error?: string;
    };
};
