<?php

namespace App\Domain\Users\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Columns match the FRD's list-table spec exactly: الرقم التعريفي /
 * الاسم / التبعية / اسم الجهة التابع لها / الدور الوظيفي / حالة الحساب
 * — i.e. what's actually on screen in app-user-list, not an arbitrary
 * export-only column set (email/created_at were never in that spec).
 */
class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $users)
    {
    }

    public function collection(): Collection
    {
        return $this->users;
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Affiliation', 'Entity', 'Role', 'Status'];
    }

    public function map($user): array
    {
        return [
            $user->code,
            $user->name,
            $user->affiliation === 'gcm' ? 'GCM' : $user->affiliation,
            $user->affiliation === 'gcm' ? $user->tenant->name : null,
            $user->getRoleNames()->implode(', '),
            $user->status,
        ];
    }
}
