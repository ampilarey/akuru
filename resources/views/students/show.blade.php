@extends('layouts.app')

@section('title', 'Student Details')
@section('page-title', 'Student Details')

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                @if($student->photo)
                    <img src="{{ asset('storage/' . $student->photo) }}" 
                         alt="{{ $student->full_name }}" 
                         class="rounded-circle mb-3" 
                         width="150" height="150">
                @else
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" 
                         style="width: 150px; height: 150px; font-size: 3rem;">
                        {{ substr($student->first_name, 0, 1) }}
                    </div>
                @endif
                <h4>{{ $student->full_name }}</h4>
                <p class="text-muted">{{ $student->student_id }}</p>
                <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">
                    {{ ucfirst($student->status) }}
                </span>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Student Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Email:</strong> {{ $student->user->email }}</p>
                        <p><strong>Phone:</strong> {{ $student->phone ?? 'N/A' }}</p>
                        <p><strong>Date of Birth:</strong> {{ $student->date_of_birth->format('M d, Y') }}</p>
                        <p><strong>Gender:</strong> {{ ucfirst($student->gender) }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Class:</strong> {{ $student->classRoom->name ?? 'N/A' }}</p>
                        <p><strong>Admission Date:</strong> {{ $student->admission_date->format('M d, Y') }}</p>
                        <p><strong>National ID:</strong> {{ $student->national_id ?? 'N/A' }}</p>
                        <p><strong>Address:</strong> {{ $student->address ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
