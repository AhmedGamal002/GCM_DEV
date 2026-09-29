<?php

namespace App\Support;

/**
 * Human labels for the raw enum values that appear in export cells
 * (`on_maintenance`, `contractor`, `data_entry`...), translated with the same
 * lang keys the on-screen lists use, so an Arabic PDF says "في الصيانة"
 * rather than "on_maintenance". Unknown values pass through unchanged.
 */
final class PdfLabels
{
    private const KEYS = [
        // lifecycle status (vehicles, assets, users)
        'active' => 'Active',
        'on_maintenance' => 'On Maintenance',
        'on_vacation' => 'On Vacation',
        'deactivated' => 'Deactivated',
        // affiliation
        'gcm' => 'GCM',
        'contractor' => 'Contractor',
        // asset type / category scope
        'container' => 'Container',
        'tank' => 'Tank',
        'both' => 'Both',
        // intermediate facility environmental service
        'disposal' => 'Safe disposal',
        'sewage_treatment' => 'Sewage treatment',
        'recycle' => 'Recycling',
        // GCM roles
        'system_admin' => 'System Admin',
        'data_entry' => 'Data Entry',
        'auditor' => 'Auditor',
        'driver' => 'Driver',
    ];

    public static function of(?string $value): string
    {
        $value = (string) $value;

        return isset(self::KEYS[$value]) ? __(self::KEYS[$value]) : $value;
    }
}
