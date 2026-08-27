@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Approval queue</h1>
        <a href="{{ route('admin.daily-content.index') }}" class="text-brandBlue-600 text-sm font-medium">Calendar →</a>
    </div>
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
    @endif
    <p class="text-sm text-gray-600 mb-4">The creator cannot approve their own item.</p>
    <div class="card">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preview</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($items as $item)
                        <tr>
                            <td class="px-6 py-4 text-sm">{{ $item['publish_date'] }}</td>
                            <td class="px-6 py-4 text-sm">{{ $item['content_type'] }}</td>
                            <td class="px-6 py-4 text-sm">
                                @if($item['content_type'] === 'hadith')
                                    {{ $item['hadith_collection'] }} {{ $item['hadith_number'] }} · {{ $item['hadith_grading'] }}
                                @elseif($item['content_type'] === 'ayah')
                                    {{ $item['ayah']['meanings']['en'] ?? 'Ayah' }}
                                @else
                                    {{ $item['text_en'] }}
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if((int) $item['created_by'] === (int) auth()->id())
                                    <span class="text-gray-500">Waiting for another reviewer</span>
                                @else
                                    <form method="POST" action="{{ route('admin.daily-content.approve', $item['id']) }}" class="flex flex-wrap gap-2">
                                        @csrf
                                        <button class="btn-secondary" type="submit" name="status" value="scheduled">Schedule</button>
                                        <button class="btn-primary" type="submit" name="status" value="published">Approve &amp; publish</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500">Nothing waiting.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
