@extends('layouts.app')
@section('title', 'Instructors')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-5xl">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Instructors</h1>
        <a href="{{ route('admin.instructors.create') }}" class="btn-primary">+ Add Instructor</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-100 text-green-800 rounded">{{ session('success') }}</div>
    @endif

    <div class="card overflow-hidden">
        @if($instructors->count() > 0)
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Name</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Specialization</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Courses</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($instructors as $instructor)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            @if($instructor->photo)
                                <img src="{{ asset('storage/' . $instructor->photo) }}" alt="{{ $instructor->name }}"
                                     class="w-9 h-9 rounded-full object-cover shrink-0">
                            @else
                                <div class="w-9 h-9 rounded-full bg-brandBeige-200 flex items-center justify-center shrink-0 text-brandMaroon-600 font-semibold text-sm">
                                    {{ strtoupper(substr($instructor->name, 0, 1)) }}
                                </div>
                            @endif
                            <div>
                                <p class="font-medium text-gray-900">{{ $instructor->name }}</p>
                                @if($instructor->qualification)
                                    <p class="text-xs text-gray-500">{{ $instructor->qualification }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $instructor->specialization ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $instructor->courses_count }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-block text-xs px-2 py-0.5 rounded-full font-medium {{ $instructor->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $instructor->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-2">
                            <a href="{{ route('admin.instructors.edit', $instructor) }}" class="text-brandBlue-600 hover:text-brandBlue-800 text-xs">Edit</a>
                            <form method="POST" action="{{ route('admin.instructors.destroy', $instructor) }}"
                                  onsubmit="return confirm('Delete {{ addslashes($instructor->name) }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 border-t">{{ $instructors->links() }}</div>
        @else
        <div class="p-8 text-center text-gray-500">
            <p>No instructors yet.</p>
            <a href="{{ route('admin.instructors.create') }}" class="mt-3 inline-block btn-primary text-sm">Add your first instructor</a>
        </div>
        @endif
    </div>
</div>
@endsection
