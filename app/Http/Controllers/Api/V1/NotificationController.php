<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\Support\NotificationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in user's own in-app notifications (FRD V01.14, part 8.2): the bell (the latest five), the
 * notifications page (all of them: filters by type and time range, a search box) and "mark as read". Every query is
 * scoped to the caller (UserNotification::forUser) — there is no way to ask for anyone else's — and no Policy: a
 * notification exists only for the person it was written for.
 */
class NotificationController extends Controller
{
    /** All the caller's notifications, newest first. Filters: type, date_from / date_to, search (trip code, PO number, type). */
    public function index(Request $request)
    {
        $query = UserNotification::query()->forUser($request->user())->orderByDesc('id');

        $type = $request->string('type')->toString();
        $from = $request->string('date_from')->toString();
        $to = $request->string('date_to')->toString();
        $search = trim($request->string('search')->toString());

        $query
            ->when(in_array($type, NotificationType::ALL, true), fn ($q) => $q->where('type', $type))
            ->when($from !== '', fn ($q) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($to !== '', fn ($q) => $q->where('created_at', '<=', $to.' 23:59:59'))
            ->when($search !== '', function ($q) use ($search) {
                // the type column is a translated label, so a search also matches the types whose label contains it
                $types = collect(NotificationType::ALL)->filter(fn (string $t) => mb_stripos(NotificationType::label($t), $search) !== false)->values()->all();
                $q->where(fn ($w) => $w->where('subject', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhereIn('type', $types));
            });

        return NotificationResource::collection($query->paginate($this->perPage($request)));
    }

    /** The bell: the latest five and how many are unread. */
    public function latest(Request $request): JsonResponse
    {
        $own = UserNotification::query()->forUser($request->user());

        return response()->json([
            'data' => NotificationResource::collection((clone $own)->orderByDesc('id')->limit(5)->get())->resolve($request),
            'unread_count' => (clone $own)->whereNull('read_at')->count(),
        ]);
    }

    /** Marks the caller's notifications read — all of them, or just `ids`. */
    public function read(Request $request): JsonResponse
    {
        $request->validate(['ids' => ['sometimes', 'array', 'max:200'], 'ids.*' => ['integer']]);

        $marked = UserNotification::query()->forUser($request->user())->whereNull('read_at')
            ->when($request->has('ids'), fn ($q) => $q->whereIn('id', $request->input('ids', [])))
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['marked' => $marked]]);
    }
}
