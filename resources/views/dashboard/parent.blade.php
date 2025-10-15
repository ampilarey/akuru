@extends('layouts.app')

@section('title', 'Parent Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                {{ __('common.parent_dashboard') }}
            </h1>
            <p class="mt-2 text-gray-600">
                {{ __('common.parent_overview') }}
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- My Children Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-brandBlue-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            {{ __('common.my_children') }}
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $children->count() }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-brandBlue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-child text-brandBlue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quran Progress Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            {{ __('common.quran_progress') }}
                        </p>
                        <p class="text-3xl font-bold text-gray-900">0</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-quran text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Rate Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            {{ __('common.attendance_rate') }}
                        </p>
                        <p class="text-3xl font-bold text-gray-900">0%</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Children Section -->
        <div class="bg-white rounded-lg shadow-md mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ __('common.my_children') }}
                </h3>
            </div>
            <div class="p-6">
                @if($children->count() > 0)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($children as $child)
                            <div class="bg-gray-50 rounded-lg p-6 text-center">
                                @if($child->photo)
                                    <img src="{{ asset('storage/' . $child->photo) }}" 
                                         alt="{{ $child->full_name }}" 
                                         class="w-20 h-20 rounded-full mx-auto mb-4 object-cover">
                                @else
                                    <div class="w-20 h-20 bg-brandBlue-500 text-white rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold">
                                        {{ substr($child->first_name, 0, 1) }}
                                    </div>
                                @endif
                                <h4 class="text-lg font-semibold text-gray-900 mb-2">
                                    {{ $child->full_name }}
                                </h4>
                                <p class="text-gray-600 mb-1">{{ $child->student_id }}</p>
                                <p class="text-gray-500 mb-4">
                                    {{ $child->classRoom->name ?? __('common.not_assigned') }}
                                </p>
                                <div class="flex justify-center space-x-2">
                                    <a href="{{ route('students.show', $child) }}" class="inline-flex items-center px-3 py-2 bg-brandBlue-600 text-white text-sm rounded-lg hover:bg-brandBlue-700 transition-colors">
                                        <i class="fas fa-eye mr-2"></i>
                                        {{ __('common.view') }}
                                    </a>
                                    <a href="{{ route('students.quran-progress', $child) }}" class="inline-flex items-center px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-colors">
                                        <i class="fas fa-quran mr-2"></i>
                                        {{ __('common.quran') }}
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12">
                        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-child text-gray-400 text-2xl"></i>
                        </div>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">
                            {{ __('common.no_children_registered') }}
                        </h4>
                        <p class="text-gray-600 max-w-md mx-auto">
                            {{ __('common.contact_administration') }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">
                    {{ __('common.quick_actions') }}
                </h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <a href="{{ route('announcements.index') }}" class="flex items-center justify-center px-4 py-3 bg-brandBlue-600 text-white rounded-lg hover:bg-brandBlue-700 transition-colors">
                        <i class="fas fa-bullhorn mr-3"></i>
                        {{ __('common.announcements') }}
                    </a>
                    
                    <a href="{{ route('e-learning.index') }}" class="flex items-center justify-center px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                        <i class="fas fa-laptop mr-3"></i>
                        {{ __('common.e_learning') }}
                    </a>
                    
                    <a href="#" class="flex items-center justify-center px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-calendar mr-3"></i>
                        {{ __('common.calendar') }}
                    </a>
                    
                    <a href="#" class="flex items-center justify-center px-4 py-3 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                        <i class="fas fa-phone mr-3"></i>
                        {{ __('common.contact_us') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection