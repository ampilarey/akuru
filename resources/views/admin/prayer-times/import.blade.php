@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="flex items-center gap-4 mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Import prayer times</h1>
        <a href="{{ route('admin.prayer-times.islands') }}" class="text-sm text-gray-500">Islands →</a>
    </div>
    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-100 text-red-800 rounded text-sm">{{ $errors->first() }}</div>
    @endif
    <p class="text-sm text-gray-600 mb-4">Cache version {{ $cacheVersion }}. Import fails unless every category has 366 rows. <code>salat.db</code> is operator-supplied and not stored in git.</p>
    <form method="POST" action="{{ route('admin.prayer-times.import.store') }}" enctype="multipart/form-data" class="bg-white p-6 rounded border mb-4">
        @csrf
        <label class="block text-sm mb-2">salat.db</label>
        <input type="file" name="salat_db" accept=".db,.sqlite" class="form-input mb-4">
        <button class="btn-primary">Import</button>
    </form>
    <form method="POST" action="{{ route('admin.prayer-times.import.store') }}">
        @csrf
        <input type="hidden" name="seed_fixture" value="1">
        <button class="btn-secondary">Seed synthetic Malé 366-day fixture</button>
    </form>
</div>
@endsection
