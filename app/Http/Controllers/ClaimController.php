<?php

namespace App\Http\Controllers;

use App\Enums\ClaimStatus;
use App\Enums\JenisPelayanan;
use App\Events\ClaimStatusChanged;
use App\Http\Requests\StoreClaimRequest;
use App\Http\Requests\UpdateClaimRequest;
use App\Models\ActivityLog;
use App\Models\Claim;
use App\Models\ClaimPeriod;
use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use App\Models\Patient;
use App\Services\IcdCodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClaimController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Claim::class);

        $hospitalId = $request->user()?->hospital_id;
        $status = (string) $request->input('status', 'all');
        $search = (string) $request->input('search', '');
        $selectedId = $request->input('selected_id');

        $baseQuery = fn () => Claim::with(['patient', 'documents', 'diagnoses', 'procedures'])
            ->forHospital($hospitalId);

        $claims = $baseQuery()
            ->status($status)
            ->search($search)
            ->orderBy('id')
            ->paginate(8)
            ->withQueryString();

        $detailQuery = fn () => Claim::with([
            'patient',
            'documents',
            'verifications',
            'risks',
            'diagnoses.icd10Code',
            'procedures.icd9cmCode',
        ])->forHospital($hospitalId);

        $selectedClaim = null;
        if ($selectedId !== null) {
            $selectedClaim = $detailQuery()->find($selectedId);
        }

        if ($selectedClaim === null && $claims->isNotEmpty()) {
            $selectedClaim = $detailQuery()->find($claims->first()->id);
        }

        $counts = Claim::forHospital($hospitalId)
            ->selectRaw(
                'COUNT(*) as all_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as menunggu_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as bermasalah_count,
                 SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as siap_count',
                [
                    ClaimStatus::MenungguVerifikasi->value,
                    ClaimStatus::Bermasalah->value,
                    ClaimStatus::SiapDiajukan->value,
                ],
            )
            ->first();

        return Inertia::render('Claims/Index', [
            'claims' => $claims,
            'selectedClaim' => $selectedClaim,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'counts' => [
                'all' => (int) ($counts->all_count ?? 0),
                'menunggu' => (int) ($counts->menunggu_count ?? 0),
                'bermasalah' => (int) ($counts->bermasalah_count ?? 0),
                'siap' => (int) ($counts->siap_count ?? 0),
            ],
            'icd10Codes' => Icd10Code::query()
                ->where('is_active', true)
                ->orderBy('kode')
                ->get(['id', 'kode', 'deskripsi_id', 'kategori']),
            'icd9cmCodes' => Icd9cmCode::query()
                ->where('is_active', true)
                ->orderBy('kode')
                ->get(['id', 'kode', 'deskripsi_id', 'kategori']),
        ]);
    }

    public function store(StoreClaimRequest $request, IcdCodingService $codingService)
    {
        $validated = $request->validated();
        $user = $request->user();

        $claim = DB::transaction(function () use ($validated, $user, $codingService) {
            $patient = Patient::firstOrCreate(
                ['no_rm' => $validated['rm_no']],
                [
                    'nama_lengkap' => $validated['patient_name'],
                    'jenis_kelamin' => $validated['gender'],
                    'usia' => $validated['age'],
                    'no_kis' => ($validated['kis_no'] ?? null) ?: null,
                ],
            );

            $status = ClaimStatus::from($validated['initial_status']);
            $admissionDate = Carbon::parse($validated['admission_date']);
            $dischargeDate = $validated['discharge_date']
                ? Carbon::parse($validated['discharge_date'])
                : $admissionDate->copy();
            $lengthOfStay = max(1, $admissionDate->diffInDays($dischargeDate));

            $claim = Claim::create([
                'nomor_klaim' => $this->generateClaimNumber(),
                'patient_id' => $patient->id,
                'hospital_id' => $user?->hospital_id,
                'period_id' => ClaimPeriod::where('hospital_id', $user?->hospital_id)
                    ->where('status', 'aktif')
                    ->value('id'),
                'created_by' => $user?->id,
                'no_sep' => $validated['sep_no'],
                'no_rujukan' => ($validated['no_rujukan'] ?? null) ?: null,
                'care_type' => $validated['care_type'],
                'jenis_pelayanan' => str_contains(Str::lower($validated['care_type']), 'jalan')
                    ? JenisPelayanan::RawatJalan
                    : JenisPelayanan::RawatInap,
                'kelas_rawat' => ($validated['kelas_rawat'] ?? null) ?: null,
                'tanggal_masuk' => $admissionDate,
                'tanggal_pulang' => $dischargeDate,
                'lama_rawat' => $lengthOfStay,
                'dpjp_nama' => $validated['dpjp'],
                'estimasi_biaya' => $validated['estimated_cost'],
                'biaya_disetujui' => 0,
                'status' => $status,
                'issue' => ($validated['issue_note'] ?? null) ?: null,
                'issue_desc' => 'Data klaim baru dimasukkan oleh petugas verifikator casemix.',
                'doc_sep' => false,
                'doc_rujukan' => false,
                'doc_resume' => false,
                'doc_billing' => false,
                'doc_toraks' => false,
            ]);

            $claim = $codingService->sync(
                $claim,
                $validated['primary_diagnosis_code'],
                $validated['secondary_diagnosis_codes'] ?? [],
                $validated['procedure_codes'] ?? [],
            );

            ActivityLog::create([
                'user_id' => $user?->id,
                'claim_id' => $claim->id,
                'action' => 'Tambah klaim baru',
                'description' => "Klaim {$claim->nomor_klaim} untuk pasien {$patient->nama_lengkap} berhasil didaftarkan.",
            ]);

            return $claim;
        });

        return redirect()->back()->with(
            'success',
            "Klaim baru atas nama {$claim->patient->nama_lengkap} ({$claim->nomor_klaim}) berhasil ditambahkan!",
        );
    }

    public function update(UpdateClaimRequest $request, Claim $claim, IcdCodingService $codingService)
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('update', $claim);

        $validated = $request->validated();
        $status = ClaimStatus::from($validated['status']);

        DB::transaction(function () use ($validated, $claim, $status, $request, $codingService) {
            $claim->patient->update([
                'nama_lengkap' => $validated['patient_name'],
                'no_rm' => $validated['rm_no'],
                'jenis_kelamin' => $validated['gender'],
                'usia' => $validated['age'],
                'no_kis' => $validated['kis_no'],
            ]);

            $claim->update([
                'no_sep' => $validated['sep_no'],
                'care_type' => $validated['care_type'],
                'tanggal_masuk' => $validated['admission_date'],
                'tanggal_pulang' => $validated['discharge_date'],
                'dpjp_nama' => $validated['dpjp'],
                'estimasi_biaya' => $validated['estimated_cost'],
                'status' => $status,
                'issue' => $validated['issue_note'],
            ]);

            $codingService->sync(
                $claim,
                $validated['primary_diagnosis_code'],
                $validated['secondary_diagnosis_codes'] ?? [],
                $validated['procedure_codes'] ?? [],
            );

            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'claim_id' => $claim->id,
                'action' => 'Ubah data klaim',
                'description' => "Data klaim {$claim->nomor_klaim} berhasil diperbarui.",
            ]);
        });

        return redirect()->back()->with('success', "Data klaim {$claim->nomor_klaim} berhasil diperbarui!");
    }

    public function updateStatus(Request $request, Claim $claim)
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('update', $claim);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ClaimStatus::class)],
        ]);

        $status = ClaimStatus::from($validated['status']);
        $previousStatus = $claim->status;

        DB::transaction(function () use ($claim, $status, $previousStatus, $request) {
            $claim->update(['status' => $status]);

            event(new ClaimStatusChanged($claim, $previousStatus, $request->user()));

            ActivityLog::create([
                'user_id' => $request->user()?->id,
                'claim_id' => $claim->id,
                'action' => 'Ubah status klaim',
                'description' => "Status klaim {$claim->nomor_klaim} diubah menjadi {$status->value}.",
            ]);
        });

        return redirect()->back()->with(
            'success',
            "Status klaim {$claim->nomor_klaim} diperbarui menjadi: {$status->label()}",
        );
    }

    public function destroy(Request $request, Claim $claim)
    {
        $claim = $this->scopedClaim($request, $claim);
        Gate::authorize('delete', $claim);

        $number = $claim->nomor_klaim;
        $claim->delete();

        return redirect()->route('klaim.index')->with('info', "Klaim {$number} telah dihapus dari sistem.");
    }

    public function export(Request $request): SymfonyResponse
    {
        Gate::authorize('export', Claim::class);

        $claims = Claim::with('patient')
            ->forHospital($request->user()?->hospital_id)
            ->orderBy('id')
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Nomor Klaim', 'Nama Pasien', 'No RM', 'Nomor SEP', 'Tanggal Masuk',
            'Tanggal Pulang', 'Jenis Layanan', 'Diagnosis Primer', 'Diagnosis Sekunder',
            'Estimasi Biaya', 'Status',
        ]);

        foreach ($claims as $claim) {
            fputcsv($handle, [
                $this->csvField($claim->nomor_klaim),
                $this->csvField($claim->patient?->nama_lengkap),
                $this->csvField($claim->patient?->no_rm),
                $this->csvField($claim->no_sep),
                $claim->tanggal_masuk?->format('Y-m-d'),
                $claim->tanggal_pulang?->format('Y-m-d'),
                $this->csvField($claim->care_type),
                $this->csvField($claim->diagnosa_primer),
                $this->csvField($claim->diagnosa_sekunder),
                $claim->estimasi_biaya,
                $claim->status?->label(),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Export_Klaim_'.now()->format('Ymd_His').'.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function scopedClaim(Request $request, Claim $claim): Claim
    {
        return Claim::forHospital($request->user()?->hospital_id)
            ->findOrFail($claim->id);
    }

    private function generateClaimNumber(): string
    {
        do {
            $number = 'KLM-'.now()->year.'-'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (Claim::withTrashed()->where('nomor_klaim', $number)->exists());

        return $number;
    }

    private function csvField(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;

        if (preg_match('/^[=+\-@\t\r]/', $text) === 1) {
            return "'".$text;
        }

        return $text;
    }
}
