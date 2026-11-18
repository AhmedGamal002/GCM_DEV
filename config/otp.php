<?php

/*
 * One-time passwords e-mailed to the account that is about to do something
 * sensitive (FRD V01.14 "الاشعارات والايميلات" / "ايميل OTP") — part 7.3.
 *
 * The numbers live here, never in the code (decision ج16, 2026-11-10): a
 * 6-digit code, valid for 10 minutes, at most 5 wrong tries, and a new code
 * can be asked for one minute after the last one. A new code cancels the old
 * one. The FRD asks for it "once per session": once the code is accepted, the
 * session (or the API token) stays verified for `verified_for_minutes`.
 */
return [
    'code_length' => (int) env('OTP_CODE_LENGTH', 6),
    'ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_after_seconds' => (int) env('OTP_RESEND_AFTER_SECONDS', 60),

    // How long an accepted code keeps the session / token verified. A reading of ours (the FRD only says "once
    // per session"): a working day, capped so a token that lives for 30 days doesn't stay verified that long.
    'verified_for_minutes' => (int) env('OTP_VERIFIED_FOR_MINUTES', 480),

    // Codes that can be sent to one account per hour (on top of the one-minute gap and the per-IP limit).
    'send_per_hour' => (int) env('OTP_SEND_PER_HOUR', 6),

    // Accounts allowed to ask for a code: the ones the FRD makes confirm sensitive trip actions with it —
    // GCM's System Admin and Data Entry, and the client's project manager / auditor.
    'roles' => ['system_admin', 'data_entry', 'client_project_manager', 'client_project_auditor'],
];
