<?php

namespace App\Http\Requests;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Claim::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'max:255'],
            'rm_no' => ['required', 'string', 'max:50'],
            'gender' => ['required', Rule::in(['Laki-laki', 'Perempuan'])],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'kis_no' => ['nullable', 'string', 'max:50'],
            'sep_no' => ['required', 'string', 'max:50', Rule::unique('claims', 'no_sep')],
            'no_rujukan' => ['nullable', 'string', 'max:50'],
            'care_type' => ['required', 'string', 'max:100'],
            'kelas_rawat' => ['nullable', 'string', 'max:50'],
            'admission_date' => ['required', 'date'],
            'discharge_date' => ['nullable', 'date', 'after_or_equal:admission_date'],
            'dpjp' => ['required', 'string', 'max:255'],
            'primary_diagnosis_code' => [
                'required',
                'string',
                'max:20',
                Rule::exists('icd10_codes', 'kode')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'secondary_diagnosis_codes' => ['nullable', 'array', 'max:5'],
            'secondary_diagnosis_codes.*' => [
                'string',
                'max:20',
                Rule::exists('icd10_codes', 'kode')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'procedure_codes' => ['nullable', 'array', 'max:10'],
            'procedure_codes.*' => [
                'string',
                'max:20',
                Rule::exists('icd9cm_codes', 'kode')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'estimated_cost' => ['required', 'numeric', 'min:0'],
            'initial_status' => ['required', Rule::enum(ClaimStatus::class)],
            'issue_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sep_no.required' => 'Nomor SEP wajib diisi.',
            'sep_no.unique' => 'Nomor SEP sudah terdaftar.',
            'dpjp.required' => 'Nama DPJP wajib diisi.',
            'primary_diagnosis_code.required' => 'Diagnosis primer wajib dipilih dari referensi ICD-10.',
            'primary_diagnosis_code.exists' => 'Kode ICD-10 primer tidak ditemukan pada referensi aktif.',
            'secondary_diagnosis_codes.*.exists' => 'Kode ICD-10 sekunder tidak ditemukan pada referensi aktif.',
            'procedure_codes.*.exists' => 'Kode ICD-9-CM tidak ditemukan pada referensi aktif.',
            'estimated_cost.required' => 'Estimasi biaya wajib diisi.',
            'initial_status.required' => 'Status awal klaim wajib dipilih.',
        ];
    }
}
