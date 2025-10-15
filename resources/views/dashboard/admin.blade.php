@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">
                {{ __('common.admin_dashboard') }}
            </h1>
            <p class="mt-2 text-gray-600">
                {{ __('common.overview_lms') }}
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Students Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-brandBlue-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            @if(app()->getLocale() === 'ar')
                                إجمالي الطلاب
                            @elseif(app()->getLocale() === 'dv')
                                ރަގަޅުތައް ގެ ތެރޭ
                            @else
                                Total Students
                            @endif
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['total_students'] }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-brandBlue-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-user-graduate text-brandBlue-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Teachers Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            @if(app()->getLocale() === 'ar')
                                إجمالي المعلمين
                            @elseif(app()->getLocale() === 'dv')
                                އުސްތާދުތައް ގެ ތެރޭ
                            @else
                                Total Teachers
                            @endif
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['total_teachers'] }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Quran Students Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            @if(app()->getLocale() === 'ar')
                                طلاب القرآن النشطين
                            @elseif(app()->getLocale() === 'dv')
                                ޤުރުއާން ރަގަޅުތައް
                            @else
                                Active Quran Students
                            @endif
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['active_quran_students'] }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-quran text-purple-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Announcements Card -->
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-600 uppercase tracking-wide">
                            @if(app()->getLocale() === 'ar')
                                الإعلانات الأخيرة
                            @elseif(app()->getLocale() === 'dv')
                                އެންމެ ފަހުގެ އެކްސް
                            @else
                                Recent Announcements
                            @endif
                        </p>
                        <p class="text-3xl font-bold text-gray-900">{{ $stats['recent_announcements']->count() }}</p>
                    </div>
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <i class="fas fa-bullhorn text-orange-600 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Metrics Section -->
        @if(isset($metrics))
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Student Growth Chart -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ __('common.student_growth') }}
                    </h3>
                </div>
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-brandBlue-600">{{ $metrics['student_growth']['current_month'] ?? 0 }}</p>
                            <p class="text-sm text-gray-600">{{ __('common.this_month') }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-gray-600">{{ $metrics['student_growth']['last_month'] ?? 0 }}</p>
                            <p class="text-sm text-gray-600">{{ __('common.last_month') }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold {{ ($metrics['student_growth']['growth_rate'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $metrics['student_growth']['growth_rate'] ?? 0 }}%
                            </p>
                            <p class="text-sm text-gray-600">{{ __('common.growth_rate') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quran Progress Overview -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ __('common.quran_progress_stats') }}
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center">
                            <p class="text-2xl font-bold text-green-600">{{ $metrics['quran_progress_stats']['completed_surahs'] ?? 0 }}</p>
                            <p class="text-sm text-gray-600">{{ __('common.completed_surahs') }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-bold text-yellow-600">{{ $metrics['quran_progress_stats']['in_progress_surahs'] ?? 0 }}</p>
                            <p class="text-sm text-gray-600">{{ __('common.in_progress') }}</p>
                        </div>
                        <div class="text-center col-span-2">
                            <p class="text-2xl font-bold text-purple-600">{{ round($metrics['quran_progress_stats']['average_accuracy'] ?? 0, 1) }}%</p>
                            <p class="text-sm text-gray-600">{{ __('common.avg_accuracy') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities and Upcoming Events -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Recent Activities -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ __('common.recent_activities') }}
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-brandBlue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-user-plus text-brandBlue-600 text-sm"></i>
                                </div>
                                <span class="text-sm text-gray-600">{{ __('common.new_students') }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $metrics['recent_activities']['new_students'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-tasks text-green-600 text-sm"></i>
                                </div>
                                <span class="text-sm text-gray-600">{{ __('common.new_assignments') }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $metrics['recent_activities']['new_assignments'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-quran text-purple-600 text-sm"></i>
                                </div>
                                <span class="text-sm text-gray-600">{{ __('common.quran_progress_updates') }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $metrics['recent_activities']['quran_progress_updates'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        {{ __('common.upcoming_events') }}
                    </h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-calendar-alt text-yellow-600 text-sm"></i>
                                </div>
                                <span class="text-sm text-gray-600">{{ __('common.upcoming_assignments') }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $metrics['upcoming_events']['upcoming_assignments'] ?? 0 }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-3">
                                    <i class="fas fa-chalkboard-teacher text-blue-600 text-sm"></i>
                                </div>
                                <span class="text-sm text-gray-600">{{ __('common.upcoming_classes') }}</span>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">{{ $metrics['upcoming_events']['upcoming_classes'] ?? 0 }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Recent Announcements -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            @if(app()->getLocale() === 'ar')
                                الإعلانات الأخيرة
                            @elseif(app()->getLocale() === 'dv')
                                އެންމެ ފަހުގެ އެކްސް
                            @else
                                Recent Announcements
                            @endif
                        </h3>
                    </div>
                    <div class="p-6">
                        @if($stats['recent_announcements']->count() > 0)
                            <div class="space-y-4">
                                @foreach($stats['recent_announcements'] as $announcement)
                                    <div class="flex items-start space-x-4 p-4 bg-gray-50 rounded-lg">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 bg-brandBlue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-bullhorn text-brandBlue-600 text-sm"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-medium text-gray-900 mb-1">{{ $announcement->title }}</h4>
                                            <p class="text-sm text-gray-600 mb-2">{{ Str::limit($announcement->content, 100) }}</p>
                                            <p class="text-xs text-gray-500">{{ $announcement->created_at->diffForHumans() }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8">
                                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-bullhorn text-gray-400 text-xl"></i>
                                </div>
                                <p class="text-gray-500">
                                    @if(app()->getLocale() === 'ar')
                                        لا توجد إعلانات حديثة
                                    @elseif(app()->getLocale() === 'dv')
                                        އެންމެ ފަހުގެ އެކްސް ނެތް
                                    @else
                                        No recent announcements
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">
                            @if(app()->getLocale() === 'ar')
                                الإجراءات السريعة
                            @elseif(app()->getLocale() === 'dv')
                                ވަގުތު ކުރެވިދާނެ ކަމުތައް
                            @else
                                Quick Actions
                            @endif
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @can('create_students')
                            <a href="{{ route('students.create') }}" class="w-full flex items-center justify-center px-4 py-2 bg-brandBlue-600 text-white rounded-lg hover:bg-brandBlue-700 transition-colors">
                                <i class="fas fa-user-plus mr-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    إضافة طالب جديد
                                @elseif(app()->getLocale() === 'dv')
                                    ރަގަޅު އެހިގަނޑު
                                @else
                                    Add New Student
                                @endif
                            </a>
                            @endcan
                            
                            @can('create_teachers')
                            <a href="{{ route('teachers.create') }}" class="w-full flex items-center justify-center px-4 py-2 border border-brandBlue-600 text-brandBlue-600 rounded-lg hover:bg-brandBlue-50 transition-colors">
                                <i class="fas fa-chalkboard-teacher mr-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    إضافة معلم جديد
                                @elseif(app()->getLocale() === 'dv')
                                    އުސްތާދު އެހިގަނޑު
                                @else
                                    Add New Teacher
                                @endif
                            </a>
                            @endcan
                            
                            @can('create_announcements')
                            <a href="#" class="w-full flex items-center justify-center px-4 py-2 border border-green-600 text-green-600 rounded-lg hover:bg-green-50 transition-colors">
                                <i class="fas fa-bullhorn mr-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    إنشاء إعلان
                                @elseif(app()->getLocale() === 'dv')
                                    އެކްސް ހުށަހަޅާ
                                @else
                                    Create Announcement
                                @endif
                            </a>
                            @endcan
                            
                            <a href="{{ route('quran-progress.index') }}" class="w-full flex items-center justify-center px-4 py-2 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 transition-colors">
                                <i class="fas fa-quran mr-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    عرض تقدم القرآن
                                @elseif(app()->getLocale() === 'dv')
                                    ޤުރުއާން ތަފްސީލް ބެލުމުން
                                @else
                                    View Quran Progress
                                @endif
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Islamic Calendar Widget -->
        <div class="mt-8">
            <div class="bg-white rounded-lg shadow-md">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">
                        @if(app()->getLocale() === 'ar')
                            التقويم الهجري
                        @elseif(app()->getLocale() === 'dv')
                            ހިޖްރީ ކެލެންޑަރު
                        @else
                            Islamic Calendar
                        @endif
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Gregorian Date -->
                        <div class="text-center">
                            <div class="bg-gray-50 rounded-lg p-6">
                                <div class="w-16 h-16 bg-brandBlue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-calendar-alt text-brandBlue-600 text-xl"></i>
                                </div>
                                <h4 class="text-xl font-semibold text-gray-900 mb-2">
                                    {{ now()->format('l, F j, Y') }}
                                </h4>
                                <p class="text-sm text-gray-600">
                                    @if(app()->getLocale() === 'ar')
                                        التاريخ الميلادي
                                    @elseif(app()->getLocale() === 'dv')
                                        ގްރެގޯރިއަން ދުވަހު
                                    @else
                                        Gregorian Date
                                    @endif
                                </p>
                            </div>
                        </div>
                        
                        <!-- Hijri Date -->
                        <div class="text-center">
                            <div class="bg-gradient-to-br from-brandBlue-50 to-brandGray-50 rounded-lg p-6">
                                <div class="w-16 h-16 bg-brandBlue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-moon text-brandBlue-600 text-xl"></i>
                                </div>
                                <h4 class="text-xl font-semibold text-gray-900 mb-2 font-arabic">
                                    {{ $islamicDate['formatted_arabic'] }}
                                </h4>
                                <p class="text-sm text-gray-600">
                                    @if(app()->getLocale() === 'ar')
                                        التاريخ الهجري
                                    @elseif(app()->getLocale() === 'dv')
                                        ހިޖްރީ ދުވަހު
                                    @else
                                        Hijri Date
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
