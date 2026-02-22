@extends('layouts.app')

@section('content')
{{-- Brand header bar --}}
<div style="background:linear-gradient(135deg,#3D1219,#7C2D37);padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center">
    <div>
        <h2 style="font-size:1.15rem;font-weight:800;color:white;margin:0">Super Admin Dashboard</h2>
        <p style="font-size:.75rem;color:rgba(255,255,255,.65);margin:.2rem 0 0">
            {{ now()->format('l, d F Y') }} &nbsp;·&nbsp;
            {{ $islamicDate['day'] }} {{ $islamicDate['month_name'] }} {{ $islamicDate['year'] }} AH
        </p>
    </div>
    <span style="padding:.35rem .85rem;background:rgba(255,255,255,.15);color:white;font-size:.7rem;font-weight:700;border-radius:9999px;letter-spacing:.06em;border:1px solid rgba(255,255,255,.25)">
        SUPER ADMIN
    </span>
</div>

<div style="padding:1.5rem 1rem 3rem;max-width:80rem;margin:0 auto">

    {{-- ── Top KPI cards ─────────────────────────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem;margin-bottom:1.5rem">

        @php
        $kpis = [
            ['label'=>'Total Users',        'value'=>$stats['total_users'],          'icon'=>'👤', 'bg'=>'#EEF2FF', 'color'=>'#4338CA'],
            ['label'=>'Enrollments',         'value'=>$stats['total_enrollments'],    'icon'=>'📋', 'bg'=>'#ECFDF5', 'color'=>'#059669'],
            ['label'=>'Pending Payment',     'value'=>$stats['pending_enrollments'],  'icon'=>'⏳', 'bg'=>'#FFFBEB', 'color'=>'#D97706'],
            ['label'=>'Open Courses',        'value'=>$stats['open_courses'],         'icon'=>'📚', 'bg'=>'#FEF3C7', 'color'=>'#B45309'],
            ['label'=>'Revenue (MVR)',        'value'=>number_format($stats['revenue_total'],0), 'icon'=>'💰', 'bg'=>'#FDF2F8', 'color'=>'#9D174D'],
            ['label'=>'New Today',           'value'=>$stats['new_users_today'],      'icon'=>'🆕', 'bg'=>'#F0F9FF', 'color'=>'#0369A1'],
        ];
        @endphp

        @foreach($kpis as $k)
        <div style="background:{{ $k['bg'] }};border-radius:.875rem;padding:1.25rem;border:1px solid rgba(0,0,0,.05)">
            <div style="font-size:1.5rem;margin-bottom:.5rem">{{ $k['icon'] }}</div>
            <div style="font-size:1.6rem;font-weight:800;color:{{ $k['color'] }};line-height:1">{{ $k['value'] }}</div>
            <div style="font-size:.75rem;color:#6B7280;margin-top:.25rem">{{ $k['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── Main grid: enrollments + health ──────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.25rem;margin-bottom:1.25rem">

        {{-- Recent Enrollments --}}
        <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;overflow:hidden">
            <div style="padding:1rem 1.25rem;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center">
                <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Recent Enrollments</h3>
                <span style="font-size:.72rem;color:#6B7280">Last 10</span>
            </div>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:.8rem">
                    <thead>
                        <tr style="background:#F9FAFB">
                            <th style="padding:.625rem 1rem;text-align:left;color:#6B7280;font-weight:600;white-space:nowrap">Student</th>
                            <th style="padding:.625rem 1rem;text-align:left;color:#6B7280;font-weight:600;white-space:nowrap">Course</th>
                            <th style="padding:.625rem 1rem;text-align:left;color:#6B7280;font-weight:600;white-space:nowrap">Status</th>
                            <th style="padding:.625rem 1rem;text-align:left;color:#6B7280;font-weight:600;white-space:nowrap">Fee</th>
                            <th style="padding:.625rem 1rem;text-align:left;color:#6B7280;font-weight:600;white-space:nowrap">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentEnrollments as $e)
                        @php
                            $statusStyles = [
                                'active'          => 'background:#D1FAE5;color:#065F46',
                                'pending'         => 'background:#FEF3C7;color:#92400E',
                                'pending_payment' => 'background:#FEF3C7;color:#92400E',
                                'cancelled'       => 'background:#FEE2E2;color:#991B1B',
                                'completed'       => 'background:#EDE9FE;color:#5B21B6',
                            ];
                            $ss = $statusStyles[$e->status] ?? 'background:#F3F4F6;color:#374151';
                        @endphp
                        <tr style="border-top:1px solid #F3F4F6">
                            <td style="padding:.625rem 1rem;color:#111827;font-weight:500">
                                {{ $e->student?->full_name ?? '—' }}
                            </td>
                            <td style="padding:.625rem 1rem;color:#374151;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                {{ $e->course?->title ?? '—' }}
                            </td>
                            <td style="padding:.625rem 1rem">
                                <span style="font-size:.68rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px;{{ $ss }}">
                                    {{ ucfirst(str_replace('_',' ',$e->status)) }}
                                </span>
                            </td>
                            <td style="padding:.625rem 1rem;color:#374151;white-space:nowrap">
                                {{ $e->payment?->amount ? 'MVR '.number_format($e->payment->amount,0) : 'Free' }}
                            </td>
                            <td style="padding:.625rem 1rem;color:#9CA3AF;white-space:nowrap">
                                {{ $e->created_at->format('d M') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" style="padding:2rem;text-align:center;color:#9CA3AF">No enrollments yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Right column: health + prayer --}}
        <div style="display:flex;flex-direction:column;gap:1rem">

            {{-- System Health --}}
            <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.25rem">
                <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0 0 .875rem">System Health</h3>
                @php
                $healthItems = [
                    ['label'=>'Database',    'val'=>$metrics['system_health']['database'],    'ok'=>'healthy'],
                    ['label'=>'Storage',     'val'=>$metrics['system_health']['storage'],     'ok'=>'healthy'],
                    ['label'=>'SMS Gateway', 'val'=>$metrics['system_health']['sms_gateway'], 'ok'=>'online'],
                ];
                @endphp
                @foreach($healthItems as $h)
                @php $ok = $h['val'] === $h['ok']; @endphp
                <div style="display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;{{ !$loop->last ? 'border-bottom:1px solid #F3F4F6' : '' }}">
                    <span style="font-size:.8rem;color:#374151">{{ $h['label'] }}</span>
                    <span style="font-size:.72rem;font-weight:700;padding:.2rem .6rem;border-radius:9999px;{{ $ok ? 'background:#D1FAE5;color:#065F46' : 'background:#FEE2E2;color:#991B1B' }}">
                        {{ $ok ? '✓ '.ucfirst($h['val']) : '✗ '.ucfirst($h['val']) }}
                    </span>
                </div>
                @endforeach
                <div style="margin-top:.875rem;padding:.625rem;background:#F9FAFB;border-radius:.5rem;text-align:center">
                    <span style="font-size:.72rem;color:#6B7280">Database size</span>
                    <div style="font-size:1.1rem;font-weight:700;color:#374151">{{ $stats['database_size'] }} MB</div>
                </div>
            </div>

            {{-- Prayer Times --}}
            <div style="background:linear-gradient(135deg,#3D1219,#7C2D37);border-radius:.875rem;padding:1.25rem;color:white">
                <h3 style="font-size:.9rem;font-weight:700;margin:0 0 .25rem;color:white">Prayer Times</h3>
                <p style="font-size:.72rem;color:rgba(255,255,255,.6);margin:0 0 .875rem">
                    {{ $islamicDate['day'] }} {{ $islamicDate['month_name'] }} {{ $islamicDate['year'] }}
                </p>
                @if($currentPrayer && $currentPrayer['prayer'])
                <div style="background:rgba(255,255,255,.12);border-radius:.5rem;padding:.625rem .75rem;margin-bottom:.875rem;display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:.78rem;color:rgba(255,255,255,.8)">Current Prayer</span>
                    <span style="font-size:.85rem;font-weight:700;color:#F9C74F">
                        {{ ucfirst($currentPrayer['prayer']) }}
                        @if($currentPrayer['time']) · {{ $currentPrayer['time']->format('H:i') }} @endif
                    </span>
                </div>
                @endif
                @if(!empty($prayerTimes))
                @php
                $prayerLabels = ['fajr'=>'Fajr','sunrise'=>'Sunrise','dhuhr'=>'Dhuhr','asr'=>'Asr','maghrib'=>'Maghrib','isha'=>'Isha'];
                @endphp
                @foreach($prayerLabels as $key => $label)
                @if(isset($prayerTimes[$key]))
                <div style="display:flex;justify-content:space-between;padding:.3rem 0;{{ !$loop->last ? 'border-bottom:1px solid rgba(255,255,255,.08)' : '' }}">
                    <span style="font-size:.75rem;color:rgba(255,255,255,.65)">{{ $label }}</span>
                    <span style="font-size:.75rem;font-weight:600;color:rgba(255,255,255,.9)">
                        {{ is_object($prayerTimes[$key]) ? $prayerTimes[$key]->format('H:i') : $prayerTimes[$key] }}
                    </span>
                </div>
                @endif
                @endforeach
                @endif
            </div>

        </div>
    </div>

    {{-- ── Bottom row: stats + quick actions ────────────────────────────── --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

        {{-- Enrollment Stats --}}
        <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0 0 1rem">Enrollment Overview</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                @php
                $enrollStats = [
                    ['label'=>'Total Courses',     'value'=>$stats['total_courses'],        'color'=>'#4338CA'],
                    ['label'=>'Active',            'value'=>$stats['active_enrollments'],   'color'=>'#059669'],
                    ['label'=>'Pending',           'value'=>$stats['pending_enrollments'],  'color'=>'#D97706'],
                    ['label'=>'Today\'s Revenue',  'value'=>'MVR '.number_format($stats['revenue_today'],0), 'color'=>'#7C2D37'],
                    ['label'=>'Enrolled Today',    'value'=>$stats['enrollments_today'],    'color'=>'#0369A1'],
                    ['label'=>'This Month (users)','value'=>$stats['new_users_this_month'], 'color'=>'#9D174D'],
                ];
                @endphp
                @foreach($enrollStats as $s)
                <div style="padding:.875rem;background:#F9FAFB;border-radius:.625rem;border:1px solid #F3F4F6">
                    <div style="font-size:1.4rem;font-weight:800;color:{{ $s['color'] }}">{{ $s['value'] }}</div>
                    <div style="font-size:.72rem;color:#6B7280;margin-top:.15rem">{{ $s['label'] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Quick Actions --}}
        <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.25rem">
            <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0 0 1rem">Quick Actions</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
                @php
                $actions = [
                    ['label'=>'View Courses',    'icon'=>'📚', 'href'=>(Route::has('admin.courses.index')     ? route('admin.courses.index')     : '#'), 'color'=>'#EEF2FF','border'=>'#C7D2FE','text'=>'#4338CA'],
                    ['label'=>'All Enrollments', 'icon'=>'📋', 'href'=>(Route::has('admin.enrollments.index') ? route('admin.enrollments.index') : '#'), 'color'=>'#ECFDF5','border'=>'#A7F3D0','text'=>'#065F46'],
                    ['label'=>'Manage Users',    'icon'=>'👥', 'href'=>(Route::has('admin.users.index')       ? route('admin.users.index')       : '#'), 'color'=>'#FFF7ED','border'=>'#FED7AA','text'=>'#92400E'],
                    ['label'=>'Site Settings',   'icon'=>'⚙️', 'href'=>(Route::has('admin.settings.index')   ? route('admin.settings.index')   : '#'), 'color'=>'#F0F9FF','border'=>'#BAE6FD','text'=>'#0369A1'],
                    ['label'=>'View Website',    'icon'=>'🌐', 'href'=>route('public.home'),                                                            'color'=>'#F5F3FF','border'=>'#DDD6FE','text'=>'#5B21B6'],
                    ['label'=>'Logout',          'icon'=>'🔒', 'href'=>route('logout'),                                                                 'color'=>'#FEF2F2','border'=>'#FECACA','text'=>'#991B1B'],
                ];
                @endphp
                @foreach($actions as $a)
                @if($a['label'] === 'Logout')
                <form method="POST" action="{{ route('logout') }}" style="margin:0">
                    @csrf
                    <button type="submit"
                       style="width:100%;display:flex;align-items:center;gap:.625rem;padding:.75rem;background:{{ $a['color'] }};border:1px solid {{ $a['border'] }};border-radius:.625rem;cursor:pointer;transition:opacity .15s"
                       onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                        <span style="font-size:1.2rem">{{ $a['icon'] }}</span>
                        <span style="font-size:.78rem;font-weight:600;color:{{ $a['text'] }}">{{ $a['label'] }}</span>
                    </button>
                </form>
                @else
                <a href="{{ $a['href'] }}"
                   style="display:flex;align-items:center;gap:.625rem;padding:.75rem;background:{{ $a['color'] }};border:1px solid {{ $a['border'] }};border-radius:.625rem;text-decoration:none;transition:opacity .15s"
                   onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
                    <span style="font-size:1.2rem">{{ $a['icon'] }}</span>
                    <span style="font-size:.78rem;font-weight:600;color:{{ $a['text'] }}">{{ $a['label'] }}</span>
                </a>
                @endif
                @endforeach
            </div>
        </div>

    </div>

</div>
@endsection
