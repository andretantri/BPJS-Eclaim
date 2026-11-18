<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('no_rm')->unique();
            $table->string('nama_lengkap');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->default('Laki-laki');
            $table->date('tanggal_lahir')->nullable();
            $table->integer('usia')->default(0);
            $table->string('no_ktp')->nullable();
            $table->string('no_kis')->unique()->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->string('segmen_peserta')->nullable();
            $table->string('kelas_hak')->nullable();
            $table->string('faskes_tingkat_pertama')->nullable();
            $table->boolean('is_active_bpjs')->default(false);
            $table->timestamps();
        });

        Schema::create('claim_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospital_id')->constrained('hospitals')->cascadeOnDelete();
            $table->string('nama_periode');
            $table->integer('bulan');
            $table->integer('tahun');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('status', ['aktif', 'ditutup', 'diarsipkan'])->default('aktif');
            $table->decimal('total_nilai_klaim', 15, 2)->default(0);
            $table->decimal('total_dibayar', 15, 2)->default(0);
            $table->integer('jumlah_klaim')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claim_periods');
        Schema::dropIfExists('patients');
    }
};
