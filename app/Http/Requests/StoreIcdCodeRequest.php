<?php

namespace App\Http\Requests;

use App\Models\Icd10Code;
use App\Models\Icd9cmCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIcdCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', $this->modelClass()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['icd10', 'icd9cm'])],
            'kode' => [
                'required',
                'string',
                'max:20',
                Rule::unique($this->table(), 'kode'),
            ],
            'deskripsi_id' => ['required', 'string', 'max:255'],
            'deskripsi_en' => ['nullable', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode.required' => 'Kode ICD wajib diisi.',
            'kode.unique' => 'Kode ini sudah ada pada referensi. Gunakan menu pencarian untuk mengeceknya.',
            'deskripsi_id.required' => 'Deskripsi bahasa Indonesia wajib diisi.',
            'type.in' => 'Jenis referensi tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('kode')) {
            $this->merge([
                'kode' => strtoupper(trim((string) $this->input('kode'))),
            ]);
        }
    }

    /**
     * @return class-string<Icd10Code>|class-string<Icd9cmCode>
     */
    private function modelClass(): string
    {
        return $this->input('type') === 'icd9cm' ? Icd9cmCode::class : Icd10Code::class;
    }

    private function table(): string
    {
        return $this->input('type') === 'icd9cm' ? 'icd9cm_codes' : 'icd10_codes';
    }
}
