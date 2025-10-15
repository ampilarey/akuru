@extends('layouts.app')

@section('title', 'E-Learning')
@section('page-title', 'E-Learning Portal')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold text-primary">
                    @if(app()->getLocale() === 'ar')
                        بوابة التعلم الإلكتروني
                    @elseif(app()->getLocale() === 'dv')
                        އެލެކްޓްރޮނިކް ތައުލީމް ޕޯޓަލް
                    @else
                        E-Learning Portal
                    @endif
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Quran Lessons -->
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-quran fa-3x text-success mb-3"></i>
                                <h5 class="card-title">
                                    @if(app()->getLocale() === 'ar')
                                        دروس القرآن الكريم
                                    @elseif(app()->getLocale() === 'dv')
                                        ޤުރުއާން ދަރުސް
                                    @else
                                        Quran Lessons
                                    @endif
                                </h5>
                                <p class="card-text">
                                    @if(app()->getLocale() === 'ar')
                                        تعلم القرآن الكريم وحفظه وتلاوته
                                    @elseif(app()->getLocale() === 'dv')
                                        ޤުރުއާން ހަފްޒު އަދި ތިލާވަތް
                                    @else
                                        Learn Quran memorization and recitation
                                    @endif
                                </p>
                                <a href="{{ route('e-learning.quran') }}" class="btn btn-success">
                                    @if(app()->getLocale() === 'ar')
                                        ابدأ التعلم
                                    @elseif(app()->getLocale() === 'dv')
                                        ތައުލީމް ފަށާ
                                    @else
                                        Start Learning
                                    @endif
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Arabic Language -->
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-language fa-3x text-primary mb-3"></i>
                                <h5 class="card-title">
                                    @if(app()->getLocale() === 'ar')
                                        اللغة العربية
                                    @elseif(app()->getLocale() === 'dv')
                                        ޢަރަބި ބަހުން
                                    @else
                                        Arabic Language
                                    @endif
                                </h5>
                                <p class="card-text">
                                    @if(app()->getLocale() === 'ar')
                                        تعلم اللغة العربية وقواعدها
                                    @elseif(app()->getLocale() === 'dv')
                                        ޢަރަބި ބަހުން އަދި ގަސްތައް
                                    @else
                                        Learn Arabic language and grammar
                                    @endif
                                </p>
                                <a href="{{ route('e-learning.arabic') }}" class="btn btn-primary">
                                    @if(app()->getLocale() === 'ar')
                                        ابدأ التعلم
                                    @elseif(app()->getLocale() === 'dv')
                                        ތައުލީމް ފަށާ
                                    @else
                                        Start Learning
                                    @endif
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Islamic Studies -->
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-mosque fa-3x text-info mb-3"></i>
                                <h5 class="card-title">
                                    @if(app()->getLocale() === 'ar')
                                        الدراسات الإسلامية
                                    @elseif(app()->getLocale() === 'dv')
                                        އިސްލާމް ތައުލީމް
                                    @else
                                        Islamic Studies
                                    @endif
                                </h5>
                                <p class="card-text">
                                    @if(app()->getLocale() === 'ar')
                                        تعلم العلوم الإسلامية والفقه
                                    @elseif(app()->getLocale() === 'dv')
                                        އިސްލާމް ތައުލީމް އަދި ފިގްހު
                                    @else
                                        Learn Islamic sciences and jurisprudence
                                    @endif
                                </p>
                                <a href="{{ route('e-learning.islamic-studies') }}" class="btn btn-info">
                                    @if(app()->getLocale() === 'ar')
                                        ابدأ التعلم
                                    @elseif(app()->getLocale() === 'dv')
                                        ތައުލީމް ފަށާ
                                    @else
                                        Start Learning
                                    @endif
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Available Subjects -->
                <div class="row mt-4">
                    <div class="col-12">
                        <h5 class="mb-3">
                            @if(app()->getLocale() === 'ar')
                                المواد المتاحة
                            @elseif(app()->getLocale() === 'dv')
                                ހުށަހަޅާ މާދާ
                            @else
                                Available Subjects
                            @endif
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>
                                            @if(app()->getLocale() === 'ar')
                                                اسم المادة
                                            @elseif(app()->getLocale() === 'dv')
                                                މާދާގެ ނަން
                                            @else
                                                Subject Name
                                            @endif
                                        </th>
                                        <th>
                                            @if(app()->getLocale() === 'ar')
                                                النوع
                                            @elseif(app()->getLocale() === 'dv')
                                                ގަސް
                                            @else
                                                Type
                                            @endif
                                        </th>
                                        <th>
                                            @if(app()->getLocale() === 'ar')
                                                الوصف
                                            @elseif(app()->getLocale() === 'dv')
                                                ތަފްސީލު
                                            @else
                                                Description
                                            @endif
                                        </th>
                                        <th>
                                            @if(app()->getLocale() === 'ar')
                                                الإجراءات
                                            @elseif(app()->getLocale() === 'dv')
                                                ކުރެވިދާނެ ކަމުތައް
                                            @else
                                                Actions
                                            @endif
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subjects as $subject)
                                    <tr>
                                        <td>
                                            <div>
                                                <strong>{{ $subject->name }}</strong>
                                                @if($subject->name_arabic)
                                                    <br><span class="arabic-text">{{ $subject->name_arabic }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $subject->is_quran_subject ? 'success' : ($subject->type === 'Arabic' ? 'primary' : 'info') }}">
                                                {{ $subject->type }}
                                            </span>
                                        </td>
                                        <td>{{ Str::limit($subject->description, 50) }}</td>
                                        <td>
                                            <a href="{{ route('e-learning.show', $subject) }}" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i>
                                                @if(app()->getLocale() === 'ar')
                                                    عرض
                                                @elseif(app()->getLocale() === 'dv')
                                                    ބެލުމުން
                                                @else
                                                    View
                                                @endif
                                            </a>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
