<?php

namespace App\Domain\Drivers\Actions;

use App\Models\Driver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Updates the driver's account basics (name/phone/photo/additional_data
 * — same editable set as UpdateUserAction) plus the driver-specific
 * profile fields together. Status changes still go exclusively through
 * UpdateUserStatusAction/PATCH /api/v1/users/{id}/status — same
 * system_admin-can't-deactivate-themselves-out guard as regular users,
 * no reason to duplicate it here.
 */
class UpdateDriverAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, ?UploadedFile>  $documents  keys: residence, license, operational_license, insurance — only replaced if present
     */
    public function execute(Driver $driver, array $data, ?UploadedFile $photo, array $documents): Driver
    {
        return DB::transaction(function () use ($driver, $data, $photo, $documents) {
            $user = $driver->user;
            $user->name = $data['name'];
            $user->phone = $data['phone'];
            $user->additional_data = $data['additional_data'] ?? null;

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            if ($photo) {
                $user->photo = $photo->store('avatars', 'public');
            }

            $user->updated_by = auth()->id();
            $user->save();

            $driver->fill([
                'default_vehicle_id' => $data['default_vehicle_id'],
                'residence_number' => $data['residence_number'],
                'residence_valid_to' => $data['residence_valid_to'],
                'license_number' => $data['license_number'],
                'license_valid_to' => $data['license_valid_to'],
                'operational_license_number' => $data['operational_license_number'],
                'operational_license_valid_to' => $data['operational_license_valid_to'],
                'insurance_number' => $data['insurance_number'],
                'insurance_valid_to' => $data['insurance_valid_to'],
            ]);

            if ($documents['residence'] ?? null) {
                $driver->residence_attachment = $documents['residence']->store('driver-documents', 'local');
            }
            if ($documents['license'] ?? null) {
                $driver->license_attachment = $documents['license']->store('driver-documents', 'local');
            }
            if ($documents['operational_license'] ?? null) {
                $driver->operational_license_attachment = $documents['operational_license']->store('driver-documents', 'local');
            }
            if ($documents['insurance'] ?? null) {
                $driver->insurance_attachment = $documents['insurance']->store('driver-documents', 'local');
            }

            $driver->updated_by = auth()->id();
            $driver->save();

            $driver->qualifiedVehicleCategories()->sync($data['vehicle_category_ids']);

            return $driver->fresh(['user.roles', 'entryPermits', 'defaultVehicle.category', 'qualifiedVehicleCategories']);
        });
    }
}
