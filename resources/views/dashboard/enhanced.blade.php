@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $dashboardData['title'] }}</h1>
                    <p class="mt-1 text-sm text-gray-600">Welcome back, {{ $user->name }}!</p>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-sm text-gray-500">
                        Last login: {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                    </div>
                    <div class="h-8 w-8 bg-brandMaroon-600 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold text-sm">{{ substr($user->name, 0, 1) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            @foreach($dashboardData['stats'] as $key => $value)
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-brandMaroon-100 rounded-md flex items-center justify-center">
                            <svg class="w-5 h-5 text-brandMaroon-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">{{ ucwords(str_replace('_', ' ', $key)) }}</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $value }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Recent Activities -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Recent Activities</h3>
                    </div>
                    <div class="p-6">
                        @if(isset($dashboardData['recent_activities']) && $dashboardData['recent_activities']->count() > 0)
                            <div class="space-y-4">
                                @foreach($dashboardData['recent_activities'] as $activity)
                                <div class="flex items-start space-x-3">
                                    <div class="flex-shrink-0">
                                        <div class="w-2 h-2 bg-brandMaroon-600 rounded-full mt-2"></div>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900">{{ $activity->activity_name }}</p>
                                        <p class="text-sm text-gray-600">{{ $activity->description }}</p>
                                        <p class="text-xs text-gray-500">{{ $activity->performed_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-4">No recent activities</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="space-y-6">
                <!-- Quick Actions Card -->
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @if($dashboardData['role'] === 'super_admin' || $dashboardData['role'] === 'admin')
                                <a href="{{ route('admin.users.index') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                    Manage Users
                                </a>
                                <a href="{{ route('admin.courses.index') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                    Manage Courses
                                </a>
                                <a href="{{ route('admin.posts.index') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                    Manage Posts
                                </a>
                            @endif
                            
                            @if($dashboardData['role'] === 'teacher')
                                <a href="{{ route('teacher.students.index') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                    View Students
                                </a>
                                <a href="{{ route('teacher.courses.index') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                    My Courses
                                </a>
                            @endif
                            
                            @if($dashboardData['role'] === 'student')
                                <a href="{{ route('student.courses.index') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                    My Courses
                                </a>
                                <a href="{{ route('student.progress') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                    View Progress
                                </a>
                            @endif
                            
                            <a href="{{ route('profile.edit') }}" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 rounded-md">
                                Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <!-- System Status (for admins) -->
                @if($dashboardData['role'] === 'super_admin' || $dashboardData['role'] === 'admin')
                <div class="bg-white rounded-lg shadow-sm border">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">System Status</h3>
                    </div>
                    <div class="p-6">
                        @if(isset($dashboardData['system_health']))
                            <div class="space-y-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Database</span>
                                    <span class="text-sm font-medium text-green-600">{{ $dashboardData['system_health']['database_status'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Storage</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $dashboardData['system_health']['storage_usage'] }}</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600">Active Users Today</span>
                                    <span class="text-sm font-medium text-gray-900">{{ $dashboardData['system_health']['active_users_today'] }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Role-specific content -->
        @if($dashboardData['role'] === 'student' && isset($dashboardData['upcoming_events']))
        <div class="mt-8">
            <div class="bg-white rounded-lg shadow-sm border">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Upcoming Events</h3>
                </div>
                <div class="p-6">
                    @if($dashboardData['upcoming_events']->count() > 0)
                        <div class="space-y-4">
                            @foreach($dashboardData['upcoming_events'] as $event)
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $event->title }}</h4>
                                    <p class="text-sm text-gray-600">{{ $event->start_date->format('M d, Y') }}</p>
                                </div>
                                <span class="text-sm text-brandMaroon-600 font-medium">{{ $event->start_date->diffForHumans() }}</span>
                            </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">No upcoming events</p>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
