<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EditGroupRequest extends FormRequest
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
        $id = $this->id;

        return [
            'group_name' => ['required', 'string', Rule::unique(Group::class, 'name')->ignore($id)],
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
