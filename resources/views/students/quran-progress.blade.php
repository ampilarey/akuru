@extends('layouts.app')

@section('content')
<div style="background:linear-gradient(135deg,#3D1219,#7C2D37);padding:1.25rem 1.5rem;display:flex;justify-content:space-between;align-items:center">
    <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:white;margin:0">Quran Progress</h2>
        <p style="font-size:.75rem;color:rgba(255,255,255,.65);margin:.2rem 0 0">{{ $student->full_name }}</p>
    </div>
    <a href="{{ route('students.show', $student) }}"
       style="font-size:.78rem;color:rgba(255,255,255,.75);text-decoration:none"
       onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,.75)'">
        ← Back to student
    </a>
</div>

<div style="max-width:56rem;margin:0 auto;padding:1.5rem 1rem">

    {{-- Student summary card --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;padding:1.25rem;margin-bottom:1.25rem;display:flex;gap:1rem;align-items:center">
        <div style="width:3rem;height:3rem;border-radius:50%;background:linear-gradient(135deg,#3D1219,#7C2D37);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <span style="color:white;font-weight:700;font-size:1.1rem">{{ strtoupper(substr($student->first_name,0,1)) }}</span>
        </div>
        <div>
            <h3 style="font-size:1rem;font-weight:700;color:#111827;margin:0">{{ $student->full_name }}</h3>
            <p style="font-size:.78rem;color:#6B7280;margin:.2rem 0 0">
                {{ $progress->count() }} progress record(s) &nbsp;·&nbsp;
                {{ $progress->where('status','completed')->count() }} completed
            </p>
        </div>
        <div style="margin-left:auto">
            <a href="{{ route('quran-progress.create') }}"
               style="padding:.5rem 1rem;background:#7C2D37;color:white;border-radius:.5rem;font-size:.8rem;font-weight:600;text-decoration:none">
                + Add Progress
            </a>
        </div>
    </div>

    {{-- Progress records --}}
    <div style="background:white;border-radius:.875rem;border:1px solid #E5E7EB;overflow:hidden">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid #F3F4F6">
            <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Progress Records</h3>
        </div>

        @forelse($progress as $p)
        @php
            $statusStyles = [
                'completed'   => 'background:#D1FAE5;color:#065F46',
                'in_progress' => 'background:#FEF3C7;color:#92400E',
                'not_started' => 'background:#F3F4F6;color:#6B7280',
            ];
            $ss = $statusStyles[$p->status] ?? 'background:#F3F4F6;color:#374151';
        @endphp
        <div style="padding:1rem 1.25rem;border-top:1px solid #F9FAFB;display:flex;justify-content:space-between;align-items:center;gap:1rem">
            <div>
                <p style="font-weight:600;color:#111827;font-size:.875rem;margin:0">
                    {{ $p->surah_name ?? 'Surah '.$p->surah_number }}
                </p>
                @if($p->verse_from && $p->verse_to)
                <p style="font-size:.75rem;color:#6B7280;margin:.2rem 0 0">
                    Verses {{ $p->verse_from }} – {{ $p->verse_to }}
                </p>
                @endif
                @if($p->teacher)
                <p style="font-size:.72rem;color:#9CA3AF;margin:.15rem 0 0">
                    Teacher: {{ $p->teacher->user->name ?? 'N/A' }}
                </p>
                @endif
            </div>
            <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0">
                @if($p->accuracy_percentage)
                <span style="font-size:.8rem;font-weight:700;color:#374151">{{ $p->accuracy_percentage }}%</span>
                @endif
                <span style="font-size:.7rem;font-weight:700;padding:.2rem .55rem;border-radius:9999px;{{ $ss }}">
                    {{ ucfirst(str_replace('_',' ',$p->status)) }}
                </span>
                <span style="font-size:.72rem;color:#9CA3AF">{{ $p->updated_at->format('d M Y') }}</span>
            </div>
        </div>
        @empty
        <div style="padding:3rem;text-align:center;color:#9CA3AF">
            <p style="font-size:2rem;margin:0 0 .5rem">📖</p>
            <p style="margin:0">No progress records yet.</p>
            <a href="{{ route('quran-progress.create') }}"
               style="display:inline-block;margin-top:.75rem;padding:.5rem 1rem;background:#7C2D37;color:white;border-radius:.5rem;font-size:.8rem;font-weight:600;text-decoration:none">
                Add First Record
            </a>
        </div>
        @endforelse
    </div>

</div>
@endsection
