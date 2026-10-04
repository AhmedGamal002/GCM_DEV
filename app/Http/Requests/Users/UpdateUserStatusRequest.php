<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = User::findOrFail($this->route('user'));

        // Say why, instead of the generic "unauthorized".
        if ($this->user()->is($target)) {
            throw new AuthorizationException(__('You cannot change the status of your own account — ask the System Admin or Data Entry.'));
        }

        return $this->user()->can('updateStatus', $target);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'on_vacation', 'deactivated'])],
        ];
    }
}
