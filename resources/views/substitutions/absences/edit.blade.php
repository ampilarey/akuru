@extends('layouts.app')

@section('title', __('substitutions.edit_absence_title').' - '.config('app.name', 'Akuru LMS'))

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <h1 class="h3 mb-4">{{ __('substitutions.edit_absence_title') }}</h1>
        <div class="card">
            <div class="card-body">
                <form action="{{ route('absences.update', $absence) }}" method="POST" data-testid="absence-form">
                    @csrf
                    @method('PUT')
                    @include('substitutions.absences._form', ['absence' => $absence])
                </form>
            </div>
        </div>
        {{-- KNOWN_ISSUES K2: an absence recorded in error can be removed. --}}
        <form action="{{ route('absences.destroy', $absence) }}" method="POST" class="mt-3 text-end">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger" onclick="return confirm(@js(__('substitutions.remove_absence_confirm')))" data-testid="remove-absence">{{ __('substitutions.remove_absence') }}</button>
        </form>
    </div>
</div>
@endsection
