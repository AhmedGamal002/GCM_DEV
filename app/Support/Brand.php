<?php

namespace App\Support;

/**
 * The product's name and logo, in one place — the sidebar/navbar brand, the
 * sign-in screens, the browser tab title and the page loader all read it
 * from here, so a rename is a one-line change.
 *
 * The name is "GLOBAL CLEAR MISSION" in English and "الرسالة الواضحة
 * العالمية" in Arabic (a lang key, so it follows the language switcher).
 * Platform (Super Admin) pages are always English, like the rest of that
 * area, whatever locale the tenant session holds.
 */
final class Brand
{
    public const NAME = 'GLOBAL CLEAR MISSION';

    public static function name(): string
    {
        return request()->is('platform', 'platform/*') ? self::NAME : __(self::NAME);
    }

    public static function logoUrl(): string
    {
        return asset('assets/img/branding/logo-light.png');
    }
}
