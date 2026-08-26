<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', User::findOrFail($this->route('user')));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'on_vacation', 'deactivated'])],
        ];
    }
}
