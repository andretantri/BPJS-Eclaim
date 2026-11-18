<?php

namespace App\Http\Requests;

use App\Models\EclaimSync;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncEclaimRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('sync', EclaimSync::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'claim_ids' => ['sometimes', 'array', 'min:1'],
            'claim_ids.*' => ['integer', Rule::exists('claims', 'id')],
        ];
    }
}
