@extends('layouts.app')

@section('title', 'Edit Teacher Absence')
@section('page-title', 'Edit Teacher Absence')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('absences.update', $absence) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('substitutions.absences._form', ['absence' => $absence])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
