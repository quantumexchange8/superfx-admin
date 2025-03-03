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
            'min_deposit' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'account_type_name' => trans('public.account_type_name'),
            'category' => trans('public.category'),
            'descriptions.en' => trans('public.description_en'),
            'descriptions.tw' => trans('public.description_tw'),
            'leverage' => trans('public.leverage'),
            'trade_delay_duration' => trans('public.trade_delay_duration'),
            'max_account' => trans('public.max_account'),
            'min_deposit' => trans('public.min_deposit'),
        ];
    }
}
