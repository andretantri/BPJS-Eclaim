<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\JenisPelayanan;
use App\Enums\TingkatRisiko;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'nomor_klaim',
        'patient_id',
        'hospital_id',
        'period_id',
        'created_by',
        'no_sep',
        'no_rujukan',
        'care_type',
        'jenis_pelayanan',
        'kelas_rawat',
        'tanggal_masuk',
        'tanggal_pulang',
        'lama_rawat',
        'dpjp_nama',
        'dpjp_kode',
        'diagnosa_primer',
        'diagnosa_sekunder',
        'prosedur_utama',
        'estimasi_biaya',
        'biaya_disetujui',
        'status',
        'issue',
        'issue_desc',
        'tingkat_risiko',
        'jumlah_temuan_risiko',
        'doc_sep',
        'doc_rujukan',
        'doc_resume',
        'doc_billing',
        'doc_toraks',
        'grouper_code',
        'grouper_description',
        'tarif_ina_cbgs',
        'is_synced_eclaim',
        'synced_at',
        'eclaim_response',
    ];

    protected $casts = [
        'status' => ClaimStatus::class,
        'jenis_pelayanan' => JenisPelayanan::class,
        'tingkat_risiko' => TingkatRisiko::class,
        'tanggal_masuk' => 'date',
        'tanggal_pulang' => 'date',
        'lama_rawat' => 'integer',
        'estimasi_biaya' => 'decimal:2',
        'biaya_disetujui' => 'decimal:2',
        'tarif_ina_cbgs' => 'decimal:2',
        'jumlah_temuan_risiko' => 'integer',
        'doc_sep' => 'boolean',
        'doc_rujukan' => 'boolean',
        'doc_resume' => 'boolean',
        'doc_billing' => 'boolean',
        'doc_toraks' => 'boolean',
        'is_synced_eclaim' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(ClaimPeriod::class, 'period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClaimDocument::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(ClaimVerification::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ClaimDiagnosis::class);
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(ClaimProcedure::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ClaimRisk::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function eclaimItems(): HasMany
    {
        return $this->hasMany(EclaimSyncItem::class);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('nomor_klaim', 'like', "%{$term}%")
                ->orWhere('no_sep', 'like', "%{$term}%")
                ->orWhereHas('patient', function ($pq) use ($term) {
                    $pq->where('nama_lengkap', 'like', "%{$term}%")
                        ->orWhere('no_rm', 'like', "%{$term}%");
                });
        });
    }

    public function scopeStatus($query, ?string $status)
    {
        if (empty($status) || $status === 'all') {
            return $query;
        }

        $statusEnum = ClaimStatus::tryFrom($status);

        return $statusEnum
            ? $query->where('status', $statusEnum->value)
            : $query->whereRaw('1 = 0');
    }

    public function scopeForHospital($query, ?int $hospitalId)
    {
        return $hospitalId ? $query->where('hospital_id', $hospitalId) : $query;
    }

    public function scopeReadyToSubmit($query)
    {
        return $query->where('status', ClaimStatus::SiapDiajukan->value)
            ->where('is_synced_eclaim', false);
    }
}
