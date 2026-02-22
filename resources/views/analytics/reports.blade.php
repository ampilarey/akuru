@extends('layouts.app')

@section('content')
<div style="background:linear-gradient(135deg,#3D1219,#7C2D37);padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center">
    <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:white;margin:0">Reports</h2>
        <p style="font-size:.75rem;color:rgba(255,255,255,.65);margin:.2rem 0 0">Generate and view system reports</p>
    </div>
    <a href="{{ route('analytics.index') }}"
       style="font-size:.78rem;color:rgba(255,255,255,.75);text-decoration:none"
       onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,.75)'">
        ← Analytics Dashboard
    </a>
</div>

<div style="max-width:60rem;margin:0 auto;padding:1.5rem 1rem">

    {{-- Generate Report Form --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.25rem;margin-bottom:1.25rem">
        <h3 style="font-size:.95rem;font-weight:700;color:#111827;margin:0 0 1rem">Generate New Report</h3>
        <form method="POST" action="{{ route('analytics.generate') }}">
            @csrf
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.75rem;margin-bottom:.75rem">
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.35rem">Report Name</label>
                    <input type="text" name="name" required
                           style="width:100%;border:1px solid #D1D5DB;border-radius:.5rem;padding:.5rem .75rem;font-size:.85rem"
                           placeholder="e.g. Monthly Enrollment">
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.35rem">Type</label>
                    <select name="type" style="width:100%;border:1px solid #D1D5DB;border-radius:.5rem;padding:.5rem .75rem;font-size:.85rem">
                        @foreach($availableTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.35rem">Category</label>
                    <select name="category" style="width:100%;border:1px solid #D1D5DB;border-radius:.5rem;padding:.5rem .75rem;font-size:.85rem">
                        @foreach($availableCategories as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.35rem">Format</label>
                    <select name="format" style="width:100%;border:1px solid #D1D5DB;border-radius:.5rem;padding:.5rem .75rem;font-size:.85rem">
                        @foreach($availableFormats as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit"
                    style="padding:.55rem 1.25rem;background:#7C2D37;color:white;border:none;border-radius:.5rem;font-size:.85rem;font-weight:600;cursor:pointer">
                Generate Report
            </button>
        </form>
    </div>

    {{-- Reports List --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;overflow:hidden">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center">
            <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">All Reports</h3>
            <span style="font-size:.78rem;color:#6B7280">{{ $reports->total() }} total</span>
        </div>

        @forelse($reports as $report)
        @php
            $statusStyles = [
                'completed' => 'background:#D1FAE5;color:#065F46',
                'pending'   => 'background:#FEF3C7;color:#92400E',
                'failed'    => 'background:#FEE2E2;color:#991B1B',
                'running'   => 'background:#DBEAFE;color:#1E40AF',
            ];
            $ss = $statusStyles[$report->status ?? 'pending'] ?? 'background:#F3F4F6;color:#374151';
        @endphp
        <div style="padding:.9rem 1.25rem;border-top:1px solid #F9FAFB;display:flex;justify-content:space-between;align-items:center;gap:1rem">
            <div>
                <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0">{{ $report->name }}</p>
                <p style="font-size:.75rem;color:#9CA3AF;margin:.2rem 0 0">
                    {{ ucfirst($report->type ?? '') }} · {{ ucfirst($report->category ?? '') }}
                    &nbsp;·&nbsp; {{ $report->created_at->format('d M Y H:i') }}
                </p>
            </div>
            <div style="display:flex;align-items:center;gap:.6rem;flex-shrink:0">
                <span style="font-size:.7rem;font-weight:700;padding:.2rem .55rem;border-radius:9999px;{{ $ss }}">
                    {{ ucfirst($report->status ?? 'Pending') }}
                </span>
                @if(($report->status ?? '') === 'completed' && $report->file_path)
                <a href="{{ route('analytics.reports.download', $report) }}"
                   style="font-size:.75rem;padding:.3rem .65rem;background:#7C2D37;color:white;border-radius:.4rem;text-decoration:none;font-weight:600">
                    Download
                </a>
                @endif
            </div>
        </div>
        @empty
        <div style="padding:3rem;text-align:center;color:#9CA3AF">
            <p style="font-size:2rem;margin:0 0 .5rem">📊</p>
            <p style="margin:0">No reports yet. Generate your first report above.</p>
        </div>
        @endforelse

        @if($reports->hasPages())
        <div style="padding:.75rem 1.25rem;border-top:1px solid #F3F4F6">
            {{ $reports->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
