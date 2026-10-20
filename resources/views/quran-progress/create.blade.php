@extends('layouts.app')

@section('title', __('quran.qp_add_title'))

@section('content')
<div class="p-4">
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h1 class="h6 m-0 font-weight-bold text-primary">{{ __('quran.qp_add_title') }}</h1>
            </div>
            <div class="card-body">
                <form action="{{ route('quran-progress.store') }}" method="POST">
                    @csrf
                    @include('quran-progress._form')
                </form>
            </div>
        </div>
    </div>
</div>
</div>
@endsection
