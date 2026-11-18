<?php

use App\Enums\ClaimStatus;
use App\Enums\EclaimSyncStatus;
use App\Enums\StatusVerifikasi;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, string>>
     */
    private const STATUS_MAPS = [
        'claims' => [
            'status' => [
                'Menunggu' => 'menunggu_verifikasi',
                'Bermasalah' => 'bermasalah',
                'Siap diajukan' => 'siap_diajukan',
                'Siap Diajukan' => 'siap_diajukan',
                'Diajukan' => 'diajukan',
                'Disetujui' => 'disetujui',
                'Ditolak' => 'ditolak',
            ],
            'tingkat_risiko' => [
                'Rendah' => 'rendah',
                'Sedang' => 'sedang',
                'Tinggi' => 'tinggi',
                'Kritis' => 'kritis',
            ],
        ],
        'claim_documents' => [
            'tipe_dokumen' => [
                'toraks' => 'penunjang',
                'billing' => 'rincian_biaya',
            ],
            'status_verifikasi' => [
                'revisi' => 'perlu_revisi',
            ],
        ],
        'claim_risks' => [
            'tingkat_risiko' => [
                'Rendah' => 'rendah',
                'Sedang' => 'sedang',
                'Tinggi' => 'tinggi',
                'Kritis' => 'kritis',
            ],
            'status_tindak_lanjut' => [
                'Dalam Proses' => 'dalam_proses',
                'Terselesaikan' => 'terselesaikan',
                'Diterima Risiko' => 'diterima_risiko',
            ],
        ],
        'eclaim_syncs' => [
            'status' => [
                'partial' => 'failed',
            ],
        ],
    ];

    public function up(): void
    {
        foreach (self::STATUS_MAPS as $table => $columns) {
            foreach ($columns as $column => $map) {
                foreach ($map as $from => $to) {
                    DB::table($table)
                        ->where($column, $from)
                        ->update([$column => $to]);
                }
            }
        }

        Schema::table('claims', function (Blueprint $table): void {
            $table->text('catatan_temuan')->nullable()->after('issue_desc');
            $table->text('catatan_tindak_lanjut')->nullable()->after('catatan_temuan');
            $table->string('status')->default(ClaimStatus::Baru->value)->change();
            $table->index(['hospital_id', 'status'], 'claims_hospital_status_index');
            $table->index('status', 'claims_status_index');
        });

        Schema::table('claim_diagnoses', function (Blueprint $table): void {
            $table->foreignId('icd10_code_id')->nullable()->after('claim_id')->constrained('icd10_codes')->nullOnDelete();
        });

        Schema::table('claim_procedures', function (Blueprint $table): void {
            $table->foreignId('icd9cm_code_id')->nullable()->after('claim_id')->constrained('icd9cm_codes')->nullOnDelete();
        });

        Schema::table('icd10_codes', function (Blueprint $table): void {
            $table->string('sub_kategori')->nullable()->after('kategori');
        });

        Schema::table('claim_verifications', function (Blueprint $table): void {
            $table->string('status')->default(StatusVerifikasi::Belum->value)->change();
            $table->index(['claim_id', 'tahap'], 'claim_verifications_claim_tahap_index');
        });

        Schema::table('eclaim_syncs', function (Blueprint $table): void {
            $table->string('status')->default(EclaimSyncStatus::Pending->value)->change();
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'is_read'], 'notifications_user_read_index');
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->index(['claim_id', 'created_at'], 'activity_logs_claim_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex('activity_logs_claim_created_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_user_read_index');
        });

        Schema::table('eclaim_syncs', function (Blueprint $table): void {
            $table->string('status')->default('completed')->change();
        });

        Schema::table('claim_verifications', function (Blueprint $table): void {
            $table->dropIndex('claim_verifications_claim_tahap_index');
            $table->string('status')->default('lolos')->change();
        });

        Schema::table('icd10_codes', function (Blueprint $table): void {
            $table->dropColumn('sub_kategori');
        });

        Schema::table('claim_procedures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('icd9cm_code_id');
        });

        Schema::table('claim_diagnoses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('icd10_code_id');
        });

        Schema::table('claims', function (Blueprint $table): void {
            $table->dropIndex('claims_status_index');
            $table->dropIndex('claims_hospital_status_index');
            $table->string('status')->default('baru')->change();
            $table->dropColumn(['catatan_temuan', 'catatan_tindak_lanjut']);
        });

        foreach (self::STATUS_MAPS as $table => $columns) {
            foreach ($columns as $column => $map) {
                foreach ($map as $from => $to) {
                    DB::table($table)
                        ->where($column, $to)
                        ->update([$column => $from]);
                }
            }
        }
    }
};
