<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupRequest extends FormRequest
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
            'group_name' => ['required', 'string', 'unique:groups,name'],
            'fee_charges' => ['required', 'numeric'],
            'color' => ['required'],
            'agent' => ['required'],
        ];
    }

    public function attributes(): array
    {
        return [
            'group_name' => 'Group Name',
            'fee_charges' => 'Fee Charges',
            'color' => 'Color',
            'agent' => 'Agent',
        ];
    }
}
