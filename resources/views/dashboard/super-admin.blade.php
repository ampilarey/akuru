<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Super Admin Dashboard') }}
            </h2>
            <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                {{ __('SUPER ADMIN') }}
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- System Health Status -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">🔧 System Health</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 border rounded-lg {{ $metrics['system_health']['database'] === 'healthy' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                        <div class="text-sm text-gray-600">Database</div>
                        <div class="text-2xl font-bold {{ $metrics['system_health']['database'] === 'healthy' ? 'text-green-600' : 'text-red-600' }}">
                            {{ ucfirst($metrics['system_health']['database']) }}
                        </div>
                    </div>
                    <div class="p-4 border rounded-lg {{ $metrics['system_health']['storage'] === 'healthy' ? 'bg-green-50 border-green-200' : ($metrics['system_health']['storage'] === 'warning' ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200') }}">
                        <div class="text-sm text-gray-600">Storage</div>
                        <div class="text-2xl font-bold">
                            {{ ucfirst($metrics['system_health']['storage']) }}
                        </div>
                    </div>
                    <div class="p-4 border rounded-lg {{ $metrics['system_health']['sms_gateway'] === 'online' ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' }}">
                        <div class="text-sm text-gray-600">SMS Gateway</div>
                        <div class="text-2xl font-bold {{ $metrics['system_health']['sms_gateway'] === 'online' ? 'text-green-600' : 'text-red-600' }}">
                            {{ ucfirst($metrics['system_health']['sms_gateway']) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Statistics -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">📊 System Statistics</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-brandBlue-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Total Users</div>
                        <div class="text-3xl font-bold text-brandBlue-600">{{ $stats['total_users'] }}</div>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Students</div>
                        <div class="text-3xl font-bold text-green-600">{{ $stats['total_students'] }}</div>
                    </div>
                    <div class="p-4 bg-purple-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Teachers</div>
                        <div class="text-3xl font-bold text-purple-600">{{ $stats['total_teachers'] }}</div>
                    </div>
                    <div class="p-4 bg-orange-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Database Size</div>
                        <div class="text-3xl font-bold text-orange-600">{{ $stats['database_size'] }} MB</div>
                    </div>
                </div>
            </div>

            <!-- SMS Usage -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">📱 SMS Gateway Usage</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">SMS Today</div>
                        <div class="text-3xl font-bold text-blue-600">{{ $stats['sms_usage_today'] }}</div>
                    </div>
                    <div class="p-4 bg-indigo-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Gateway Status</div>
                        <div class="text-xl font-bold {{ $metrics['system_health']['sms_gateway'] === 'online' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $metrics['system_health']['sms_gateway'] === 'online' ? '✅ Online' : '❌ Offline' }}
                        </div>
                    </div>
                    <div class="p-4 bg-cyan-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">API Integration</div>
                        <div class="text-xl font-bold text-cyan-600">✅ Active</div>
                    </div>
                </div>
            </div>

            <!-- Regular Admin Dashboard Content -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">📚 Academic Overview</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Quran Students</div>
                        <div class="text-3xl font-bold text-brandGray-800">{{ $stats['active_quran_students'] }}</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Assignments</div>
                        <div class="text-3xl font-bold text-brandGray-800">{{ $stats['total_assignments'] }}</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Announcements</div>
                        <div class="text-3xl font-bold text-brandGray-800">{{ $stats['total_announcements'] }}</div>
                    </div>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="text-sm text-brandGray-600">Attendance Rate</div>
                        <div class="text-3xl font-bold text-brandGray-800">{{ $metrics['attendance_rate'] }}%</div>
                    </div>
                </div>
            </div>

            <!-- Islamic Calendar -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">🌙 Islamic Calendar</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600">Hijri Date</p>
                        <p class="text-xl font-bold">{{ $islamicDate['day'] }} {{ $islamicDate['month_name'] }} {{ $islamicDate['year'] }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600">Current Prayer</p>
                        <p class="text-xl font-bold">{{ $currentPrayer['prayer'] ? ucfirst($currentPrayer['prayer']).' - '.($currentPrayer['time'] ? $currentPrayer['time']->format('H:i') : '') : 'No prayer time now' }}</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions for Super Admin -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold mb-4">⚡ Quick Actions (Super Admin Only)</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="#" class="p-4 border-2 border-red-200 rounded-lg hover:bg-red-50 text-center">
                        <div class="text-2xl mb-2">⚙️</div>
                        <div class="text-sm font-semibold">System Settings</div>
                    </a>
                    <a href="#" class="p-4 border-2 border-red-200 rounded-lg hover:bg-red-50 text-center">
                        <div class="text-2xl mb-2">🔑</div>
                        <div class="text-sm font-semibold">API Keys</div>
                    </a>
                    <a href="#" class="p-4 border-2 border-red-200 rounded-lg hover:bg-red-50 text-center">
                        <div class="text-2xl mb-2">👥</div>
                        <div class="text-sm font-semibold">User Roles</div>
                    </a>
                    <a href="#" class="p-4 border-2 border-red-200 rounded-lg hover:bg-red-50 text-center">
                        <div class="text-2xl mb-2">📊</div>
                        <div class="text-sm font-semibold">System Logs</div>
                    </a>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

