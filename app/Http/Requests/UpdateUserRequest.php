<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users', 'username')->ignore($user?->user_id, 'user_id'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user?->user_id, 'user_id'),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(['ADMIN', 'SUPERVISOR', 'PETUGAS_GUDANG', 'VENDOR'])],
            'vendor_id' => [
                Rule::requiredIf($this->string('role')->value() === 'VENDOR'),
                'nullable',
                'integer',
                'exists:vendors,vendor_id',
            ],
        ];
    }
}
