<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('claim_risks', function (Blueprint $table): void {
            $table->text('catatan_tindak_lanjut')->nullable()->after('rekomendasi');
        });
    }

    public function down(): void
    {
        Schema::table('claim_risks', function (Blueprint $table): void {
            $table->dropColumn('catatan_tindak_lanjut');
        });
    }
};
