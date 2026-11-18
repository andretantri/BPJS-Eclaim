<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'password' => ['nullable', 'filled', 'string', 'min:8', 'confirmed'],
            'hospital_id' => [
                'required',
                Rule::exists('hospitals', 'id')->where(fn ($query) => $query->where('id', $this->user()?->hospital_id)),
            ],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'nip' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.unique' => 'Email sudah digunakan akun lain.',
            'password.min' => 'Password minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'hospital_id.exists' => 'Rumah sakit harus sesuai dengan akun administrator.',
            'role_id.required' => 'Role wajib dipilih.',
            'role_id.exists' => 'Role tidak ditemukan.',
        ];
    }
}
