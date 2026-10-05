<?php

namespace App\Domain\Drivers\Actions;

use App\Domain\Contractors\Affiliation;
use App\Models\Driver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
     * @param  ?array<int, array{id?: ?int, area_name: string, permit_number: string, valid_to: string, attachment?: ?UploadedFile}>  $entryPermits
     *         the driver's full list of entry permits — null leaves them untouched
     */
    public function execute(Driver $driver, array $data, ?UploadedFile $photo, array $documents, ?array $entryPermits = null): Driver
    {
        return DB::transaction(function () use ($driver, $data, $photo, $documents, $entryPermits) {
            $user = $driver->user;
            $user->name = $data['name'];
            if (isset($data['email'])) {
                $user->email = $data['email'];
            }
            $user->phone = $data['phone'];
            $user->additional_data = $data['additional_data'] ?? null;

            // FRD §1.3: every field is editable, the affiliation included. It lives on
            // the account (users) and on the driver profile (drivers) — kept in step.
            $affiliation = array_key_exists('affiliation', $data) ? Affiliation::attributes($data) : null;
            if ($affiliation) {
                $user->affiliation = $affiliation['affiliation'];
                $user->contractor_id = $affiliation['contractor_id'];
            }

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            if ($photo) {
                $user->photo = $photo->store('avatars', 'public');
            }

            $user->updated_by = auth()->id();
            $user->save();

            $driver->fill([
                'default_vehicle_id' => $data['default_vehicle_id'] ?? null,
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

            if ($affiliation) {
                $driver->contractor_id = $affiliation['contractor_id'];
            }

            $driver->updated_by = auth()->id();
            $driver->save();

            $driver->qualifiedVehicleCategories()->sync($data['vehicle_category_ids']);

            if ($entryPermits !== null) {
                $this->syncEntryPermits($driver, $entryPermits);
            }

            return $driver->fresh(['user.roles', 'entryPermits', 'defaultVehicle.category', 'qualifiedVehicleCategories']);
        });
    }

    /**
     * The edit form sends the driver's whole permit list: a row with an id updates that permit (a
     * new file replaces the old one, no file keeps it), a row without one is added, and any
     * permit not in the list is removed together with its file. Same behaviour as the vehicle form.
     * An id that doesn't belong to this driver is treated as a new row, never as someone else's permit.
     *
     * @param  array<int, array{id?: ?int, area_name: string, permit_number: string, valid_to: string, attachment?: ?UploadedFile}>  $permits
     */
    private function syncEntryPermits(Driver $driver, array $permits): void
    {
        $keptIds = [];

        foreach ($permits as $permit) {
            $existing = empty($permit['id']) ? null : $driver->entryPermits()->find($permit['id']);
            $attachment = $permit['attachment'] ?? null;

            $attributes = [
                'area_name' => $permit['area_name'],
                'permit_number' => $permit['permit_number'],
                'valid_to' => $permit['valid_to'],
            ];

            if ($attachment) {
                if ($existing?->attachment) {
                    Storage::disk('local')->delete($existing->attachment);
                }
                $attributes['attachment'] = $attachment->store('driver-documents', 'local');
            }

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;
            } else {
                $keptIds[] = $driver->entryPermits()->create($attributes)->id;
            }
        }

        $driver->entryPermits()
            ->when($keptIds, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->get()
            ->each(function ($old) {
                if ($old->attachment) {
                    Storage::disk('local')->delete($old->attachment);
                }
                $old->delete();
            });
    }
}
