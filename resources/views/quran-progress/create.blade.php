@extends('layouts.app')

@section('title', 'Add Quran Progress')
@section('page-title', 'Add Quran Progress')

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    @if(app()->getLocale() === 'ar')
                        إضافة تقدم جديد في القرآن الكريم
                    @elseif(app()->getLocale() === 'dv')
                        ޤުރުއާން ތަފްސީލް އެހިގަނޑު
                    @else
                        Add New Quran Progress
                    @endif
                </h6>
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
@endsection
