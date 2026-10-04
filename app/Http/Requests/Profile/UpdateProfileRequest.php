<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FRD: what a user changes from their own profile page is the photo —
 * name and every other field are admin-managed — plus, for a client account
 * (FRD V01.14 §1.4), its signature and operational stamp images. At least
 * one of them must be sent; the signature/stamp are refused for everyone
 * who isn't a client account.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientOnly = Rule::prohibitedIf(! $this->user()->isClient());

        return [
            'photo' => ['required_without_all:signature,stamp', 'nullable', 'image', 'max:2048'],
            'signature' => [$clientOnly, 'nullable', 'image', 'max:2048'],
            'stamp' => [$clientOnly, 'nullable', 'image', 'max:2048'],
        ];
    }
}
