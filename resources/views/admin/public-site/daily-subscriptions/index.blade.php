@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-900">Daily subscriptions</h1>
            <a href="{{ route('admin.daily-content.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Daily content →</a>
            <a href="{{ route('admin.research.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Research →</a>
            <a href="{{ route('admin.prayer-times.islands') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Prayer times →</a>
            <a href="{{ route('admin.leads.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Leads →</a>
        </div>
        <a href="{{ route('admin.daily-subscriptions.export') }}" class="btn-secondary">Export CSV</a>
    </div>

    <p class="text-sm text-gray-600 mb-4">Opt-in only. Push rows are stored but not sent. Empty days are skipped silently so a later publish can still deliver.</p>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        @foreach(['sms','email','push'] as $channel)
            <div class="card p-4" data-metric-channel="{{ $channel }}">
                <h2 class="text-sm font-semibold text-gray-500 uppercase mb-2">{{ strtoupper($channel) }}</h2>
                <p class="text-2xl font-bold text-gray-900">{{ $metrics['totals'][$channel.'_active'] }} active</p>
                <p class="text-sm text-gray-500">{{ $metrics['totals'][$channel.'_paused'] }} paused</p>
            </div>
        @endforeach
    </div>

    <div class="card p-4 mb-8">
        <h2 class="text-lg font-semibold mb-3">Subscribers per type</h2>
        <p class="text-sm text-gray-700">
            Ayah {{ $metrics['types']['ayah'] }} · Hadith {{ $metrics['types']['hadith'] }} · Saying {{ $metrics['types']['saying'] }} · Reminder {{ $metrics['types']['reminder'] }}
        </p>
    </div>

    <div class="card p-4 mb-8">
        <h2 class="text-lg font-semibold mb-3">Delivery failures</h2>
        @forelse($metrics['failures'] as $failure)
            <p data-delivery-failure="{{ $failure['id'] }}" class="text-sm text-red-800 mb-1">
                #{{ $failure['subscription_id'] }} {{ $failure['channel'] }} {{ $failure['send_date'] }} — {{ $failure['error'] }}
            </p>
        @empty
            <p class="text-sm text-gray-500">No delivery failures recorded.</p>
        @endforelse
    </div>

    <div class="card overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Id</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Channel</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Types</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($metrics['rows'] as $row)
                    <tr>
                        <td class="px-6 py-4 text-sm">{{ $row['id'] }}</td>
                        <td class="px-6 py-4 text-sm">{{ $row['user_id'] }}</td>
                        <td class="px-6 py-4 text-sm">{{ $row['channel'] }}</td>
                        <td class="px-6 py-4 text-sm">{{ $row['status'] }}</td>
                        <td class="px-6 py-4 text-sm">{{ implode(', ', $row['content_types']) }}</td>
                        <td class="px-6 py-4 text-sm">{{ $row['send_time'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-4 text-sm text-gray-500">No subscribers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
