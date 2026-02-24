@extends('layouts.app')

@section('title', $subject->name)
@section('page-title', $subject->name)

@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    @if(app()->getLocale() === 'ar' && $subject->name_arabic)
                        {{ $subject->name_arabic }}
                    @elseif(app()->getLocale() === 'dv' && $subject->name_dhivehi)
                        {{ $subject->name_dhivehi }}
                    @else
                        {{ $subject->name }}
                    @endif
                </h5>
            </div>
            <div class="card-body">
                <div class="subject-description">
                    @if(app()->getLocale() === 'ar' && $subject->description_arabic)
                        <div class="arabic-text">
                            {!! $subject->description_arabic !!}
                        </div>
                    @elseif(app()->getLocale() === 'dv' && $subject->description_dhivehi)
                        <div class="dhivehi-text">
                            {!! $subject->description_dhivehi !!}
                        </div>
                    @else
                        {!! $subject->description !!}
                    @endif
                </div>
                
                <div class="mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>
                                @if(app()->getLocale() === 'ar')
                                    نوع المادة:
                                @elseif(app()->getLocale() === 'dv')
                                    ބާވަތް:
                                @else
                                    Subject Type:
                                @endif
                            </strong> {{ ucfirst($subject->type) }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>
                                @if(app()->getLocale() === 'ar')
                                    الساعات المعتمدة:
                                @elseif(app()->getLocale() === 'dv')
                                    ގަރަޑިޓް:
                                @else
                                    Credits:
                                @endif
                            </strong> {{ $subject->credits }}</p>
                        </div>
                    </div>
                </div>
                
                @if($subject->is_quran_subject)
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-quran me-2"></i>
                        @if(app()->getLocale() === 'ar')
                            هذه مادة قرآنية
                        @elseif(app()->getLocale() === 'dv')
                            މި ޤުރުއާން ބާވަތެކެވެ
                        @else
                            This is a Quran subject
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    @if(app()->getLocale() === 'ar')
                        معلومات المادة
                    @elseif(app()->getLocale() === 'dv')
                        ބާވަތުގެ ތަފްސީލް
                    @else
                        Subject Information
                    @endif
                </h6>
            </div>
            <div class="card-body">
                <p><strong>
                    @if(app()->getLocale() === 'ar')
                        رمز المادة:
                    @elseif(app()->getLocale() === 'dv')
                        ކޯޑް:
                    @else
                        Subject Code:
                    @endif
                </strong> {{ $subject->code }}</p>
                
                <p><strong>
                    @if(app()->getLocale() === 'ar')
                        الحالة:
                    @elseif(app()->getLocale() === 'dv')
                        ހާލަތު:
                    @else
                        Status:
                    @endif
                </strong> 
                    <span class="badge bg-{{ $subject->is_active ? 'success' : 'secondary' }}">
                        {{ $subject->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </p>
                
                @if($subject->is_quran_subject)
                    <div class="mt-3">
                        <h6>
                            @if(app()->getLocale() === 'ar')
                                خصائص القرآن:
                            @elseif(app()->getLocale() === 'dv')
                                ޤުރުއާން ތަފްސީލް:
                            @else
                                Quran Features:
                            @endif
                        </h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    حفظ القرآن
                                @elseif(app()->getLocale() === 'dv')
                                    ޤުރުއާން ހިފްޒު
                                @else
                                    Quran Memorization
                                @endif
                            </li>
                            <li><i class="fas fa-check text-success me-2"></i>
                                @if(app()->getLocale() === 'ar')
                                    تلاوة القرآن
                                @elseif(app()->getLocale() === 'dv')
                                    ޤުރުއާން ތާރީތު
                                @else
                                    Quran Recitation
                                @endif
                            </li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-body text-center">
                <a href="{{ route('e-learning.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>
                    @if(app()->getLocale() === 'ar')
                        العودة
                    @elseif(app()->getLocale() === 'dv')
                        ފަހަތަށް
                    @else
                        Back to E-Learning
                    @endif
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
