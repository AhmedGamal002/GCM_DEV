<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Mobile (bearer) tokens now expire — see config/sanctum.php; drop the dead rows once a day.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// One-time codes are useless after minutes; keep the table small (OtpChallenge::prunable() = older than a week).
Schedule::command('model:prune', ['--model' => [\App\Models\OtpChallenge::class]])->daily();

// Contract alerts the calendar caused (a line running low or out with no write) — part 8.2.
Schedule::command('notifications:check-purchase-orders')->dailyAt('01:00');
