<?php

namespace App\Casts;

use App\Support\RichText;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * The rich-text "Additional Data" column: cleaned by App\Support\RichText on the way in (so the database never holds
 * script) AND on the way out (so rows saved before this existed — or by a seeder / console command that skipped the
 * form request — cannot reach the view pages' `innerHTML` either).
 */
class SanitizedRichText implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return RichText::sanitize($value === null ? null : (string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return RichText::sanitize($value === null ? null : (string) $value);
    }
}
