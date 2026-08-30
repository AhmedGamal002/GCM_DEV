<?php

namespace App\Domain\Drivers\Actions;

use App\Models\Driver;
use App\Models\DriverEntryPermit;
use Illuminate\Http\UploadedFile;

class AddDriverEntryPermitAction
{
    /**
     * @param  array{area_name: string, permit_number: string, valid_to: string}  $data
     */
    public function execute(Driver $driver, array $data, ?UploadedFile $attachment): DriverEntryPermit
    {
        return $driver->entryPermits()->create([
            'area_name' => $data['area_name'],
            'permit_number' => $data['permit_number'],
            'valid_to' => $data['valid_to'],
            'attachment' => $attachment ? $attachment->store('driver-documents', 'local') : null,
        ]);
    }
}
