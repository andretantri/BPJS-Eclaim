<?php

use App\Models\Claim;
use App\Services\IcdCodingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $codingService = app(IcdCodingService::class);

        Claim::query()->chunkById(100, function (Collection $claims) use ($codingService): void {
            foreach ($claims as $claim) {
                $hasCoding = DB::table('claim_diagnoses')->where('claim_id', $claim->id)->exists()
                    || DB::table('claim_procedures')->where('claim_id', $claim->id)->exists();

                if (! $hasCoding) {
                    $codingService->syncFromLegacy($claim);
                }
            }
        });
    }

    public function down(): void
    {
        DB::table('claim_diagnoses')->delete();
        DB::table('claim_procedures')->delete();
    }
};
