@php
    $stored = isset($course) && is_array($course->learning_outcomes) ? $course->learning_outcomes : [];
    $en = old('learning_outcomes_en', implode("\n", is_array($stored['en'] ?? null) ? $stored['en'] : []));
    $dv = old('learning_outcomes_dv', implode("\n", is_array($stored['dv'] ?? null) ? $stored['dv'] : []));
    $ar = old('learning_outcomes_ar', implode("\n", is_array($stored['ar'] ?? null) ? $stored['ar'] : []));
@endphp
<div class="space-y-3 rounded-xl border border-gray-200 p-4">
    <div>
        <p class="text-sm font-semibold text-gray-900">Learning outcomes</p>
        <p class="text-xs text-gray-500 mt-0.5">One outcome per line. Shown as “What you'll be able to do” on the public course page. Empty locales fall back to English.</p>
    </div>
    <div>
        <label for="learning_outcomes_en" class="block text-sm font-medium text-gray-700 mb-1">English</label>
        <textarea name="learning_outcomes_en" id="learning_outcomes_en" rows="4" class="form-input w-full rounded-md">{{ $en }}</textarea>
    </div>
    <div>
        <label for="learning_outcomes_dv" class="block text-sm font-medium text-gray-700 mb-1">Dhivehi</label>
        <textarea name="learning_outcomes_dv" id="learning_outcomes_dv" rows="4" dir="rtl" class="form-input w-full rounded-md">{{ $dv }}</textarea>
    </div>
    <div>
        <label for="learning_outcomes_ar" class="block text-sm font-medium text-gray-700 mb-1">Arabic</label>
        <textarea name="learning_outcomes_ar" id="learning_outcomes_ar" rows="4" dir="rtl" class="form-input w-full rounded-md">{{ $ar }}</textarea>
    </div>
</div>
