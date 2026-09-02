<?php

namespace App\Domain\Drivers\Actions;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the driver's User account (role `driver`) and their Driver
 * profile (residence/licenses/insurance/entry permits) together, in one
 * transaction — the FRD treats "create a driver" as a single form, not
 * "create a user, then separately attach driver data".
 *
 * Documents (residence/license/insurance/permit attachments) go on the
 * `local` disk, not `public` — unlike the profile photo, these are
 * sensitive official documents and must never get a guessable public
 * URL. They're served back out through a policy-gated download route
 * instead (DriverController::downloadDocument()).
 */
class CreateDriverAction
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, ?UploadedFile>  $documents  keys: residence, license, operational_license, insurance
     * @param  array<int, array{area_name: string, permit_number: string, valid_to: string, attachment?: ?UploadedFile}>  $entryPermits
     */
    public function execute(array $data, ?UploadedFile $photo, array $documents, array $entryPermits): Driver
    {
        return DB::transaction(function () use ($data, $photo, $documents, $entryPermits) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
                'additional_data' => $data['additional_data'] ?? null,
                'photo' => $photo ? $photo->store('avatars', 'public') : null,
                // Drivers can belong to a Contractor per the FRD, but
                // Contractor doesn't exist until Week 5 — always 'gcm'.
                'affiliation' => 'gcm',
            ]);
            $user->assignRole('driver');

            $driver = Driver::create([
                'user_id' => $user->id,
                'default_vehicle_id' => $data['default_vehicle_id'],
                'residence_number' => $data['residence_number'],
                'residence_valid_to' => $data['residence_valid_to'],
                'residence_attachment' => $this->storeDocument($documents['residence'] ?? null),
                'license_number' => $data['license_number'],
                'license_valid_to' => $data['license_valid_to'],
                'license_attachment' => $this->storeDocument($documents['license'] ?? null),
                'operational_license_number' => $data['operational_license_number'],
                'operational_license_valid_to' => $data['operational_license_valid_to'],
                'operational_license_attachment' => $this->storeDocument($documents['operational_license'] ?? null),
                'insurance_number' => $data['insurance_number'],
                'insurance_valid_to' => $data['insurance_valid_to'],
                'insurance_attachment' => $this->storeDocument($documents['insurance'] ?? null),
            ]);

            foreach ($entryPermits as $permit) {
                $driver->entryPermits()->create([
                    'area_name' => $permit['area_name'],
                    'permit_number' => $permit['permit_number'],
                    'valid_to' => $permit['valid_to'],
                    'attachment' => $this->storeDocument($permit['attachment'] ?? null),
                ]);
            }

            $driver->qualifiedVehicleCategories()->sync($data['vehicle_category_ids']);

            return $driver->load(['user.roles', 'entryPermits', 'defaultVehicle.category', 'qualifiedVehicleCategories']);
        });
    }

    private function storeDocument(?UploadedFile $file): ?string
    {
        return $file ? $file->store('driver-documents', 'local') : null;
    }
}
