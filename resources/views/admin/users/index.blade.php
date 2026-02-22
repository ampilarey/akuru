@extends('layouts.app')

@section('content')

{{-- Header --}}
<div style="background:linear-gradient(135deg,#3D1219,#7C2D37);padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center">
    <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:white;margin:0">User Management</h2>
        <p style="font-size:.75rem;color:rgba(255,255,255,.65);margin:.2rem 0 0">
            {{ $users->total() }} users total
        </p>
    </div>
    <a href="{{ route('dashboard') }}"
       style="font-size:.78rem;color:rgba(255,255,255,.75);text-decoration:none;display:flex;align-items:center;gap:.375rem"
       onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,.75)'">
        ← Dashboard
    </a>
</div>

<div style="max-width:72rem;margin:0 auto;padding:1.5rem 1rem">

    {{-- Alerts --}}
    @if(session('success'))
    <div style="margin-bottom:1rem;padding:.875rem 1rem;background:#ECFDF5;border:1px solid #6EE7B7;border-radius:.625rem;color:#065F46;font-size:.85rem">
        ✓ {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="margin-bottom:1rem;padding:.875rem 1rem;background:#FEF2F2;border:1px solid #FECACA;border-radius:.625rem;color:#991B1B;font-size:.85rem">
        ✗ {{ session('error') }}
    </div>
    @endif

    {{-- Search & filter --}}
    <form method="GET" action="{{ route('admin.users.index') }}"
          style="display:flex;gap:.75rem;margin-bottom:1.25rem;flex-wrap:wrap">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search name, ID card, mobile, email…"
               style="flex:1;min-width:200px;padding:.625rem .875rem;border:1px solid #E5E7EB;border-radius:.5rem;font-size:.85rem;outline:none"
               onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
        <select name="role"
                style="padding:.625rem .875rem;border:1px solid #E5E7EB;border-radius:.5rem;font-size:.85rem;outline:none;background:white">
            <option value="">All roles</option>
            <option value="super_admin"  {{ request('role')==='super_admin'  ? 'selected' : '' }}>Super Admin</option>
            <option value="admin"        {{ request('role')==='admin'        ? 'selected' : '' }}>Admin</option>
            <option value="student"      {{ request('role')==='student'      ? 'selected' : '' }}>Student</option>
            <option value="teacher"      {{ request('role')==='teacher'      ? 'selected' : '' }}>Teacher</option>
            <option value="parent"       {{ request('role')==='parent'       ? 'selected' : '' }}>Parent</option>
        </select>
        <button type="submit"
                style="padding:.625rem 1.25rem;background:#7C2D37;color:white;border:none;border-radius:.5rem;font-size:.85rem;font-weight:600;cursor:pointer">
            Search
        </button>
        @if(request('search') || request('role'))
        <a href="{{ route('admin.users.index') }}"
           style="padding:.625rem 1rem;border:1px solid #E5E7EB;border-radius:.5rem;font-size:.85rem;color:#6B7280;text-decoration:none;display:flex;align-items:center">
            Clear
        </a>
        @endif
    </form>

    {{-- Table --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;overflow:hidden">
        <table style="width:100%;border-collapse:collapse;font-size:.82rem">
            <thead>
                <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
                    <th style="padding:.75rem 1rem;text-align:left;color:#6B7280;font-weight:600">#</th>
                    <th style="padding:.75rem 1rem;text-align:left;color:#6B7280;font-weight:600">Name</th>
                    <th style="padding:.75rem 1rem;text-align:left;color:#6B7280;font-weight:600">Contact</th>
                    <th style="padding:.75rem 1rem;text-align:left;color:#6B7280;font-weight:600">ID Card</th>
                    <th style="padding:.75rem 1rem;text-align:left;color:#6B7280;font-weight:600">Role</th>
                    <th style="padding:.75rem 1rem;text-align:left;color:#6B7280;font-weight:600">Registered</th>
                    <th style="padding:.75rem 1rem;text-align:left;color:#6B7280;font-weight:600">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $u)
                @php
                    $role      = $u->roles->first()?->name;
                    $mobile    = $u->contacts->firstWhere('type', 'mobile')?->value;
                    $email     = $u->contacts->firstWhere('type', 'email')?->value;
                    $isSelf    = $u->id === auth()->id();
                    $isSuperAdmin = $u->hasRole('super_admin');
                    $roleColors = [
                        'super_admin' => 'background:#FEE2E2;color:#991B1B',
                        'admin'       => 'background:#FEF3C7;color:#92400E',
                        'teacher'     => 'background:#EDE9FE;color:#5B21B6',
                        'student'     => 'background:#ECFDF5;color:#065F46',
                        'parent'      => 'background:#EFF6FF;color:#1D4ED8',
                    ];
                    $roleStyle = $roleColors[$role] ?? 'background:#F3F4F6;color:#374151';
                @endphp
                <tr style="border-top:1px solid #F3F4F6{{ $isSelf ? ';background:#FFFBF0' : '' }}">
                    <td style="padding:.75rem 1rem;color:#9CA3AF">{{ $u->id }}</td>
                    <td style="padding:.75rem 1rem">
                        <div style="font-weight:600;color:#111827">{{ $u->name }}</div>
                        @if($isSelf)
                        <div style="font-size:.68rem;color:#D97706;font-weight:600">YOU</div>
                        @endif
                    </td>
                    <td style="padding:.75rem 1rem;color:#374151">
                        @if($mobile)
                        <div>📱 {{ $mobile }}</div>
                        @endif
                        @if($email)
                        <div style="color:#6B7280;font-size:.78rem">✉ {{ $email }}</div>
                        @endif
                        @if(!$mobile && !$email)
                        <span style="color:#D1D5DB">—</span>
                        @endif
                    </td>
                    <td style="padding:.75rem 1rem;color:#374151">
                        {{ $u->national_id ?? ($u->passport ?? '—') }}
                    </td>
                    <td style="padding:.75rem 1rem">
                        @if($role)
                        <span style="font-size:.7rem;font-weight:700;padding:.2rem .55rem;border-radius:9999px;{{ $roleStyle }}">
                            {{ ucwords(str_replace('_', ' ', $role)) }}
                        </span>
                        @else
                        <span style="color:#D1D5DB">—</span>
                        @endif
                    </td>
                    <td style="padding:.75rem 1rem;color:#9CA3AF;white-space:nowrap">
                        {{ $u->created_at->format('d M Y') }}
                    </td>
                    <td style="padding:.75rem 1rem">
                        @if($isSelf || $isSuperAdmin)
                        <span style="font-size:.75rem;color:#D1D5DB">Protected</span>
                        @else
                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                              onsubmit="return confirm('Delete {{ addslashes($u->name) }}? This will remove all their enrollments and data permanently.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    style="padding:.35rem .75rem;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;border-radius:.375rem;font-size:.75rem;font-weight:600;cursor:pointer"
                                    onmouseover="this.style.background='#FEE2E2'" onmouseout="this.style.background='#FEF2F2'">
                                Delete
                            </button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="padding:3rem;text-align:center;color:#9CA3AF">
                        No users found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($users->hasPages())
    <div style="margin-top:1rem">
        {{ $users->links() }}
    </div>
    @endif

</div>
@endsection
