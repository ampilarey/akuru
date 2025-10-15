@extends('layouts.app')

@section('title', 'Teacher Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                {{ __('common.teacher_dashboard') }}
            </h1>
            <p class="mt-2 text-gray-600">
                {{ __('common.teacher_overview') }}
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- My Students Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-brandBlue-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            {{ __('common.my_students') }}
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['my_students'] }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-brandBlue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-graduate text-brandBlue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Grades Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            {{ __('common.pending_grades') }}
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['pending_grades'] }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-tasks text-yellow-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quran Updates Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            {{ __('common.quran_updates') }}
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['quran_progress_updates'] }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-quran text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Classes Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            {{ __('common.todays_classes') }}
                        </p>
                        <p class="text-3xl font-bold text-gray-900">0</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- My Students Section -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('common.my_students') }}
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="text-center py-12">
                            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-graduate text-gray-400 text-2xl"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">
                                {{ __('common.no_student_data') }}
                            </h4>
                            <p class="text-gray-600 max-w-md mx-auto">
                                {{ __('common.student_list_will_appear') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ __('common.quick_actions') }}
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            <a href="{{ route('quran-progress.index') }}" class="w-full flex items-center justify-center px-4 py-3 bg-brandBlue-600 text-white rounded-lg hover:bg-brandBlue-700 transition-colors">
                                <i class="fas fa-quran mr-3"></i>
                                {{ __('common.quran_progress') }}
                            </a>
                            
                            <a href="{{ route('e-learning.index') }}" class="w-full flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                <i class="fas fa-laptop mr-3"></i>
                                {{ __('common.e_learning') }}
                            </a>
                            
                            <a href="{{ route('announcements.index') }}" class="w-full flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                <i class="fas fa-bullhorn mr-3"></i>
                                {{ __('common.announcements') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection