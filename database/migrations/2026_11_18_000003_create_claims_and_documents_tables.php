<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_klaim')->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('claim_periods')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('no_sep')->unique();
            $table->string('no_rujukan')->nullable();
            $table->string('care_type')->nullable();
            $table->string('jenis_pelayanan')->default('rawat_inap'); // rawat_inap / rawat_jalan
            $table->string('kelas_rawat')->nullable();
            $table->date('tanggal_masuk');
            $table->date('tanggal_pulang')->nullable();
            $table->integer('lama_rawat')->default(1);

            $table->string('dpjp_nama')->nullable();
            $table->string('dpjp_kode')->nullable();

            $table->string('diagnosa_primer')->nullable();
            $table->string('diagnosa_sekunder')->nullable();
            $table->string('prosedur_utama')->nullable();

            $table->decimal('estimasi_biaya', 15, 2)->default(0);
            $table->decimal('biaya_disetujui', 15, 2)->default(0);

            $table->string('status')->default('baru');
            $table->string('issue')->nullable();
            $table->text('issue_desc')->nullable();

            $table->string('tingkat_risiko')->nullable();
            $table->integer('jumlah_temuan_risiko')->default(0);

            // Document checklist flags (for quick indexing/caching)
            $table->boolean('doc_sep')->default(false);
            $table->boolean('doc_rujukan')->default(false);
            $table->boolean('doc_resume')->default(false);
            $table->boolean('doc_billing')->default(false);
            $table->boolean('doc_toraks')->default(false);

            // INA-CBGs & E-Claim Sync fields
            $table->string('grouper_code')->nullable();
            $table->string('grouper_description')->nullable();
            $table->decimal('tarif_ina_cbgs', 15, 2)->nullable();
            $table->boolean('is_synced_eclaim')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->text('eclaim_response')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('claim_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('claim_id')->constrained('claims')->cascadeOnDelete();
            $table->string('tipe_dokumen'); // 'sep', 'rujukan', 'resume_medis', 'rincian_biaya', 'toraks', 'penunjang'
            $table->string('nama_file');
            $table->string('path_file')->nullable();
            $table->integer('ukuran_file')->default(0); // in KB
            $table->string('mime_type')->default('application/pdf');
            $table->string('status_verifikasi')->default('belum');
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_documents');
        Schema::dropIfExists('claims');
    }
};
