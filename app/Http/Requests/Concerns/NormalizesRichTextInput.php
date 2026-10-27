<?php

namespace App\Http\Requests\Concerns;

use App\Support\RichText;

/**
 * The "Additional Data" field across Users/Drivers/Vehicles/Assets is a
 * Quill rich-text editor — an untouched (or fully cleared) editor doesn't
 * submit an empty string, it submits `<p><br></p>` (Quill's own empty-
 * paragraph placeholder markup), which is `nullable|string`-valid and
 * non-empty, so it always got saved and always displayed the "Additional
 * Data" section on every view page with a heading over nothing. Real bug
 * flagged by the user across all four modules.
 *
 * Normalizing here (once, in `prepareForValidation()`) rather than in
 * each view page's JS fixes it at the source for every consumer at once
 * (PDF/Excel exports included), not just the Blade view pages that
 * happened to get patched.
 */
trait NormalizesRichTextInput
{
    /**
     * Also strips everything outside the editor's allowlist (scripts, event handlers, javascript: links...) — the view
     * pages render this field with `innerHTML`, see App\Support\RichText.
     */
    protected function normalizeRichText(?string $html): ?string
    {
        return RichText::sanitize($html);
    }
}
