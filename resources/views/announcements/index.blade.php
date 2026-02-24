@extends('layouts.app')

@section('title', 'Announcements')
@section('page-title', 'School Announcements')

@section('page-actions')
    @can('create_announcements')
        <a href="{{ route('announcements.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>
            @if(app()->getLocale() === 'ar')
                إضافة إعلان
            @elseif(app()->getLocale() === 'dv')
                އިލާނު ހިމްނުން
            @else
                Add Announcement
            @endif
        </a>
    @endcan
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        @if($announcements->count() > 0)
            @foreach($announcements as $announcement)
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                @if(app()->getLocale() === 'ar' && $announcement->title_arabic)
                                    {{ $announcement->title_arabic }}
                                @elseif(app()->getLocale() === 'dv' && $announcement->title_dhivehi)
                                    {{ $announcement->title_dhivehi }}
                                @else
                                    {{ $announcement->title }}
                                @endif
                            </h5>
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
                                    {!! $announcement->content_arabic !!}
                                </div>
                            @elseif(app()->getLocale() === 'dv' && $announcement->content_dhivehi)
                                <div class="dhivehi-text">
                                    {!! $announcement->content_dhivehi !!}
                                </div>
                            @else
                                {!! $announcement->content !!}
                            @endif
                        </div>
                        
                        @if($announcement->target_audience)
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i>
                                    @if(app()->getLocale() === 'ar')
                                        موجه إلى:
                                    @elseif(app()->getLocale() === 'dv')
                                        ގެންނަ:
                                    @else
                                        Target Audience:
                                    @endif
                                    {{ implode(', ', $announcement->target_audience) }}
                                </small>
                            </div>
                        @endif
                        
                        @if($announcement->expiry_date)
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    @if(app()->getLocale() === 'ar')
                                        ينتهي في:
                                    @elseif(app()->getLocale() === 'dv')
                                        ނިމުނީ:
                                    @else
                                        Expires:
                                    @endif
                                    {{ $announcement->expiry_date->format('M d, Y') }}
                                </small>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer">
                        <a href="{{ route('announcements.show', $announcement) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye me-1"></i>
                            @if(app()->getLocale() === 'ar')
                                عرض التفاصيل
                            @elseif(app()->getLocale() === 'dv')
                                ތަފްސީލް ބެލުމުން
                            @else
                                View Details
                            @endif
                        </a>
                    </div>
                </div>
            @endforeach
        @else
            <div class="text-center py-5">
                <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">
                    @if(app()->getLocale() === 'ar')
                        لا توجد إعلانات
                    @elseif(app()->getLocale() === 'dv')
                        އިލާނުތައް ނެތް
                    @else
                        No announcements found
                    @endif
                </h5>
                <p class="text-muted">
                    @if(app()->getLocale() === 'ar')
                        لا توجد إعلانات مدرسية في الوقت الحالي
                    @elseif(app()->getLocale() === 'dv')
                        މިހާރު ސްކޫލުގައި އިލާނުތައް ނެތް
                    @else
                        There are no school announcements at this time
                    @endif
                </p>
            </div>
        @endif
    </div>
</div>
@endsection
