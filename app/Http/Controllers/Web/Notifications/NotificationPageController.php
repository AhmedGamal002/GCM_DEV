<?php

namespace App\Http\Controllers\Web\Notifications;

use App\Domain\Notifications\Support\NotificationType;
use App\Http\Controllers\Controller;

/**
 * The notifications page (FRD V01.14: "صفحة الاشعارات", reached from the bell) — a Blade shell for any signed-in tenant
 * user: it reads their own notifications from /api/v1/notifications, so there is nothing to authorize here beyond
 * being signed in (a notification exists only for the person it was written for).
 */
class NotificationPageController extends Controller
{
    public function index()
    {
        return view('tenant.notifications.list', [
            'types' => collect(NotificationType::ALL)->mapWithKeys(fn (string $type) => [$type => NotificationType::label($type)]),
        ]);
    }
}
