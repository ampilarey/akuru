@extends('layouts.app')

@section('title', 'Teacher Details')
@section('page-title', 'Teacher Details')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                @if($teacher->photo)
                    <img src="{{ asset('storage/' . $teacher->photo) }}" 
                         alt="{{ $teacher->full_name }}" 
                         class="rounded-circle mb-3" 
                         width="150" height="150">
                @else
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 150px; height: 150px; font-size: 3rem;">
                        {{ substr($teacher->first_name, 0, 1) }}
                    </div>
                @endif
                <h4>{{ $teacher->full_name }}</h4>
                <p class="text-muted">{{ $teacher->teacher_id }}</p>
                <span class="badge bg-{{ $teacher->status === 'active' ? 'success' : 'secondary' }}">
                    {{ ucfirst($teacher->status) }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Teacher Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Email:</strong> {{ $teacher->user->email }}</p>
                        <p><strong>Phone:</strong> {{ $teacher->phone ?? 'N/A' }}</p>
                        <p><strong>Date of Birth:</strong> {{ $teacher->date_of_birth->format('M d, Y') }}</p>
                        <p><strong>Gender:</strong> {{ ucfirst($teacher->gender) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Qualification:</strong> {{ $teacher->qualification }}</p>
                        <p><strong>Specialization:</strong> {{ $teacher->specialization }}</p>
                        <p><strong>Joining Date:</strong> {{ $teacher->joining_date->format('M d, Y') }}</p>
                        <p><strong>Salary:</strong> {{ $teacher->salary ? '$' . number_format($teacher->salary, 2) : 'N/A' }}</p>
                    </div>
                </div>
                
                @if($teacher->subjects->count() > 0)
                    <hr>
                    <h6>Subjects Taught:</h6>
                    <div class="row">
                        @foreach($teacher->subjects as $subject)
                            <div class="col-md-4 mb-2">
                                <span class="badge bg-info">{{ $subject->name }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
