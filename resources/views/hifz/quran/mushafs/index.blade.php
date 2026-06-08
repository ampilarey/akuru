@extends('layouts.app')
@section('title', 'Quran Mushafs')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-5xl mx-auto px-4">
<div class="flex justify-between mb-6"><h1 class="text-2xl font-bold">Quran Mushafs</h1>@can('manage', App\Models\QuranMushaf::class)<a href="{{ route('hifz.quran.mushafs.create') }}" class="btn btn-primary">Upload Mushaf</a>@endcan</div>
<div class="card overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Name</th><th>Pages</th><th>Active</th><th>Locked</th><th></th></tr></thead>
<tbody>@foreach($mushafs as $m)<tr class="border-t"><td class="px-4 py-3">{{ $m->name }}</td><td>{{ $m->page_count ?? $m->pages_count }}</td><td>{{ $m->is_active ? 'Yes' : 'No' }}</td><td>{{ $m->locked ? 'Yes' : 'No' }}</td><td><a href="{{ route('hifz.quran.mushafs.show', $m) }}">Manage</a></td></tr>@endforeach</tbody></table></div>
{{ $mushafs->links() }}
</div></div>
@endsection
