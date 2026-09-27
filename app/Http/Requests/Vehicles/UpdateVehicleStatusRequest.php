<?php

namespace App\Http\Requests\Vehicles;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleStatusRequest extends FormRequest
{
    private ?Vehicle $targetVehicle = null;

    private function targetVehicle(): Vehicle
    {
        return $this->targetVehicle ??= Vehicle::findOrFail($this->route('vehicle'));
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateStatus', $this->targetVehicle());
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['active', 'on_maintenance', 'deactivated'])],
        ];
    }
}
