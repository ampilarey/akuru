@extends('layouts.app')

@section('title', 'Student Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                {{ __('common.student_dashboard') }}
            </h1>
            <p class="mt-2 text-gray-600">
                {{ __('common.student_overview') }}
            </p>
        </div>

        <!-- Student Info and Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
            <!-- Student Info Card -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('common.student_information') }}
                        </h3>
                    </div>
                    <div class="p-6 text-center">
                        @if(auth()->user()->student)
                            <div class="mb-4">
                                <div class="w-20 h-20 bg-brandBlue-100 rounded-full flex items-center justify-center mx-auto">
                                    <i class="fas fa-user-circle text-brandBlue-600 text-3xl"></i>
                                </div>
                            </div>
                            <h4 class="text-xl font-semibold text-gray-900 mb-2">
                                {{ auth()->user()->student->full_name }}
                            </h4>
                            <p class="text-gray-600 mb-2">{{ auth()->user()->student->student_id }}</p>
                            <p class="text-sm text-gray-500">
                                {{ __('common.class') }}: {{ auth()->user()->student->classRoom->name ?? __('common.not_assigned') }}
                            </p>
                        @else
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-user text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">{{ __('common.no_student_profile') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quran Progress -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('common.quran_progress') }}
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($stats['quran_progress']->count() > 0)
                            <div class="space-y-4">
                                @foreach($stats['quran_progress'] as $progress)
                                    <div class="flex items-center p-4 bg-gray-50 rounded-lg">
                                        <div class="flex-shrink-0">
                                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-quran text-green-600"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 ml-4">
                                            <h4 class="text-sm font-medium text-gray-900 mb-1">
                                                {{ $progress->surah_name }}
                                                <span class="font-arabic text-brandBlue-600">({{ $progress->surah_name_arabic }})</span>
                                            </h4>
                                            <div class="flex items-center space-x-2 mb-2">
                                                <div class="flex-1 bg-gray-200 rounded-full h-2">
                                                    <div class="bg-green-500 h-2 rounded-full" style="width: {{ $progress->accuracy_percentage ?? 0 }}%"></div>
                                                </div>
                                                <span class="text-sm text-gray-600">{{ $progress->accuracy_percentage ?? 0 }}%</span>
                                            </div>
                                            <p class="text-xs text-gray-500">
                                                {{ __('common.accuracy') }}: {{ $progress->accuracy_percentage ?? 0 }}%
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                @if($progress->status === 'completed') bg-green-100 text-green-800
                                                @elseif($progress->status === 'in_progress') bg-yellow-100 text-yellow-800
                                                @else bg-red-100 text-red-800 @endif">
                                                {{ ucfirst($progress->status) }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-12">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-quran text-gray-400 text-2xl"></i>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">
                                    {{ __('common.no_progress_recorded') }}
                                </h4>
                                <p class="text-gray-600">
                                    {{ __('common.start_quran_learning') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Grades and Attendance -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Recent Grades -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ __('common.recent_grades') }}
                    </h3>
                </div>
                <div class="p-6">
                    @if($stats['recent_grades']->count() > 0)
                        <div class="space-y-4">
                            @foreach($stats['recent_grades'] as $grade)
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div class="flex-1">
                                        <h4 class="text-sm font-medium text-gray-900 mb-1">
                                            {{ $grade->assignment_name }}
                                        </h4>
                                        <p class="text-xs text-gray-500">
                                            {{ $grade->subject->name ?? 'N/A' }}
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brandBlue-100 text-brandBlue-800">
                                            {{ $grade->score }}/{{ $grade->max_score }}
                                        </span>
                                        @if($grade->letter_grade)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                {{ $grade->letter_grade }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-graduation-cap text-gray-400 text-xl"></i>
                            </div>
                            <p class="text-gray-500">
                                {{ __('common.no_grades_recorded') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Attendance -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ __('common.attendance') }}
                    </h3>
                </div>
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mx-auto">
                            <i class="fas fa-calendar-check text-blue-600 text-2xl"></i>
                        </div>
                    </div>
                    <h4 class="text-3xl font-bold text-blue-600 mb-2">
                        {{ $stats['attendance_rate'] }}%
                    </h4>
                    <p class="text-gray-600">
                        {{ __('common.attendance_rate') }}
                    </p>
                    <div class="mt-4">
                        <div class="flex-1 bg-gray-200 rounded-full h-3">
                            <div class="bg-blue-500 h-3 rounded-full" style="width: {{ $stats['attendance_rate'] }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection