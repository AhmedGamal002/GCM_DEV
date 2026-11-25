<?php

namespace App\Http\Resources;

use App\Domain\Notifications\Support\NotificationType;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One notification as its recipient reads it: the type and the sentence are written now, in the reader's language,
 * from the stored `data` (never stored translated), and the link goes to the page of THAT reader's role — a trip
 * opens in GCM's pages for staff, the client's for client accounts, the contractor's for contractor users.
 *
 * @mixin UserNotification
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => NotificationType::label($this->type),
            'detail' => NotificationType::detail($this->type, $this->data ?? []),
            'subject' => $this->subject,
            'url' => $this->url($request->user()),
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at,
        ];
    }

    private function url(?User $viewer): ?string
    {
        if ($viewer === null) {
            return null;
        }

        if ($this->purchase_order_id !== null) {
            return $viewer->can('viewAny', PurchaseOrder::class) ? url('/app/po/view/'.$this->purchase_order_id) : null;
        }

        if ($this->trip_id === null) {
            return null;
        }

        $base = match (true) {
            $viewer->isClient() => '/app/client-trip/view/',
            $viewer->isContractorUser() => '/app/contractor-trip/view/',
            $viewer->hasRole('driver') => '/app/driver-trip/view/',
            default => '/app/trip/view/',
        };

        return url($base.$this->trip_id);
    }
}
