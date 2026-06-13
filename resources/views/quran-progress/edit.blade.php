@extends('layouts.app')

@section('title', 'Edit Quran Progress')
@section('page-title', 'Edit Quran Progress')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">Edit Quran Progress</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('quran-progress.update', $quranProgress) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('quran-progress._form', ['record' => $quranProgress])
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
