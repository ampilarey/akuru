@extends('layouts.app')

@section('title', 'Hifz Session')
@section('content')
<div class="min-h-screen bg-gray-50 py-6">
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
@include('hifz.partials.alerts')

<div class="flex flex-wrap justify-between items-center mb-6 gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Daily Hifz Session</h1>
        <p class="text-gray-600">{{ $session->program->name }} · {{ $session->session_date->format('d M Y') }} · {{ $session->teacher->full_name }}</p>
    </div>
    <div class="flex gap-2">
        <form method="POST" action="{{ route('hifz.sessions.update', $session) }}">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn btn-primary">Complete Session</button>
        </form>
    </div>
</div>

<form method="POST" action="{{ route('hifz.sessions.records.bulk', $session) }}">
@csrf
<div class="card overflow-x-auto">
<table class="min-w-full text-sm">
<thead class="bg-gray-50">
<tr>
    <th class="px-3 py-2 text-left">Student</th>
    <th class="px-3 py-2">Attend.</th>
    <th class="px-3 py-2">New Mem. (Surah/Ayah)</th>
    <th class="px-3 py-2">Recent Rev.</th>
    <th class="px-3 py-2">Old Rev.</th>
    <th class="px-3 py-2">Result</th>
    <th class="px-3 py-2">Mistakes</th>
    <th class="px-3 py-2">Flags</th>
    <th class="px-3 py-2">Actions</th>
</tr>
</thead>
<tbody>
@foreach($session->records as $record)
<tr class="border-t align-top">
    <td class="px-3 py-3 font-medium">{{ $record->student->full_name }}</td>
    <td class="px-3 py-3">
        <select name="records[{{ $record->id }}][attendance_status]" class="form-input text-xs w-24">
            @foreach(['present','late','absent','excused'] as $s)
            <option value="{{ $s }}" @selected($record->attendance_status?->value === $s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-3 py-3">
        <div class="flex flex-col gap-1">
            <select name="records[{{ $record->id }}][new_from_surah_id]" class="form-input text-xs">
                <option value="">From Surah</option>
                @foreach($surahs as $surah)
                <option value="{{ $surah->id }}" @selected($record->new_from_surah_id == $surah->id)>{{ $surah->index }}. {{ $surah->english_name }}</option>
                @endforeach
            </select>
            <input type="number" name="records[{{ $record->id }}][new_from_ayah]" value="{{ $record->new_from_ayah }}" placeholder="From ayah" class="form-input text-xs w-20">
            <select name="records[{{ $record->id }}][new_to_surah_id]" class="form-input text-xs">
                <option value="">To Surah</option>
                @foreach($surahs as $surah)
                <option value="{{ $surah->id }}" @selected($record->new_to_surah_id == $surah->id)>{{ $surah->index }}. {{ $surah->english_name }}</option>
                @endforeach
            </select>
            <input type="number" name="records[{{ $record->id }}][new_to_ayah]" value="{{ $record->new_to_ayah }}" placeholder="To ayah" class="form-input text-xs w-20">
        </div>
    </td>
    <td class="px-3 py-3">
        <textarea name="records[{{ $record->id }}][recent_revision_text]" rows="2" class="form-input text-xs w-32">{{ $record->recent_revision_text }}</textarea>
        <select name="records[{{ $record->id }}][recent_revision_result]" class="form-input text-xs mt-1">
            <option value="">Result</option>
            @foreach(['pass','repeat','not_done'] as $r)
            <option value="{{ $r }}" @selected($record->recent_revision_result?->value === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-3 py-3">
        <textarea name="records[{{ $record->id }}][old_revision_text]" rows="2" class="form-input text-xs w-32">{{ $record->old_revision_text }}</textarea>
        <select name="records[{{ $record->id }}][old_revision_result]" class="form-input text-xs mt-1">
            <option value="">Result</option>
            @foreach(['pass','repeat','not_done'] as $r)
            <option value="{{ $r }}" @selected($record->old_revision_result?->value === $r)>{{ $r }}</option>
            @endforeach
        </select>
    </td>
    <td class="px-3 py-3">
        <select name="records[{{ $record->id }}][new_result]" class="form-input text-xs">
            <option value="">—</option>
            @foreach(['pass','pass_with_notes','repeat','not_prepared','not_done'] as $r)
            <option value="{{ $r }}" @selected($record->new_result?->value === $r)>{{ str_replace('_',' ',$r) }}</option>
            @endforeach
        </select>
        <input type="number" name="records[{{ $record->id }}][new_score]" value="{{ $record->new_score }}" min="0" max="100" placeholder="Score" class="form-input text-xs mt-1 w-16">
    </td>
    <td class="px-3 py-3 text-center">
        <span class="inline-block bg-gray-100 rounded px-2 py-1">{{ $record->mistake_count }}</span>
        <div class="text-xs text-gray-500 mt-1">H:{{ $record->haraka_mistakes }}</div>
    </td>
    <td class="px-3 py-3">
        <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="records[{{ $record->id }}][requires_parent_attention]" value="1" @checked($record->requires_parent_attention)> Parent</label>
        <label class="flex items-center gap-1 text-xs mt-1"><input type="checkbox" name="records[{{ $record->id }}][requires_supervisor_review]" value="1" @checked($record->requires_supervisor_review)> Supervisor</label>
    </td>
    <td class="px-3 py-3 whitespace-nowrap">
        <a href="{{ route('hifz.session-records.quran-page', $record) }}" class="text-brandMaroon-600 text-xs font-medium block mb-1">Quran Page</a>
        <a href="{{ route('hifz.students.history', $record->student) }}" class="text-gray-600 text-xs">History</a>
    </td>
</tr>
<tr class="border-t bg-gray-50">
    <td colspan="9" class="px-3 py-2">
        <div class="grid md:grid-cols-3 gap-2">
            <input type="text" name="records[{{ $record->id }}][teacher_note]" value="{{ $record->teacher_note }}" placeholder="Internal teacher note" class="form-input text-xs">
            <input type="text" name="records[{{ $record->id }}][parent_visible_note]" value="{{ $record->parent_visible_note }}" placeholder="Parent-visible note" class="form-input text-xs">
            <input type="text" name="records[{{ $record->id }}][next_target]" value="{{ $record->next_target }}" placeholder="Next target / homework" class="form-input text-xs">
        </div>
    </td>
</tr>
@endforeach
</tbody>
</table>
</div>
<div class="sticky bottom-0 bg-white border-t py-3 mt-4 flex justify-end">
    <button type="submit" class="btn btn-primary">Quick Save All</button>
</div>
</form>
</div>
</div>
@endsection
