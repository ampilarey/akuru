@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold">Recipient groups</h1>
            <a href="{{ route('admin.prayer-times.islands') }}" class="text-sm text-brandBlue-600">Islands →</a>
        </div>
        <a href="{{ route('admin.prayer-times.groups.create') }}" class="btn-primary">New group</a>
    </div>
    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 rounded mb-4">{{ session('success') }}</div>
    @endif
    <table class="min-w-full bg-white border">
        <thead><tr class="text-left text-xs uppercase text-gray-500"><th class="p-2">Name</th><th class="p-2">Members</th><th class="p-2">Active</th></tr></thead>
        <tbody>
        @forelse($groups as $group)
            <tr class="border-t">
                <td class="p-2"><a class="text-brandBlue-700" href="{{ route('admin.prayer-times.groups.edit', $group) }}">{{ $group->name_en }}</a></td>
                <td class="p-2">{{ is_array($group->member_refs) ? count($group->member_refs) : 0 }}</td>
                <td class="p-2">{{ $group->is_active ? 'yes' : 'no' }}</td>
            </tr>
        @empty
            <tr><td class="p-4" colspan="3">No groups yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
