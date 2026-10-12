<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintSubjectSet;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §4.4 (NT1): a grade's subject set. The office types one
 * subject per line in each language; the lines pair up by position, so the
 * three lists must be the same length.
 */
class SavePrintSubjectSetAction
{
    /** @param  array<string, mixed>  $data */
    public function execute(array $data, ?PrintSubjectSet $set = null): PrintSubjectSet
    {
        $lines = fn (string $key) => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) ($data[$key] ?? ''))), fn (string $s) => $s !== ''));
        $en = $lines('subjects_en');
        $dv = $lines('subjects_dv');
        $ar = $lines('subjects_ar');
        if ($en === []) {
            throw ValidationException::withMessages(['subjects_en' => __('print.error_subjects_empty')]);
        }
        if (count($dv) !== count($en) || count($ar) !== count($en)) {
            throw ValidationException::withMessages(['subjects_en' => __('print.error_subjects_count', ['en' => count($en), 'dv' => count($dv), 'ar' => count($ar)])]);
        }

        $subjects = [];
        foreach ($en as $i => $name) {
            $subjects[] = ['en' => $name, 'dv' => $dv[$i], 'ar' => $ar[$i]];
        }
        $set ??= new PrintSubjectSet;
        $set->fill([
            'label' => trim((string) $data['label']),
            'label_dv' => trim((string) ($data['label_dv'] ?? '')) ?: null,
            'label_ar' => trim((string) ($data['label_ar'] ?? '')) ?: null,
            'subjects' => $subjects,
            'is_active' => filter_var($data['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => (int) ($data['sort_order'] ?? $set->sort_order ?? 0),
        ]);
        $set->save();

        return $set;
    }
}
