@extends('layouts.app')

@section('content')
<div style="background:linear-gradient(135deg,#3D1219,#7C2D37);padding:1.25rem 1.5rem">
    <h2 style="font-size:1.1rem;font-weight:800;color:white;margin:0">System Settings</h2>
    <p style="font-size:.75rem;color:rgba(255,255,255,.65);margin:.2rem 0 0">Application configuration overview and utilities</p>
</div>

<div style="max-width:56rem;margin:0 auto;padding:1.5rem 1rem">

    @if(session('success'))
    <div style="background:#D1FAE5;border:1px solid #A7F3D0;color:#065F46;border-radius:.75rem;padding:.85rem 1rem;margin-bottom:1rem;font-size:.875rem;font-weight:600">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Integration Status --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1.25rem">
        <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.1rem;display:flex;align-items:center;gap:.75rem">
            <div style="font-size:1.6rem">📩</div>
            <div>
                <p style="font-size:.75rem;color:#6B7280;margin:0">Mail Driver</p>
                <p style="font-size:.9rem;font-weight:700;color:#111827;margin:.15rem 0 0">{{ ucfirst($settings['mail_mailer']) }}</p>
                <p style="font-size:.7rem;color:#9CA3AF;margin:.1rem 0 0">{{ $settings['mail_from'] }}</p>
            </div>
        </div>
        <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.1rem;display:flex;align-items:center;gap:.75rem">
            <div style="font-size:1.6rem">{{ $smsConfigured ? '✅' : '⚠️' }}</div>
            <div>
                <p style="font-size:.75rem;color:#6B7280;margin:0">SMS Gateway</p>
                <p style="font-size:.9rem;font-weight:700;color:{{ $smsConfigured ? '#065F46' : '#92400E' }};margin:.15rem 0 0">
                    {{ $smsConfigured ? 'Configured' : 'Not Configured' }}
                </p>
                <p style="font-size:.7rem;color:#9CA3AF;margin:.1rem 0 0">Check .env SMS_GATEWAY_URL</p>
            </div>
        </div>
        <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.1rem;display:flex;align-items:center;gap:.75rem">
            <div style="font-size:1.6rem">{{ $bmlConfigured ? '✅' : '⚠️' }}</div>
            <div>
                <p style="font-size:.75rem;color:#6B7280;margin:0">BML Payment</p>
                <p style="font-size:.9rem;font-weight:700;color:{{ $bmlConfigured ? '#065F46' : '#92400E' }};margin:.15rem 0 0">
                    {{ $bmlConfigured ? 'Configured' : 'Not Configured' }}
                </p>
                <p style="font-size:.7rem;color:#9CA3AF;margin:.1rem 0 0">Check .env BML_API_KEY</p>
            </div>
        </div>
        <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.1rem;display:flex;align-items:center;gap:.75rem">
            <div style="font-size:1.6rem">🗄️</div>
            <div>
                <p style="font-size:.75rem;color:#6B7280;margin:0">Cache / Session</p>
                <p style="font-size:.9rem;font-weight:700;color:#111827;margin:.15rem 0 0">{{ ucfirst($settings['cache_driver']) }} / {{ ucfirst($settings['session_driver']) }}</p>
                <p style="font-size:.7rem;color:#9CA3AF;margin:.1rem 0 0">Queue: {{ ucfirst($settings['queue_connection']) }}</p>
            </div>
        </div>
    </div>

    {{-- App Info --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:1.25rem">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #F3F4F6">
            <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Application Info</h3>
        </div>
        @foreach([
            ['App Name', $settings['app_name']],
            ['Environment', $settings['app_env']],
            ['App URL', $settings['app_url']],
            ['PHP Version', PHP_VERSION],
            ['Laravel Version', app()->version()],
        ] as [$label, $val])
        <div style="padding:.75rem 1.25rem;border-top:1px solid #F9FAFB;display:flex;justify-content:space-between;font-size:.85rem">
            <span style="color:#6B7280">{{ $label }}</span>
            <span style="font-weight:600;color:#111827">{{ $val }}</span>
        </div>
        @endforeach
    </div>

    {{-- Cache Management --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:1.25rem">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #F3F4F6">
            <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Cache Management</h3>
        </div>
        <div style="padding:1.1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem">
            <p style="font-size:.85rem;color:#6B7280;margin:0">
                Clear all caches (application, views, routes, config). Safe to run at any time.
            </p>
            <form method="POST" action="{{ route('admin.settings.clear-cache') }}">
                @csrf
                <button type="submit"
                        style="padding:.55rem 1.1rem;background:#7C2D37;color:white;border:none;border-radius:.5rem;font-size:.83rem;font-weight:600;cursor:pointer">
                    Clear All Caches
                </button>
            </form>
        </div>
    </div>

    {{-- Quick Links --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;overflow:hidden">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #F3F4F6">
            <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Quick Links</h3>
        </div>
        <div style="padding:1rem 1.25rem;display:flex;flex-wrap:wrap;gap:.65rem">
            @php
                $links = [
                    ['label' => 'Manage Users',       'route' => 'admin.users.index',       'icon' => '👥'],
                    ['label' => 'Enrollments',         'route' => 'admin.enrollments.index',  'icon' => '📋'],
                    ['label' => 'Manage Courses',      'route' => 'admin.courses.index',      'icon' => '📚'],
                    ['label' => 'Analytics',           'route' => 'analytics.index',          'icon' => '📊'],
                    ['label' => 'Reports',             'route' => 'analytics.reports',        'icon' => '📄'],
                    ['label' => 'Admin Pages (CMS)',   'route' => 'admin.pages.index',        'icon' => '🖥️'],
                ];
            @endphp
            @foreach($links as $link)
            @if(Route::has($link['route']))
            <a href="{{ route($link['route']) }}"
               style="display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .9rem;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:.5rem;font-size:.82rem;font-weight:600;color:#374151;text-decoration:none"
               onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='#F9FAFB'">
                <span>{{ $link['icon'] }}</span> {{ $link['label'] }}
            </a>
            @endif
            @endforeach
        </div>
    </div>

</div>
@endsection
