@extends('layouts.app')
@section('title', 'Hifz Milestones')
@section('content')
<div class="min-h-screen bg-gray-50 py-6"><div class="max-w-5xl mx-auto px-4">
<h1 class="text-2xl font-bold mb-6">Hifz Milestones</h1>
<div class="card overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="bg-gray-50"><th class="px-4 py-2 text-left">Student</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>@foreach($milestones as $m)<tr class="border-t"><td class="px-4 py-3">{{ $m->student->full_name }}</td><td>{{ str_replace('_',' ',$m->type->value) }}</td><td>{{ $m->status->value }}</td>
<td class="space-x-2">@if($m->status->value==='pending' && auth()->user()->isSupervisor())<form method="POST" action="{{ route('hifz.milestones.supervisor-review', $m) }}" class="inline">@csrf<button class="text-blue-600 text-xs">Review</button></form>@endif
@if($m->status->value==='supervisor_reviewed' && auth()->user()->isHifzDean())<form method="POST" action="{{ route('hifz.milestones.approve', $m) }}" class="inline">@csrf<button class="text-green-600 text-xs">Approve</button></form>@endif</td></tr>@endforeach</tbody></table></div>
{{ $milestones->links() }}
</div></div>
@endsection
