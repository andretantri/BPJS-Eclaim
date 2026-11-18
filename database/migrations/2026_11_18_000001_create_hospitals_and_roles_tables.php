<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitals', function (Blueprint $table) {
            $table->id();
            $table->string('kode_rs')->unique();
            $table->string('nama_rs');
            $table->string('alamat')->nullable();
            $table->string('kota')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('telepon')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->string('kode_ppk_bpjs')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hospital_id')->nullable()->constrained('hospitals')->nullOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('nip')->nullable();
            $table->string('jabatan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['hospital_id']);
            $table->dropForeign(['role_id']);
            $table->dropColumn(['hospital_id', 'role_id', 'nip', 'jabatan', 'is_active', 'last_login_at']);
        });

        Schema::dropIfExists('roles');
        Schema::dropIfExists('hospitals');
    }
};
