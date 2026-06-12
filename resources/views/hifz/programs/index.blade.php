@extends('layouts.app')
@section('title', 'Hifz Programs')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-7xl mx-auto px-4">
@include('hifz.partials.alerts')
<div class="flex justify-between mb-6"><h1 class="text-2xl font-bold">Hifz Programs</h1>
@can('create', App\Domains\Hifz\Models\HifzProgram::class)<a href="{{ route('hifz.programs.create') }}" class="btn btn-primary">New Program</a>@endcan
</div>
<div class="card overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Name</th><th>Class</th><th>Supervisor</th><th>Status</th><th></th></tr></thead>
<tbody>@foreach($programs as $p)<tr class="border-t"><td class="px-4 py-3">{{ $p->name }}</td><td>{{ $p->classRoom->name ?? '—' }}</td><td>{{ $p->supervisor->name ?? '—' }}</td><td><span class="px-2 py-1 rounded bg-gray-100">{{ $p->status->value }}</span></td><td><a href="{{ route('hifz.programs.show', $p) }}" class="text-brandMaroon-600">View</a></td></tr>@endforeach</tbody></table></div>
{{ $programs->links() }}
</div></div>
@endsection
