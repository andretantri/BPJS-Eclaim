<?php

namespace Database\Seeders;

use App\Enums\ClaimStatus;
use App\Enums\EclaimItemStatus;
use App\Enums\EclaimSyncStatus;
use App\Enums\RiskFollowUpStatus;
use App\Enums\StatusVerifikasi;
use App\Enums\TingkatRisiko;
use App\Enums\TipeDokumen;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\ClaimDocument;
use App\Models\ClaimPeriod;
use App\Models\ClaimRisk;
use App\Models\ClaimVerification;
use App\Models\EclaimSync;
use App\Models\EclaimSyncItem;
use App\Models\Hospital;
use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use App\Models\Notification;
use App\Models\Patient;
use App\Models\Role;
use App\Models\User;
use App\Services\IcdCodingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ClaimGuardSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Hospital
        $hospital = Hospital::create([
            'kode_rs' => 'RS-CKR-001',
            'nama_rs' => 'RS Cakra Medika',
            'alamat' => 'Jl. Kesehatan No. 45, Bandung',
            'kota' => 'Bandung',
            'provinsi' => 'Jawa Barat',
            'telepon' => '022-7281923',
            'email' => 'casemix@cakramedika.com',
            'kode_ppk_bpjs' => '0184R001',
        ]);

        // 2. Roles
        $rolePetugas = Role::create([
            'name' => 'petugas_casemix',
            'display_name' => 'Petugas Casemix',
            'permissions' => ['claims.view', 'claims.create', 'claims.edit', 'claims.verify'],
        ]);

        $roleVerifikator = Role::create([
            'name' => 'verifikator_medis',
            'display_name' => 'Verifikator Medis',
            'permissions' => ['claims.view', 'claims.verify', 'claims.approve', 'eclaim.sync'],
        ]);

        $roleAdmin = Role::create([
            'name' => 'admin_casemix',
            'display_name' => 'Admin Casemix',
            'permissions' => ['*'],
        ]);

        // 3. Users
        $nadia = User::create([
            'name' => 'Nadia Putri',
            'email' => 'nadia@cakramedika.com',
            'password' => Hash::make('password'),
            'hospital_id' => $hospital->id,
            'role_id' => $rolePetugas->id,
            'nip' => 'CSM-2024-0012',
            'jabatan' => 'Petugas Casemix',
            'is_active' => true,
        ]);

        $bima = User::create([
            'name' => 'Bima Satya',
            'email' => 'bima@cakramedika.com',
            'password' => Hash::make('password'),
            'hospital_id' => $hospital->id,
            'role_id' => $roleVerifikator->id,
            'nip' => 'VRF-2023-0008',
            'jabatan' => 'Verifikator Medis BPJS',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Administrator Casemix',
            'email' => 'admin@cakramedika.com',
            'password' => Hash::make('password'),
            'hospital_id' => $hospital->id,
            'role_id' => $roleAdmin->id,
            'nip' => 'ADM-2022-0001',
            'jabatan' => 'Kepala Unit Casemix',
            'is_active' => true,
        ]);

        // 4. Reference ICD Codes
        $icd10 = [
            ['kode' => 'J18.9', 'deskripsi_id' => 'Pneumonia, tidak spesifik', 'kategori' => 'Respirasi'],
            ['kode' => 'E11.9', 'deskripsi_id' => 'Diabetes melitus tipe 2 tanpa komplikasi', 'kategori' => 'Endokrin'],
            ['kode' => 'K35.8', 'deskripsi_id' => 'Apendisitis akut lainnya dan tidak spesifik', 'kategori' => 'Digestif'],
            ['kode' => 'A09.9', 'deskripsi_id' => 'Gastroenteritis dan kolitis infeksius tidak spesifik', 'kategori' => 'Infeksi'],
            ['kode' => 'E86', 'deskripsi_id' => 'Dehidrasi / Volume depletion', 'kategori' => 'Nutrisi & Metabolik'],
            ['kode' => 'I10', 'deskripsi_id' => 'Hipertensi esensial (primer)', 'kategori' => 'Kardiovaskular'],
            ['kode' => 'J45.9', 'deskripsi_id' => 'Asma bronkial tidak spesifik', 'kategori' => 'Respirasi'],
            ['kode' => 'N20.0', 'deskripsi_id' => 'Batu ginjal / Calculus of kidney', 'kategori' => 'Urologi'],
            ['kode' => 'N13.2', 'deskripsi_id' => 'Hidronefrosis dengan sumbatan batu kalkulus', 'kategori' => 'Urologi'],
            ['kode' => 'K30', 'deskripsi_id' => 'Dispepsia fungsional', 'kategori' => 'Digestif'],
        ];
        foreach ($icd10 as $item) {
            Icd10Code::create($item);
        }

        $icd9 = [
            ['kode' => '99.21', 'deskripsi_id' => 'Injeksi antibiotik intravena', 'kategori' => 'Medikasi'],
            ['kode' => '87.44', 'deskripsi_id' => 'Foto toraks rutin', 'kategori' => 'Radiologi'],
            ['kode' => '47.09', 'deskripsi_id' => 'Laparoskopi apendektomi / operasi usus buntu', 'kategori' => 'Bedah'],
            ['kode' => '99.18', 'deskripsi_id' => 'Terapi rehidrasi elektrolit intravena', 'kategori' => 'Medikasi'],
            ['kode' => '89.52', 'deskripsi_id' => 'Elektrokardiogram (EKG) rutin', 'kategori' => 'Diagnostik'],
            ['kode' => '93.94', 'deskripsi_id' => 'Nebulisasi terapi inhalasi', 'kategori' => 'Respirasi'],
            ['kode' => '59.8', 'deskripsi_id' => 'Ureteroskopi & Pemasangan DJ Stent', 'kategori' => 'Bedah Urologi'],
            ['kode' => '90.59', 'deskripsi_id' => 'Pemeriksaan laboratorium gula darah rutin', 'kategori' => 'Laboratorium'],
            ['kode' => '96.07', 'deskripsi_id' => 'Pemasangan selang lambung (NGT)', 'kategori' => 'Tindakan Keperawatan'],
        ];
        foreach ($icd9 as $item) {
            Icd9cmCode::create($item);
        }

        // 5. Claim Period
        $period = ClaimPeriod::create([
            'hospital_id' => $hospital->id,
            'nama_periode' => 'November 2026',
            'bulan' => 11,
            'tahun' => 2026,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-30',
            'status' => 'aktif',
            'total_nilai_klaim' => 6840000000.00,
            'total_dibayar' => 6300000000.00,
            'jumlah_klaim' => 1284,
        ]);

        // 6. Patients & Claims Data
        $claimsData = [
            [
                'id' => 'KLM-2026-0184',
                'name' => 'Dewi Larasati',
                'gender' => 'Perempuan',
                'age' => 48,
                'rm' => 'RM-260184',
                'sep' => 'SIM-SEP-261104-0184',
                'kis' => 'SIM-KIS-00000184',
                'admission_date' => '2026-11-04',
                'discharge_date' => '2026-11-08',
                'care_type' => 'Rawat inap · Kelas 2',
                'jenis_pelayanan' => 'rawat_inap',
                'kelas_rawat' => 'Kelas 2',
                'dpjp' => 'dr. Aditya Wibawa, Sp.PD (fiktif)',
                'primary_diag' => 'J18.9 Pneumonia',
                'secondary_diag' => 'E11.9 DM tipe 2',
                'procedures' => '99.21 (Injeksi IV), 87.44 (Foto toraks)',
                'cost' => 7850000,
                'status' => ClaimStatus::Bermasalah->value,
                'issue' => 'Resume belum ditandatangani',
                'issue_desc' => 'Lengkapi tanda tangan DPJP sebelum klaim dapat ditandai siap diajukan.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => false, 'billing' => true, 'toraks' => true],
                'risk_level' => TingkatRisiko::Sedang->value,
                'risk_findings' => 1,
                'grouper_code' => 'J-4-16-I',
                'grouper_desc' => 'Infeksi Saluran Pernapasan Bawah Akut Ringan',
            ],
            [
                'id' => 'KLM-2026-0193',
                'name' => 'Arif Wicaksana',
                'gender' => 'Laki-laki',
                'age' => 42,
                'rm' => 'RM-260193',
                'sep' => 'SIM-SEP-261105-0193',
                'kis' => 'SIM-KIS-00000193',
                'admission_date' => '2026-11-05',
                'discharge_date' => '2026-11-08',
                'care_type' => 'Rawat inap · Kelas 1',
                'jenis_pelayanan' => 'rawat_inap',
                'kelas_rawat' => 'Kelas 1',
                'dpjp' => 'dr. Hendra Gunawan, Sp.B (fiktif)',
                'primary_diag' => 'K35.8 Apendisitis akut',
                'secondary_diag' => '-',
                'procedures' => '47.09 (Laparoskopi apendektomi)',
                'cost' => 5420000,
                'status' => ClaimStatus::Bermasalah->value,
                'issue' => 'Diagnosis perlu klarifikasi',
                'issue_desc' => 'Pemeriksaan patologi anatomi belum dilampirkan dalam resume.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => false, 'billing' => true, 'toraks' => true],
                'risk_level' => TingkatRisiko::Sedang->value,
                'risk_findings' => 1,
                'grouper_code' => 'K-1-12-I',
                'grouper_desc' => 'Prosedur Apendektomi Ringan',
            ],
            [
                'id' => 'KLM-2026-0207',
                'name' => 'Rina Puspitasari',
                'gender' => 'Perempuan',
                'age' => 35,
                'rm' => 'RM-260207',
                'sep' => 'SIM-SEP-261106-0207',
                'kis' => 'SIM-KIS-00000207',
                'admission_date' => '2026-11-06',
                'discharge_date' => '2026-11-09',
                'care_type' => 'Rawat inap · Kelas 2',
                'jenis_pelayanan' => 'rawat_inap',
                'kelas_rawat' => 'Kelas 2',
                'dpjp' => 'dr. Maya Safitri, Sp.PD (fiktif)',
                'primary_diag' => 'A09.9 Gastroenteritis',
                'secondary_diag' => 'E86 Dehidrasi ringan',
                'procedures' => '99.18 (Terapi rehidrasi intravena)',
                'cost' => 4680000,
                'status' => ClaimStatus::MenungguVerifikasi->value,
                'issue' => 'Menunggu verifikasi berkas',
                'issue_desc' => 'Menunggu hasil audit kelayakan administrasi rekam medis.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => true, 'billing' => true, 'toraks' => true],
                'risk_level' => TingkatRisiko::Rendah->value,
                'risk_findings' => 0,
                'grouper_code' => 'A-4-10-I',
                'grouper_desc' => 'Infeksi Saluran Cerna Ringan',
            ],
            [
                'id' => 'KLM-2026-0208',
                'name' => 'Bagas Pramudya',
                'gender' => 'Laki-laki',
                'age' => 54,
                'rm' => 'RM-260208',
                'sep' => 'SIM-SEP-261106-0208',
                'kis' => 'SIM-KIS-00000208',
                'admission_date' => '2026-11-06',
                'discharge_date' => '2026-11-09',
                'care_type' => 'Rawat inap · Kelas 3',
                'jenis_pelayanan' => 'rawat_inap',
                'kelas_rawat' => 'Kelas 3',
                'dpjp' => 'dr. Budi Hartono, Sp.JP (fiktif)',
                'primary_diag' => 'I10 Hipertensi esensial',
                'secondary_diag' => '-',
                'procedures' => '89.52 (EKG rutin)',
                'cost' => 3950000,
                'status' => ClaimStatus::SiapDiajukan->value,
                'issue' => 'Berkas lengkap & terverifikasi',
                'issue_desc' => 'Semua dokumen dan koding telah disetujui tim casemix.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => true, 'billing' => true, 'toraks' => true],
                'risk_level' => TingkatRisiko::Rendah->value,
                'risk_findings' => 0,
                'grouper_code' => 'I-4-17-I',
                'grouper_desc' => 'Hipertensi Ringan',
            ],
            [
                'id' => 'KLM-2026-0211',
                'name' => 'Sari Kusumadewi',
                'gender' => 'Perempuan',
                'age' => 29,
                'rm' => 'RM-260211',
                'sep' => 'SIM-SEP-261107-0211',
                'kis' => 'SIM-KIS-00000211',
                'admission_date' => '2026-11-07',
                'discharge_date' => '2026-11-07',
                'care_type' => 'Rawat jalan (Poli Paru)',
                'jenis_pelayanan' => 'rawat_jalan',
                'kelas_rawat' => 'Rawat Jalan',
                'dpjp' => 'dr. Aditya Wibawa, Sp.PD (fiktif)',
                'primary_diag' => 'J45.9 Asma bronkial',
                'secondary_diag' => '-',
                'procedures' => '93.94 (Nebulisasi terapi)',
                'cost' => 2150000,
                'status' => ClaimStatus::SiapDiajukan->value,
                'issue' => 'Sesuai regulasi rawat jalan',
                'issue_desc' => 'Klaim rawat jalan telah lengkap verifikasi.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => true, 'billing' => true, 'toraks' => true],
                'risk_level' => TingkatRisiko::Rendah->value,
                'risk_findings' => 0,
                'grouper_code' => 'Q-5-18-0',
                'grouper_desc' => 'Rawat Jalan Tingkat Lanjut Spesialis',
            ],
            [
                'id' => 'KLM-2026-0215',
                'name' => 'Fajar Adinata',
                'gender' => 'Laki-laki',
                'age' => 50,
                'rm' => 'RM-260215',
                'sep' => 'SIM-SEP-261107-0215',
                'kis' => 'SIM-KIS-00000215',
                'admission_date' => '2026-11-07',
                'discharge_date' => '2026-11-10',
                'care_type' => 'Rawat inap · Kelas 1',
                'jenis_pelayanan' => 'rawat_inap',
                'kelas_rawat' => 'Kelas 1',
                'dpjp' => 'dr. Satria Nugraha, Sp.U (fiktif)',
                'primary_diag' => 'N20.0 Batu ginjal',
                'secondary_diag' => 'N13.2 Hidronefrosis',
                'procedures' => '59.8 (Ureteroskopi & DJ Stent)',
                'cost' => 6890000,
                'status' => ClaimStatus::MenungguVerifikasi->value,
                'issue' => 'Menunggu hasil USG urologi',
                'issue_desc' => 'Pemberkasan penunjang sedang diunggah bagian radiologi.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => true, 'billing' => true, 'toraks' => false],
                'risk_level' => TingkatRisiko::Rendah->value,
                'risk_findings' => 0,
                'grouper_code' => 'N-1-10-I',
                'grouper_desc' => 'Prosedur Endoskopi Saluran Kemih',
            ],
            [
                'id' => 'KLM-2026-0219',
                'name' => 'Maya Anggraini',
                'gender' => 'Perempuan',
                'age' => 61,
                'rm' => 'RM-260219',
                'sep' => 'SIM-SEP-261108-0219',
                'kis' => 'SIM-KIS-00000219',
                'admission_date' => '2026-11-08',
                'discharge_date' => '2026-11-11',
                'care_type' => 'Rawat inap · Kelas 2',
                'jenis_pelayanan' => 'rawat_inap',
                'kelas_rawat' => 'Kelas 2',
                'dpjp' => 'dr. Aditya Wibawa, Sp.PD (fiktif)',
                'primary_diag' => 'E11.9 DM tipe 2',
                'secondary_diag' => 'I10 Hipertensi',
                'procedures' => '90.59 (Pemeriksaan lab gula darah)',
                'cost' => 4800000,
                'status' => ClaimStatus::SiapDiajukan->value,
                'issue' => 'Berkas lengkap',
                'issue_desc' => 'Telah memenuhi standar koding INA-CBGs.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => true, 'billing' => true, 'toraks' => true],
                'risk_level' => TingkatRisiko::Rendah->value,
                'risk_findings' => 0,
                'grouper_code' => 'E-4-10-I',
                'grouper_desc' => 'Diabetes Tanpa Komplikasi Mayor',
            ],
            [
                'id' => 'KLM-2026-0224',
                'name' => 'Teguh Mahardika',
                'gender' => 'Laki-laki',
                'age' => 38,
                'rm' => 'RM-260224',
                'sep' => 'SIM-SEP-261108-0224',
                'kis' => 'SIM-KIS-00000224',
                'admission_date' => '2026-11-08',
                'discharge_date' => '2026-11-10',
                'care_type' => 'Rawat inap · Kelas 3',
                'jenis_pelayanan' => 'rawat_inap',
                'kelas_rawat' => 'Kelas 3',
                'dpjp' => 'dr. Maya Safitri, Sp.PD (fiktif)',
                'primary_diag' => 'K30 Dispepsia',
                'secondary_diag' => '-',
                'procedures' => '96.07 (Pemasangan NGT)',
                'cost' => 3250000,
                'status' => ClaimStatus::SiapDiajukan->value,
                'issue' => 'Validasi koding selesai',
                'issue_desc' => 'Lolos verifikasi sistem ClaimGuard.',
                'docs' => ['sep' => true, 'rujukan' => true, 'resume' => true, 'billing' => true, 'toraks' => true],
                'risk_level' => TingkatRisiko::Rendah->value,
                'risk_findings' => 0,
                'grouper_code' => 'K-4-17-I',
                'grouper_desc' => 'Kelainan Lambung & Duodenum',
            ],
        ];

        foreach ($claimsData as $c) {
            $patient = Patient::create([
                'no_rm' => $c['rm'],
                'nama_lengkap' => $c['name'],
                'jenis_kelamin' => $c['gender'],
                'usia' => $c['age'],
                'no_kis' => $c['kis'],
                'no_ktp' => '3273'.rand(100000000000, 999999999999),
                'alamat' => 'Kota Bandung, Jawa Barat',
                'kelas_hak' => str_contains($c['care_type'], 'Kelas 1') ? 'Kelas 1' : (str_contains($c['care_type'], 'Kelas 3') ? 'Kelas 3' : 'Kelas 2'),
                'segmen_peserta' => 'PPU (Pekerja Penerima Upah)',
                'faskes_tingkat_pertama' => 'Klinik Pratama Pelita (fiktif)',
                'is_active_bpjs' => true,
            ]);

            $claim = Claim::create([
                'nomor_klaim' => $c['id'],
                'patient_id' => $patient->id,
                'hospital_id' => $hospital->id,
                'period_id' => $period->id,
                'created_by' => $nadia->id,
                'no_sep' => $c['sep'],
                'no_rujukan' => 'RJK-SIM-261103-'.substr($c['rm'], 3),
                'care_type' => $c['care_type'],
                'jenis_pelayanan' => $c['jenis_pelayanan'],
                'kelas_rawat' => $c['kelas_rawat'],
                'tanggal_masuk' => $c['admission_date'],
                'tanggal_pulang' => $c['discharge_date'],
                'lama_rawat' => 4,
                'dpjp_nama' => $c['dpjp'],
                'dpjp_kode' => 'DPJP-'.substr(md5($c['dpjp']), 0, 5),
                'diagnosa_primer' => $c['primary_diag'],
                'diagnosa_sekunder' => $c['secondary_diag'],
                'prosedur_utama' => $c['procedures'],
                'estimasi_biaya' => $c['cost'],
                'biaya_disetujui' => $c['status'] === ClaimStatus::SiapDiajukan->value ? $c['cost'] : 0,
                'status' => $c['status'],
                'issue' => $c['issue'],
                'issue_desc' => $c['issue_desc'],
                'tingkat_risiko' => $c['risk_level'],
                'jumlah_temuan_risiko' => $c['risk_findings'],
                'doc_sep' => $c['docs']['sep'],
                'doc_rujukan' => $c['docs']['rujukan'],
                'doc_resume' => $c['docs']['resume'],
                'doc_billing' => $c['docs']['billing'],
                'doc_toraks' => $c['docs']['toraks'],
                'grouper_code' => $c['grouper_code'],
                'grouper_description' => $c['grouper_desc'],
                'tarif_ina_cbgs' => $c['cost'],
                'is_synced_eclaim' => $c['status'] === ClaimStatus::SiapDiajukan->value,
                'synced_at' => $c['status'] === ClaimStatus::SiapDiajukan->value ? now()->subHours(rand(1, 4)) : null,
            ]);

            app(IcdCodingService::class)->sync(
                $claim,
                $this->firstIcd10Code($c['primary_diag']),
                $this->icd10Codes($c['secondary_diag']),
                $this->icd9Codes($c['procedures']),
            );

            // Add claim documents
            $docTypes = [
                ['tipe' => 'sep', 'nama' => "SEP_{$c['rm']}.pdf", 'size' => 284, 'status' => $c['docs']['sep'] ? StatusVerifikasi::Lengkap->value : StatusVerifikasi::TidakLengkap->value],
                ['tipe' => 'rujukan', 'nama' => "Rujukan_{$c['rm']}.pdf", 'size' => 196, 'status' => $c['docs']['rujukan'] ? StatusVerifikasi::Lengkap->value : StatusVerifikasi::TidakLengkap->value],
                ['tipe' => 'resume_medis', 'nama' => "Resume_{$c['rm']}.pdf", 'size' => 412, 'status' => $c['docs']['resume'] ? StatusVerifikasi::Lengkap->value : StatusVerifikasi::TidakLengkap->value, 'note' => $c['docs']['resume'] ? null : 'Belum ditandatangani DPJP'],
                ['tipe' => 'rincian_biaya', 'nama' => "Billing_{$c['rm']}.pdf", 'size' => 150, 'status' => $c['docs']['billing'] ? StatusVerifikasi::Lengkap->value : StatusVerifikasi::TidakLengkap->value],
                ['tipe' => TipeDokumen::Penunjang->value, 'nama' => "Toraks_{$c['rm']}.pdf", 'size' => 1840, 'status' => $c['docs']['toraks'] ? StatusVerifikasi::Lengkap->value : StatusVerifikasi::TidakLengkap->value],
            ];

            foreach ($docTypes as $doc) {
                ClaimDocument::create([
                    'claim_id' => $claim->id,
                    'tipe_dokumen' => $doc['tipe'],
                    'nama_file' => $doc['nama'],
                    'ukuran_file' => $doc['size'],
                    'status_verifikasi' => $doc['status'],
                    'catatan_verifikasi' => $doc['note'] ?? null,
                    'verified_by' => $nadia->id,
                    'verified_at' => now()->subMinutes(rand(10, 120)),
                ]);
            }

            // Add verifications
            $stages = ['vclaim', 'pra_klaim', 'icd10', 'icd9cm', 'risiko', 'tinjauan_akhir'];
            foreach ($stages as $stage) {
                ClaimVerification::create([
                    'claim_id' => $claim->id,
                    'tahap' => $stage,
                    'status' => ($stage === 'pra_klaim' && ! $c['docs']['resume']) ? 'perlu_revisi' : 'lolos',
                    'verified_by' => $bima->id,
                    'verified_at' => now(),
                ]);
            }

            // Add risks if problematic
            if ($c['status'] === ClaimStatus::Bermasalah->value) {
                ClaimRisk::create([
                    'claim_id' => $claim->id,
                    'kode_temuan' => 'ADM-01',
                    'judul_temuan' => $c['issue'],
                    'deskripsi_temuan' => $c['issue_desc'],
                    'tingkat_risiko' => TingkatRisiko::Sedang->value,
                    'status_tindak_lanjut' => RiskFollowUpStatus::Baru->value,
                    'rekomendasi' => 'Unggah dokumen resume medis revisi bertanda tangan basah / digital DPJP.',
                    'bukti_pendukung' => 'Resume_'.$c['rm'].'.pdf (halaman 3 belum dibubuhi tanda tangan)',
                ]);
            }
        }

        // 7. E-Claim Batch Sync Demo
        $sync = EclaimSync::create([
            'hospital_id' => $hospital->id,
            'initiated_by' => $bima->id,
            'batch_code' => 'BATCH-SIM-NOV-003',
            'tipe_sync' => 'batch_pengajuan',
            'status' => EclaimSyncStatus::Completed->value,
            'total_items' => 12,
            'success_count' => 12,
            'failed_count' => 0,
            'started_at' => now()->subHours(2),
            'completed_at' => now()->subHours(2)->addMinutes(3),
        ]);

        $readyClaims = Claim::where('status', ClaimStatus::SiapDiajukan->value)->get();
        foreach ($readyClaims as $rc) {
            EclaimSyncItem::create([
                'sync_id' => $sync->id,
                'claim_id' => $rc->id,
                'status' => EclaimItemStatus::Success->value,
                'eclaim_response_code' => '200',
                'eclaim_response_message' => 'Data klaim berhasil diterima dan divalidasi oleh INA-CBGs E-Claim Server.',
                'request_payload' => ['nomor_sep' => $rc->no_sep, 'tarif' => $rc->estimasi_biaya],
                'response_payload' => ['cbg_code' => $rc->grouper_code, 'tarif_inacbg' => $rc->tarif_ina_cbgs],
            ]);
        }

        // 8. Notifications
        $notifs = [
            [
                'id' => (string) Str::uuid(),
                'user_id' => $nadia->id,
                'claim_id' => Claim::where('nomor_klaim', 'KLM-2026-0184')->first()->id,
                'tipe' => 'action',
                'judul' => 'Resume Belum Ditandatangani',
                'pesan' => 'Dewi Larasati (KLM-2026-0184): Tanda tangan DPJP dr. Aditya Wibawa belum terisi.',
                'action_url' => '/klaim/'.Claim::where('nomor_klaim', 'KLM-2026-0184')->first()->id.'/verifikasi/pra-klaim',
                'prioritas' => 'urgent',
                'is_read' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $nadia->id,
                'claim_id' => Claim::where('nomor_klaim', 'KLM-2026-0193')->first()->id,
                'tipe' => 'action',
                'judul' => 'Diagnosis Perlu Klarifikasi',
                'pesan' => 'Arif Wicaksana (KLM-2026-0193): Konfirmasi kode patologi apendisitis akut K35.8.',
                'action_url' => '/klaim/'.Claim::where('nomor_klaim', 'KLM-2026-0193')->first()->id.'/verifikasi/icd10',
                'prioritas' => 'normal',
                'is_read' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $nadia->id,
                'claim_id' => null,
                'tipe' => 'info',
                'judul' => '12 Klaim Ditandai Siap',
                'pesan' => 'Batch SIM-NOV-003 disahkan oleh Bima Satya untuk pengajuan periode November.',
                'action_url' => '/eclaim',
                'prioritas' => 'normal',
                'is_read' => false,
            ],
            [
                'id' => (string) Str::uuid(),
                'user_id' => $nadia->id,
                'claim_id' => null,
                'tipe' => 'info',
                'judul' => 'Pencocokan SEP Berhasil',
                'pesan' => '85 SEP rawat inap dan rawat jalan telah disinkronkan dengan data kepesertaan V-Claim.',
                'action_url' => '/eclaim',
                'prioritas' => 'normal',
                'is_read' => true,
            ],
        ];

        foreach ($notifs as $n) {
            Notification::create($n);
        }

        // 9. Activity Logs
        $logs = [
            ['user_id' => $nadia->id, 'action' => 'Validasi koding selesai', 'description' => 'KLM-2026-0208 · Nadia Putri'],
            ['user_id' => $nadia->id, 'action' => 'Dokumen perlu dilengkapi', 'description' => 'KLM-2026-0184 · Dewi Larasati'],
            ['user_id' => $bima->id, 'action' => '12 klaim ditandai siap', 'description' => 'Batch SIM-NOV-003 · Bima Satya'],
            ['user_id' => $nadia->id, 'action' => 'Pencocokan SEP V-Claim', 'description' => '85 SEP berhasil diverifikasi sistem'],
        ];

        foreach ($logs as $l) {
            ActivityLog::create($l);
        }
    }

    private function firstIcd10Code(string $text): string
    {
        return $this->icd10Codes($text)[0] ?? '';
    }

    /**
     * @return array<int, string>
     */
    private function icd10Codes(string $text): array
    {
        preg_match_all('/\b[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?\b/', strtoupper($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }

    /**
     * @return array<int, string>
     */
    private function icd9Codes(string $text): array
    {
        preg_match_all('/\b[0-9]{2}(?:\.[0-9]{1,2})?\b/', $text, $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
