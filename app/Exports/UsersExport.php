<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

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
        return ['ID', 'Name', 'Email', 'Status', 'Roles', 'Created At'];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->status,
            $user->getRoleNames()->implode(', '),
            $user->created_at,
        ];
    }
}
