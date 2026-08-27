@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-900">Prayer islands</h1>
            <a href="{{ route('admin.prayer-times.import') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Import →</a>
            <a href="{{ route('admin.prayer-times.groups.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Groups →</a>
            <a href="{{ route('admin.prayer-times.broadcasts.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Broadcasts →</a>
            <a href="{{ route('admin.daily-content.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Daily content →</a>
        </div>
        <a href="{{ route('admin.prayer-times.islands.export') }}" class="btn-secondary">Export CSV</a>
    </div>
    <p class="text-sm text-gray-600 mb-4">Cache version {{ $cacheVersion }} · default island {{ $defaultIslandId ?: 'unset' }}</p>
    <table class="min-w-full bg-white border">
        <thead><tr class="text-left text-xs uppercase text-gray-500">
            <th class="p-2">ID</th><th class="p-2">Island</th><th class="p-2">Atoll</th><th class="p-2">Offset</th><th class="p-2">Lat/Lng</th><th class="p-2">Active</th>
        </tr></thead>
        <tbody>
        @forelse($islands as $island)
            <tr class="border-t">
                <td class="p-2">{{ $island->id }}</td>
                <td class="p-2">{{ $island->nameEn }} <span class="text-gray-500 text-sm">{{ $island->nameDv }}</span></td>
                <td class="p-2">{{ $island->atollLatin }}</td>
                <td class="p-2">{{ $island->offsetMinutes }}</td>
                <td class="p-2">{{ $island->latitude }}, {{ $island->longitude }}</td>
                <td class="p-2">{{ $island->isActive ? 'yes' : 'no' }}</td>
            </tr>
        @empty
            <tr><td class="p-4" colspan="6">No islands. Import salat.db or seed the synthetic fixture.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
