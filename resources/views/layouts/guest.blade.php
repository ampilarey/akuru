<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar','dv']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Akuru Institute') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Figtree', sans-serif; }
        .auth-input {
            width:100%;padding:.625rem .875rem;border:1.5px solid #E5E7EB;border-radius:.5rem;
            font-size:.9rem;transition:border-color .15s,box-shadow .15s;outline:none;
        }
        .auth-input:focus { border-color:#7C2D37;box-shadow:0 0 0 3px rgba(124,45,55,.12); }
        .auth-label { display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem; }
        .auth-btn {
            width:100%;padding:.75rem 1.5rem;border-radius:.625rem;font-weight:700;font-size:.95rem;
            cursor:pointer;border:none;transition:opacity .2s,transform .15s;
            background:linear-gradient(135deg,#7C2D37,#5A1F28);color:white;
        }
        .auth-btn:hover { opacity:.9;transform:translateY(-1px); }
        .auth-error { color:#DC2626;font-size:.78rem;margin-top:.25rem; }
    </style>
</head>
<body style="min-height:100vh;display:flex;background:#F8F5F2">

{{-- ── Left brand panel (hidden on mobile) ──────────────────────────── --}}
<div class="hidden lg:flex lg:w-5/12 xl:w-2/5" style="background:linear-gradient(160deg,#3D1219 0%,#7C2D37 55%,#5A1F28 100%);flex-direction:column;justify-content:space-between;padding:3rem 2.5rem;position:relative;overflow:hidden">

    {{-- subtle pattern --}}
    <div style="position:absolute;inset:0;opacity:.06;background-image:url(\"data:image/svg+xml,%3Csvg width='52' height='26' viewBox='0 0 52 26' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23C9A227' fill-opacity='1'%3E%3Cpath d='M10 10c0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6h2c0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4 3.314 0 6 2.686 6 6 0 2.21 1.79 4 4 4v2c-3.314 0-6-2.686-6-6 0-2.21-1.79-4-4-4-3.314 0-6-2.686-6-6zm25.464-1.95l8.486 8.486-1.414 1.414-8.486-8.486 1.414-1.414z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")"></div>

    {{-- logo + name --}}
    <div style="position:relative">
        <div style="display:flex;align-items:center;gap:.875rem;margin-bottom:3rem">
            <x-akuru-logo size="h-14" class="brightness-0 invert" />
        </div>
        <h1 style="font-size:2rem;font-weight:800;color:white;line-height:1.2;margin-bottom:.75rem">
            Welcome to<br>Akuru Institute
        </h1>
        <p style="color:rgba(255,255,255,.65);font-size:.95rem;line-height:1.65;max-width:20rem">
            Quality Islamic education for all ages — Quran, Arabic Language, and Islamic Studies in the Maldives.
        </p>

        <div style="margin-top:2.5rem;display:flex;flex-direction:column;gap:1rem">
            @foreach([
                ['M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944','Qualified instructors'],
                ['M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z','Flexible schedules'],
                ['M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7','All ages welcome'],
            ] as [$icon, $label])
            <div style="display:flex;align-items:center;gap:.75rem">
                <div style="width:2rem;height:2rem;border-radius:.5rem;background:rgba(201,162,39,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <svg width="14" height="14" fill="none" stroke="#C9A227" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/></svg>
                </div>
                <span style="color:rgba(255,255,255,.75);font-size:.875rem">{{ $label }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- bottom quote --}}
    <div style="position:relative;border-top:1px solid rgba(255,255,255,.1);padding-top:1.5rem">
        <p style="color:rgba(255,255,255,.5);font-size:.78rem;font-style:italic">
            "Seek knowledge from the cradle to the grave."
        </p>
    </div>
</div>

{{-- ── Right form panel ────────────────────────────────────────────── --}}
<div style="flex:1;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:2rem 1rem;min-height:100vh">

    {{-- Mobile logo (only shown on small screens) --}}
    <div class="lg:hidden" style="text-align:center;margin-bottom:2rem">
        <x-akuru-logo size="h-14" />
        <p style="margin-top:.5rem;color:#6B7280;font-size:.85rem">Akuru Institute</p>
    </div>

    <div style="width:100%;max-width:26rem">
        {{-- gold top accent --}}
        <div style="height:3px;border-radius:9999px;background:linear-gradient(90deg,#A8861F,#C9A227,#E8BC3C);margin-bottom:2rem"></div>

        {{ $slot }}

        <p style="text-align:center;margin-top:2rem;font-size:.75rem;color:#9CA3AF">
            © {{ date('Y') }} Akuru Institute. All rights reserved.
        </p>
    </div>
</div>

</body>
</html>
