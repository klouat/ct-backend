<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
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
