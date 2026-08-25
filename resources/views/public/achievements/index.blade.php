@extends('public.layouts.public')

@section('title', 'Achievements')
@section('description', 'Published school awards at Akuru Institute')

@section('content')
<div class="container mx-auto px-4 py-10">
    <h1 class="text-3xl font-bold text-brandMaroon-700 mb-6">Achievements</h1>
    @if ($achievements->isEmpty())
        <p class="text-gray-600">No published school awards yet.</p>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($achievements as $row)
                <article class="rounded-lg border bg-white p-4">
                    <h2 class="text-xl font-semibold text-brandMaroon-700">{{ $row['title'] }}</h2>
                    <p class="text-gray-700">{{ $row['student_name'] }}</p>
                    <p class="text-sm text-gray-500">{{ $row['awarded_date'] }}</p>
                    @if ($row['photo_allowed'] && $row['photo'])
                        <p class="mt-2 text-xs text-gray-500">Photo on file</p>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
