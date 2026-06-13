@extends('layouts.app')

@section('title', 'Record Teacher Absence')
@section('page-title', 'Record Teacher Absence')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('absences.store') }}" method="POST">
                    @csrf
                    @include('substitutions.absences._form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
