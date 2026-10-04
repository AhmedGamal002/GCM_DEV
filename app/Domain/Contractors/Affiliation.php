<?php

namespace App\Domain\Contractors;

/**
 * Turns the validated `affiliation` + `contractor_id` form fields into the
 * columns users, drivers, vehicles and assets store them in. A GCM-owned
 * record never keeps a contractor id, whatever was posted along with it.
 */
final class Affiliation
{
    /**
     * @param  array<string, mixed>  $data  validated request data
     * @return array{affiliation: string, contractor_id: ?int}
     */
    public static function attributes(array $data): array
    {
        $isContractor = ($data['affiliation'] ?? 'gcm') === 'contractor' && ! empty($data['contractor_id']);

        return [
            'affiliation' => $isContractor ? 'contractor' : 'gcm',
            'contractor_id' => $isContractor ? (int) $data['contractor_id'] : null,
        ];
    }
}
