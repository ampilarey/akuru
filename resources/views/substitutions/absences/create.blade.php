@extends('layouts.app')

@section('title', __('substitutions.record_title').' - '.config('app.name', 'Akuru LMS'))

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <h1 class="h3 mb-4">{{ __('substitutions.record_title') }}</h1>
        <div class="card">
            <div class="card-body">
                <form action="{{ route('absences.store') }}" method="POST" data-testid="absence-form">
                    @csrf
                    @include('substitutions.absences._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
