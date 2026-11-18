<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ICD Reference Tables
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('deskripsi_id');
            $table->string('deskripsi_en')->nullable();
            $table->string('kategori')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('icd9cm_codes', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('deskripsi_id');
            $table->string('deskripsi_en')->nullable();
            $table->string('kategori')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Claim Diagnoses and Procedures
        Schema::create('claim_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('kode_icd');
            $table->string('deskripsi');
            $table->enum('tipe', ['primer', 'sekunder'])->default('primer');
            $table->boolean('is_valid')->default(true);
            $table->text('catatan_validasi')->nullable();
            $table->timestamps();
        });

        Schema::create('claim_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('kode_prosedur');
            $table->string('deskripsi');
            $table->boolean('is_valid')->default(true);
            $table->text('catatan_validasi')->nullable();
            $table->timestamps();
        });

        // 6-step Verification records
        Schema::create('claim_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('tahap'); // 'vclaim', 'pra_klaim', 'icd10', 'icd9cm', 'risiko', 'tinjauan_akhir'
            $table->string('status')->default('lolos'); // 'lolos', 'gagal', 'perlu_revisi'
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('detail_hasil')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // Risk detection items
        Schema::create('claim_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('kode_temuan'); // e.g. ADM-01
            $table->string('judul_temuan');
            $table->text('deskripsi_temuan');
            $table->string('tingkat_risiko')->default('Sedang'); // Rendah, Sedang, Tinggi, Kritis
            $table->string('status_tindak_lanjut')->default('baru'); // baru, dalam_proses, terselesaikan
            $table->text('rekomendasi')->nullable();
            $table->text('bukti_pendukung')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        // E-Claim Sync & Batch Tables
        Schema::create('eclaim_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('batch_code')->unique();
            $table->string('tipe_sync')->default('batch_pengajuan'); // batch_pengajuan, single, penarikan_status
            $table->string('status')->default('completed'); // pending, processing, completed, failed
            $table->integer('total_items')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->json('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('eclaim_sync_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_id')->constrained('eclaim_syncs')->cascadeOnDelete();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('status')->default('success'); // success, failed
            $table->string('eclaim_response_code')->nullable();
            $table->text('eclaim_response_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });

        // Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained('claims')->nullOnDelete();
            $table->string('action');
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });

        // Notifications
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('claim_id')->nullable()->constrained('claims')->nullOnDelete();
            $table->string('tipe')->default('info');
            $table->string('judul');
            $table->text('pesan');
            $table->string('action_url')->nullable();
            $table->string('prioritas')->default('normal');
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('eclaim_sync_items');
        Schema::dropIfExists('eclaim_syncs');
        Schema::dropIfExists('claim_risks');
        Schema::dropIfExists('claim_verifications');
        Schema::dropIfExists('claim_procedures');
        Schema::dropIfExists('claim_diagnoses');
        Schema::dropIfExists('icd9cm_codes');
        Schema::dropIfExists('icd10_codes');
    }
};
