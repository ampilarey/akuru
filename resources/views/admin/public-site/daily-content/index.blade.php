@extends('layouts.app')

@section('content')
@php
    $monthStart = \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->timezone(config('app.timezone'))->startOfMonth();
    $gridStart = $monthStart->copy()->startOfWeek(\Carbon\Carbon::SUNDAY);
    $gridEnd = $monthStart->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $byDate = collect($items)->groupBy('publish_date');
@endphp
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div class="flex items-center gap-4">
            <h1 class="text-3xl font-bold text-gray-900">Daily content</h1>
            <a href="{{ route('admin.daily-content.queue') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Approval queue →</a>
            <a href="{{ route('admin.daily-subscriptions.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Subscriptions →</a>
            <a href="{{ route('admin.research.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Research →</a>
            <a href="{{ route('admin.prayer-times.islands') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Prayer times →</a>
            <a href="{{ route('admin.leads.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Leads →</a>
            <a href="{{ route('admin.funnel.index') }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-sm font-medium">Funnel →</a>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.daily-content.export', request()->only(['month','status','content_type','theme_tag','q'])) }}" class="btn-secondary">Export CSV</a>
            <a href="{{ route('admin.daily-content.create') }}" class="btn-primary">New item</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
    @endif

    <p class="text-sm text-gray-600 mb-4">Maker–checker: a second reviewer with <code>daily_content.approve</code> must approve before schedule/publish. Hadith needs collection, number, grading, and grading source. No auto-generation.</p>

    <form method="GET" class="mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Month</label>
            <input type="month" name="month" value="{{ $month }}" class="form-input rounded-md">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Type</label>
            <select name="content_type" class="form-input rounded-md">
                <option value="">All</option>
                @foreach(['ayah','hadith','saying','reminder'] as $type)
                    <option value="{{ $type }}" @selected(($filters['content_type'] ?? '') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status" class="form-input rounded-md">
                <option value="">All</option>
                @foreach(['draft','scheduled','published','archived'] as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Theme</label>
            <input type="text" name="theme_tag" value="{{ $filters['theme_tag'] ?? '' }}" class="form-input rounded-md">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Search archive</label>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" class="form-input rounded-md">
        </div>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card p-4 mb-8">
        <h2 class="text-lg font-semibold mb-3">{{ $monthStart->format('F Y') }}</h2>
        <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:4px;font-size:.875rem">
            @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $label)
                <div style="text-align:center;font-size:.75rem;color:#6B7280;padding:.25rem 0">{{ $label }}</div>
            @endforeach
            @php $cursor = $gridStart->copy(); @endphp
            @while($cursor->lte($gridEnd))
                @php
                    $key = $cursor->toDateString();
                    $inMonth = $cursor->month === $monthStart->month;
                    $dayItems = $byDate->get($key, collect());
                @endphp
                <div style="min-height:5.5rem;border-radius:.375rem;padding:.25rem;{{ $inMonth ? 'background:#fff;border:1px solid #E5E7EB' : 'background:#F9FAFB;border:1px solid transparent;color:#9CA3AF' }}">
                    <div style="font-size:.75rem;color:#6B7280;margin-bottom:.25rem">{{ $cursor->day }}</div>
                    @foreach($dayItems as $cell)
                        <a href="{{ route('admin.daily-content.edit', $cell['id']) }}" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;line-height:1.25;margin-bottom:2px;text-decoration:none;{{ $cell['status'] === 'published' ? 'color:#15803D' : ($cell['status'] === 'draft' ? 'color:#B45309' : 'color:#374151') }}">
                            {{ $cell['content_type'] }}
                        </a>
                    @endforeach
                </div>
                @php $cursor->addDay(); @endphp
            @endwhile
        </div>
    </div>

    <div class="card mb-8">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($items as $item)
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $item['publish_date'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $item['content_type'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ $item['status'] }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                @if($item['content_type'] === 'ayah')
                                    {{ $item['ayah']['meanings']['en'] ?? ('Ayah '.$item['quran_ayah_id']) }}
                                @elseif($item['content_type'] === 'hadith')
                                    {{ $item['hadith_collection'] }} {{ $item['hadith_number'] }}
                                @else
                                    {{ \Illuminate\Support\Str::limit($item['text_en'] ?? $item['attribution'], 80) }}
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm"><a href="{{ route('admin.daily-content.edit', $item['id']) }}" class="text-brandBlue-600">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">No items this month.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="text-lg font-semibold mb-3">Theme batch (reminder drafts)</h2>
        <form method="POST" action="{{ route('admin.daily-content.batch') }}" class="grid md:grid-cols-2 gap-4">
            @csrf
            <div>
                <label class="block text-xs text-gray-500 mb-1">Start date</label>
                <input type="date" name="publish_date" required class="form-input rounded-md w-full">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Days (1–40)</label>
                <input type="number" name="days" min="1" max="40" value="30" class="form-input rounded-md w-full">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Theme tag</label>
                <input type="text" name="theme_tag" placeholder="ramadan" class="form-input rounded-md w-full">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Attribution</label>
                <input type="text" name="attribution" class="form-input rounded-md w-full">
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">English</label>
                <textarea name="text_en" rows="2" class="form-input rounded-md w-full"></textarea>
            </div>
            <div class="md:col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Dhivehi</label>
                <textarea name="text_dv" rows="2" dir="rtl" class="form-input rounded-md w-full"></textarea>
            </div>
            <div>
                <button class="btn-primary" type="submit">Create drafts</button>
            </div>
        </form>
    </div>
</div>
@endsection
