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
            'ib' => ['required'],
        ];
    }

    public function attributes(): array
    {
        return [
            'group_name' => trans('public.group_name'),
            'fee_charges' => trans('public.fee_charges'),
            'color' => trans('public.color'),
            'ib' => trans('public.ib'),
        ];
    }
}
