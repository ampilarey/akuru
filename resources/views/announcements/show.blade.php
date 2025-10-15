@extends('layouts.app')

@section('title', 'Announcement Details')
@section('page-title', 'Announcement Details')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="card-title mb-0">
                        @if(app()->getLocale() === 'ar' && $announcement->title_arabic)
                            {{ $announcement->title_arabic }}
                        @elseif(app()->getLocale() === 'dv' && $announcement->title_dhivehi)
                            {{ $announcement->title_dhivehi }}
                        @else
                            {{ $announcement->title }}
                        @endif
                    </h4>
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i>
                        {{ $announcement->createdBy->name }}
                        <i class="fas fa-calendar ms-2 me-1"></i>
                        {{ $announcement->publish_date->format('M d, Y') }}
                    </small>
                </div>
                <div>
                    <span class="badge bg-{{ $announcement->priority === 'urgent' ? 'danger' : ($announcement->priority === 'high' ? 'warning' : 'info') }}">
                        {{ ucfirst($announcement->priority) }}
                    </span>
                    <span class="badge bg-secondary ms-1">
                        {{ ucfirst($announcement->type) }}
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="announcement-content">
                    @if(app()->getLocale() === 'ar' && $announcement->content_arabic)
                        <div class="arabic-text">
                            {!! nl2br(e($announcement->content_arabic)) !!}
                        </div>
                    @elseif(app()->getLocale() === 'dv' && $announcement->content_dhivehi)
                        <div class="dhivehi-text">
                            {!! nl2br(e($announcement->content_dhivehi)) !!}
                        </div>
                    @else
                        {!! nl2br(e($announcement->content)) !!}
                    @endif
                </div>
                
                @if($announcement->target_audience)
                    <div class="mt-4">
                        <h6>
                            @if(app()->getLocale() === 'ar')
                                موجه إلى:
                            @elseif(app()->getLocale() === 'dv')
                                ގެންނަ:
                            @else
                                Target Audience:
                            @endif
                        </h6>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($announcement->target_audience as $audience)
                                <span class="badge bg-primary">{{ $audience }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
                
                @if($announcement->target_classes)
                    <div class="mt-3">
                        <h6>
                            @if(app()->getLocale() === 'ar')
                                الصفوف المستهدفة:
                            @elseif(app()->getLocale() === 'dv')
                                ގަރަޑުތައް:
                            @else
                                Target Classes:
                            @endif
                        </h6>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($announcement->target_classes as $class)
                                <span class="badge bg-success">{{ $class }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
                
                <div class="mt-4">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>
                                @if(app()->getLocale() === 'ar')
                                    تاريخ النشر:
                                @elseif(app()->getLocale() === 'dv')
                                    ފެންވަތު:
                                @else
                                    Published:
                                @endif
                            </strong> {{ $announcement->publish_date->format('M d, Y H:i') }}</p>
                        </div>
                        @if($announcement->expiry_date)
                            <div class="col-md-6">
                                <p><strong>
                                    @if(app()->getLocale() === 'ar')
                                        تاريخ الانتهاء:
                                    @elseif(app()->getLocale() === 'dv')
                                        ނިމުނީ:
                                    @else
                                        Expires:
                                    @endif
                                </strong> {{ $announcement->expiry_date->format('M d, Y H:i') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <a href="{{ route('announcements.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>
                    @if(app()->getLocale() === 'ar')
                        العودة
                    @elseif(app()->getLocale() === 'dv')
                        ފަހަތަށް
                    @else
                        Back
                    @endif
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
