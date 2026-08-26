<?php

namespace App\Domains\People\Actions;

use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\ParentGuardian;
use Illuminate\Support\Facades\DB;

class ListStudentFormOptionsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        return [
            'schools' => DB::table('schools')->orderBy('name')->get(['id', 'name'])->values(),
            'classes' => DB::table('classes')
                ->orderBy('name')
                ->orderBy('section')
                ->get(['id', 'name', 'section', 'academic_year_id'])
                ->map(fn (object $class) => [
                    'id' => $class->id,
                    'academic_year_id' => $class->academic_year_id,
                    'name' => $class->name,
                    'section' => $class->section,
                    'label' => trim($class->name.' '.$class->section),
                ])
                ->values(),
            'guardians' => ParentGuardian::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (ParentGuardian $guardian) => [
                    'id' => $guardian->id,
                    'name' => $guardian->full_name,
                ])
                ->values(),
            'statuses' => array_map(fn (StudentStatus $status) => $status->value, StudentStatus::cases()),
            'relationships' => array_map(fn (GuardianRelationship $rel) => $rel->value, GuardianRelationship::cases()),
        ];
    }
}
