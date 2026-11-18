<?php

namespace App\Http\Requests;

use App\Enums\TipeDokumen;
use App\Models\Claim;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $claim = $this->route('claim');
        $user = $this->user();

        return $user !== null
            && $user->hasPermission('claims.verify')
            && $claim instanceof Claim
            && $claim->hospital_id === $user->hospital_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tipe_dokumen' => ['required', Rule::enum(TipeDokumen::class)->only([TipeDokumen::ResumeMedis])],
            'dokumen' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ];
    }
}
