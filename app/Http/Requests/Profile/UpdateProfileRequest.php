<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD: what a user changes from their own profile page is the photo —
 * name and every other field are admin-managed, except that the System Admin
 * edits their own name and email too (client request) — plus, for a client account
 * (FRD V01.14 §1.4) and GCM's own staff, the signature and operational stamp
 * images. At least one of them must be sent; the signature/stamp are refused
 * for drivers and contractor users (User::canHaveSignatureStamp()).
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        $noSignature = Rule::prohibitedIf(! $user->canHaveSignatureStamp());
        $canEditIdentity = $user->canEditOwnIdentity();

        $rules = [
            // At least one thing has to be sent (name / email count too, for whoever may edit them).
            'photo' => ['required_without_all:signature,stamp'.($canEditIdentity ? ',name,email' : ''), 'nullable', 'image', 'max:2048'],
            'signature' => [$noSignature, 'nullable', 'image', 'max:2048'],
            'stamp' => [$noSignature, 'nullable', 'image', 'max:2048'],
        ];

        // Name and email: only for whoever canEditOwnIdentity(). For every other role they stay admin-managed:
        // with no rule they never reach validated(), so a name sent along with a photo is ignored.
        if ($canEditIdentity) {
            $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];
            // Same rule as the admin's user-edit form: login looks accounts up by email across the whole system.
            $rules['email'] = ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)];
        }

        return $rules;
    }
}
