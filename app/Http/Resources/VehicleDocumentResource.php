<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'document_number' => $this->document_number,
            'area_name' => $this->area_name,
            'valid_to' => $this->valid_to?->toDateString(),
            // The attachment is on the private disk — expose only whether
            // one exists and the Gate-checked download route, never a URL.
            'has_attachment' => $this->attachment_path !== null,
            'download_url' => $this->attachment_path
                ? route('api.vehicles.documents.download', ['vehicle' => $this->vehicle_id, 'document' => $this->id])
                : null,
        ];
    }
}
