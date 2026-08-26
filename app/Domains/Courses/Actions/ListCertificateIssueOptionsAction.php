<?php

namespace App\Domains\Courses\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ListCertificateIssueOptionsAction
{
    /**
     * @return array{
     *     students: Collection<int, array{id: int, name: string}>,
     *     years: Collection<int, array{id: int, name: string}>,
     *     courses: Collection<int, array{id: int, title: string}>,
     *     offerings: Collection<int, array{id: int, course_id: int, title: string}>
     * }
     */
    public function execute(): array
    {
        return [
            'students' => DB::table('students')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'name' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')),
                ])
                ->values(),
            'years' => DB::table('academic_years')
                ->orderByDesc('id')
                ->get(['id', 'name'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'name' => (string) $row->name,
                ])
                ->values(),
            'courses' => DB::table('courses')
                ->orderBy('title')
                ->get(['id', 'title'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                ])
                ->values(),
            'offerings' => DB::table('course_offerings')
                ->whereNull('deleted_at')
                ->orderBy('title')
                ->get(['id', 'course_id', 'title'])
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'course_id' => (int) $row->course_id,
                    'title' => (string) $row->title,
                ])
                ->values(),
        ];
    }
}
