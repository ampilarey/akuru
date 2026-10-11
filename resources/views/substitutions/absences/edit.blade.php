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
    </div>
</div>
@endsection
