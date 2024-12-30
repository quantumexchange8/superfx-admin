<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountTypeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_type_name' => ['required'],
            'category' => ['required'],
            'descriptions.*' => ['sometimes', 'required'],
            'leverage' => ['required', 'numeric'],
            'trade_delay_duration' => ['required'],
            'max_account' => ['required', 'numeric'],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_type_name' => 'account_type_name',
            'category' => 'category',
            'descriptions' => 'description',
            'leverage' => 'leverage',
            'trade_delay_duration' => 'trade_delay_duration',
            'max_account' => 'max_account',
        ];
    }
}
