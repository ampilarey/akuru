@extends('layouts.app')

@section('title', 'Arabic Lessons')
@section('page-title', 'Arabic Lessons')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    @if(app()->getLocale() === 'ar')
                        دروس اللغة العربية
                    @elseif(app()->getLocale() === 'dv')
                        ޢަރަބި ބަހުގެ ދަރުސް
                    @else
                        Arabic Lessons
                    @endif
                </h5>
            </div>
            <div class="card-body">
                @if($arabicSubjects->count() > 0)
                    <div class="row">
                        @foreach($arabicSubjects as $subject)
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="text-center mb-3">
                                            <i class="fas fa-language fa-3x text-success"></i>
                                        </div>
                                        <h6 class="card-title">
                                            @if(app()->getLocale() === 'ar' && $subject->name_arabic)
                                                {{ $subject->name_arabic }}
                                            @elseif(app()->getLocale() === 'dv' && $subject->name_dhivehi)
                                                {{ $subject->name_dhivehi }}
                                            @else
                                                {{ $subject->name }}
                                            @endif
                                        </h6>
                                        <p class="card-text text-muted">
                                            @if(app()->getLocale() === 'ar' && $subject->description_arabic)
                                                {{ Str::limit($subject->description_arabic, 100) }}
                                            @elseif(app()->getLocale() === 'dv' && $subject->description_dhivehi)
                                                {{ Str::limit($subject->description_dhivehi, 100) }}
                                            @else
                                                {{ Str::limit($subject->description, 100) }}
                                            @endif
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-success">{{ $subject->code }}</span>
                                            <a href="{{ route('e-learning.show', $subject) }}" class="btn btn-sm btn-outline-success">
                                                @if(app()->getLocale() === 'ar')
                                                    عرض
                                                @elseif(app()->getLocale() === 'dv')
                                                    ބެލުމުން
                                                @else
                                                    View
                                                @endif
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fas fa-language fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">
                            @if(app()->getLocale() === 'ar')
                                لا توجد دروس عربية متاحة
                            @elseif(app()->getLocale() === 'dv')
                                ޢަރަބި ދަރުސް ނެތް
                            @else
                                No Arabic lessons available
                            @endif
                        </h5>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
