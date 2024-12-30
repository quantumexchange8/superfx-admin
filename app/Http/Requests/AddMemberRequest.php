<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Validation\Rules\Password;
use Illuminate\Foundation\Http\FormRequest;

class AddMemberRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'regex:/^[a-zA-Z0-9\p{Han}. ]+$/u', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:' . User::class],
            'dial_code' => ['required'],
            'phone' => ['required', 'max:255', 'unique:' . User::class],
            'password' => ['required', Password::defaults(), 'confirmed'],
            'upline' => ['required'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return [
            'name' => 'Full Name',
            'email' => 'Email',
            'phone' => 'Phone Number',
            'password' => 'Password',
            'upline' => 'Upline',
        ];
    }
}
