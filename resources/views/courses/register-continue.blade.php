@extends('public.layouts.public')

@section('title', __('register.details_title'))

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">{{ __('register.details_title') }}</h1>

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded" role="alert">{{ $errors->first() }}</div>
            @endif
            {{-- What the resume link's form did (sent, or refused) is flashed, and the layout shows a flash: printing it here too showed it twice (slice RG1b). --}}

            {{-- C16 slice N5: a single-use link to come back to this form later (OWNER_ACTIONS 14). --}}
            <form method="POST" action="{{ route('courses.register.resume-link') }}" class="mb-6 flex flex-wrap items-center gap-3 rounded border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-700" data-testid="resume-link-form">
                @csrf
                <span class="grow">{{ __('register.resume_intro') }}</span>
                <button type="submit" class="btn-secondary" data-testid="resume-link-send">{{ __('register.resume_send') }}</button>
            </form>

            <div x-data="enrollFlow()" x-init="init()">
                <div class="mb-6">
                    <p class="text-gray-600 mb-4">{{ __('register.how') }}</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <button type="button" @click="flow = 'parent'" :aria-pressed="flow === 'parent'" data-testid="flow-parent"
                            :style="flow === 'parent'
                                ? 'border:2px solid #7C2D37;background:#FDF2F2;color:#7C2D37'
                                : 'border:1px solid #D1D5DB;background:white;color:#374151'"
                            style="padding:1rem;border-radius:.5rem;text-align:start;transition:all .15s;cursor:pointer;display:block;width:100%">
                            <span style="font-weight:600;display:flex;align-items:center;gap:.4rem">
                                <span x-show="flow === 'parent'" style="color:#7C2D37" aria-hidden="true">✓</span>
                                {{ __('register.how_parent') }}
                            </span>
                            <span style="font-size:.82rem;color:#6B7280;margin-top:.2rem;display:block">{{ __('register.how_parent_hint') }}</span>
                        </button>
                        <button type="button" @click="flow = 'adult'" :aria-pressed="flow === 'adult'" data-testid="flow-adult"
                            :style="flow === 'adult'
                                ? 'border:2px solid #7C2D37;background:#FDF2F2;color:#7C2D37'
                                : 'border:1px solid #D1D5DB;background:white;color:#374151'"
                            style="padding:1rem;border-radius:.5rem;text-align:start;transition:all .15s;cursor:pointer;display:block;width:100%">
                            <span style="font-weight:600;display:flex;align-items:center;gap:.4rem">
                                <span x-show="flow === 'adult'" style="color:#7C2D37" aria-hidden="true">✓</span>
                                {{ __('register.how_adult') }}
                            </span>
                            <span style="font-size:.82rem;color:#6B7280;margin-top:.2rem;display:block">{{ __('register.how_adult_hint') }}</span>
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('courses.register.enroll') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="flow" x-model="flow">
                    {{-- Always submit the correct id_type regardless of radio/disabled state --}}
                    <input type="hidden" name="id_type" :value="flow === 'parent' ? childIdType : adultIdType">
                    <input type="hidden" name="term_id" value="{{ $termId }}">

                    @foreach($courses as $c)
                        <input type="hidden" name="course_ids[]" value="{{ $c->id }}">
                    @endforeach

                    <div x-show="flow === 'parent'" x-cloak>
                        <fieldset class="mb-4">
                            <legend class="block text-sm font-medium text-gray-700 mb-2">{{ __('register.child_mode') }}</legend>
                            <div class="flex flex-wrap gap-4 mb-4">
                                <label><input type="radio" name="student_mode" value="new" x-model="studentMode"> {{ __('register.child_new') }}</label>
                                @if($children->isNotEmpty())
                                    <label><input type="radio" name="student_mode" value="existing" x-model="studentMode"> {{ __('register.child_existing') }}</label>
                                @endif
                            </div>
                        </fieldset>
                        <div x-show="studentMode === 'existing'" x-cloak class="mb-4">
                            <label for="ch_student_id" class="block text-sm font-medium text-gray-700 mb-2">{{ __('register.child_choose') }}</label>
                            <select id="ch_student_id" name="student_id" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'existing'" required>
                                <option value="">{{ __('register.child_choose') }}</option>
                                @foreach($children as $s)
                                    <option value="{{ $s->id }}">{{ trans_choice('register.child_age', $s->age(), ['name' => $s->full_name, 'age' => $s->age()]) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="studentMode === 'new'" x-cloak class="space-y-4">
                            <div class="grid sm:grid-cols-3 gap-4">
                                <div><label for="ch_first_name" class="block text-sm font-medium mb-1">{{ __('register.first_name') }} <span class="text-red-500">*</span></label><input id="ch_first_name" type="text" name="first_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('first_name') }}" required></div>
                                <div><label for="ch_middle_name" class="block text-sm font-medium mb-1">{{ __('account.name_middle') }}</label><input id="ch_middle_name" type="text" name="middle_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('middle_name') }}"></div>
                                <div><label for="ch_last_name" class="block text-sm font-medium mb-1">{{ __('register.last_name') }} <span class="text-red-500">*</span></label><input id="ch_last_name" type="text" name="last_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('last_name') }}" required></div>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div><label for="ch_dob" class="block text-sm font-medium mb-1">{{ __('register.dob') }} <span class="text-red-500">*</span></label><input id="ch_dob" type="date" name="dob" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('dob') }}" max="{{ date('Y-m-d') }}" required></div>
                                <div>
                                    <label for="ch_gender" class="block text-sm font-medium mb-1">{{ __('register.gender') }}</label>
                                    <select id="ch_gender" name="gender" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'">
                                        <option value="">{{ __('register.choose') }}</option>
                                        <option value="male" @selected(old('gender') === 'male')>{{ __('register.male') }}</option>
                                        <option value="female" @selected(old('gender') === 'female')>{{ __('register.female') }}</option>
                                    </select>
                                </div>
                            </div>
                            {{-- ID document --}}
                            <fieldset>
                                <legend class="block text-sm font-medium mb-2">{{ __('register.id_document') }} <span class="text-red-500">*</span></legend>
                                <div class="flex flex-wrap gap-4 mb-3">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="national_id" x-model="childIdType"> {{ __('register.id_card') }}
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" value="passport" x-model="childIdType"> {{ __('register.passport') }}
                                    </label>
                                </div>
                                <div x-show="childIdType === 'national_id'">
                                    <input type="text" name="national_id" placeholder="{{ __('register.id_card_example') }}" aria-label="{{ __('register.id_card_number') }}" dir="ltr"
                                           class="w-full rounded-md border-gray-300 uppercase" maxlength="10"
                                           :disabled="flow !== 'parent' || studentMode !== 'new' || childIdType !== 'national_id'"
                                           value="{{ old('national_id') }}"
                                           oninput="this.value=this.value.toUpperCase()">
                                    <p class="text-xs text-gray-500 mt-1">{{ __('register.id_card_format') }}</p>
                                </div>
                                <div x-show="childIdType === 'passport'" x-cloak>
                                    <input type="text" name="passport" placeholder="{{ __('register.passport_number') }}" aria-label="{{ __('register.passport_number') }}" dir="ltr"
                                           class="w-full rounded-md border-gray-300 uppercase" maxlength="20"
                                           :disabled="flow !== 'parent' || studentMode !== 'new' || childIdType !== 'passport'"
                                           value="{{ old('passport') }}"
                                           oninput="this.value=this.value.toUpperCase()">
                                </div>
                            </fieldset>
                            <div><label for="ch_relationship" class="block text-sm font-medium mb-1">{{ __('register.relationship') }} <span class="text-red-500">*</span></label>
                                <select id="ch_relationship" name="relationship" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'">
                                    <option value="father" @selected(old('relationship') === 'father')>{{ __('register.relationship_father') }}</option>
                                    <option value="mother" @selected(old('relationship') === 'mother')>{{ __('register.relationship_mother') }}</option>
                                    <option value="guardian" @selected(old('relationship') === 'guardian')>{{ __('register.relationship_guardian') }}</option>
                                    <option value="other" @selected(old('relationship') === 'other')>{{ __('register.relationship_other') }}</option>
                                </select>
                            </div>

                            {{-- Child login password --}}
                            <div style="background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:.625rem;padding:1rem;margin-top:.5rem">
                                <p style="font-size:.8rem;font-weight:700;color:#166534;margin:0 0 .5rem"><span aria-hidden="true">🔐</span> {{ __('register.child_password_title') }}</p>
                                <p style="font-size:.75rem;color:#15803D;margin:0 0 .75rem">{{ __('register.child_password_body') }}</p>
                                <div class="grid sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="ch_password" class="block text-sm font-medium mb-1">{{ __('register.password') }} <span class="text-red-500">*</span></label>
                                        <input id="ch_password" type="password" name="child_password"
                                               class="w-full rounded-md border-gray-300"
                                               :disabled="flow !== 'parent' || studentMode !== 'new'"
                                               minlength="8" required autocomplete="new-password"
                                               placeholder="{{ __('register.password_hint') }}">
                                        @error('child_password')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="ch_password_confirmation" class="block text-sm font-medium mb-1">{{ __('register.confirm_password') }} <span class="text-red-500">*</span></label>
                                        <input id="ch_password_confirmation" type="password" name="child_password_confirmation"
                                               class="w-full rounded-md border-gray-300"
                                               :disabled="flow !== 'parent' || studentMode !== 'new'"
                                               minlength="8" required autocomplete="new-password"
                                               placeholder="{{ __('register.confirm_hint') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="flow === 'adult'" class="space-y-4">
                        @if($prefill['first_name'] || $prefill['national_id'])
                            <p class="text-sm text-green-700 bg-green-50 rounded p-2">
                                <span aria-hidden="true">✓</span> {{ __('register.prefilled') }}
                            </p>
                        @endif
                        <div class="grid sm:grid-cols-3 gap-4">
                            <div><label for="ad_first_name" class="block text-sm font-medium mb-1">{{ __('register.first_name') }} <span class="text-red-500">*</span></label><input id="ad_first_name" type="text" name="first_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('first_name', $prefill['first_name']) }}" required autocomplete="given-name"></div>
                            <div><label for="ad_middle_name" class="block text-sm font-medium mb-1">{{ __('account.name_middle') }}</label><input id="ad_middle_name" type="text" name="middle_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('middle_name', $prefill['middle_name']) }}" autocomplete="additional-name"></div>
                            <div><label for="ad_last_name" class="block text-sm font-medium mb-1">{{ __('register.last_name') }} <span class="text-red-500">*</span></label><input id="ad_last_name" type="text" name="last_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('last_name', $prefill['last_name']) }}" required autocomplete="family-name"></div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label for="ad_dob" class="block text-sm font-medium mb-1">{{ __('register.dob') }} <span class="text-red-500">*</span></label><input id="ad_dob" type="date" name="dob" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('dob', $prefill['dob']) }}" max="{{ date('Y-m-d') }}" required autocomplete="bday"></div>
                            <div>
                                <label for="ad_gender" class="block text-sm font-medium mb-1">{{ __('register.gender') }}</label>
                                <select id="ad_gender" name="gender" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'">
                                    <option value="">{{ __('register.choose') }}</option>
                                    <option value="male" @selected(old('gender', $prefill['gender']) === 'male')>{{ __('register.male') }}</option>
                                    <option value="female" @selected(old('gender', $prefill['gender']) === 'female')>{{ __('register.female') }}</option>
                                </select>
                            </div>
                        </div>
                        {{-- ID document --}}
                        <fieldset>
                            <legend class="block text-sm font-medium mb-2">{{ __('register.id_document') }} <span class="text-red-500">*</span></legend>
                            <div class="flex flex-wrap gap-4 mb-3">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="national_id" x-model="adultIdType"> {{ __('register.id_card') }}
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" value="passport" x-model="adultIdType"> {{ __('register.passport') }}
                                </label>
                            </div>
                            <div x-show="adultIdType === 'national_id'">
                                <input type="text" name="national_id" placeholder="{{ __('register.id_card_example') }}" aria-label="{{ __('register.id_card_number') }}" dir="ltr"
                                       class="w-full rounded-md border-gray-300 uppercase" maxlength="10"
                                       :disabled="flow !== 'adult' || adultIdType !== 'national_id'"
                                       value="{{ old('national_id', $prefill['national_id']) }}"
                                       oninput="this.value=this.value.toUpperCase()">
                                <p class="text-xs text-gray-500 mt-1">{{ __('register.id_card_format') }}</p>
                            </div>
                            <div x-show="adultIdType === 'passport'" x-cloak>
                                <input type="text" name="passport" placeholder="{{ __('register.passport_number') }}" aria-label="{{ __('register.passport_number') }}" dir="ltr"
                                       class="w-full rounded-md border-gray-300 uppercase" maxlength="20"
                                       :disabled="flow !== 'adult' || adultIdType !== 'passport'"
                                       value="{{ old('passport', $prefill['passport']) }}"
                                       oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </fieldset>
                    </div>

                    {{-- COMMERCE_PARITY_PLAN P3: the learner's ID card — the front only since C17 R3 (STATUS §5of) — a child's own card. The office checks it after enrolment. --}}
                    <fieldset class="mt-2 pt-4 border-t border-gray-100" data-testid="learner-id-card">
                        <legend class="block text-sm font-medium text-gray-700 mb-1">
                            <span x-show="flow === 'adult'">{{ __('account.id_learner_title') }}</span>
                            <span x-show="flow === 'parent'" x-cloak>{{ __('account.id_learner_child_title') }}</span>
                        </legend>
                        <p class="text-xs text-gray-500 mb-2">{{ __('account.id_learner_hint') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block text-sm">{{ __('account.id_front') }}
                                <input type="file" name="id_front" accept="image/jpeg,image/png,image/webp,application/pdf" class="mt-1 block w-full text-sm" data-id-scan-source data-testid="learner-id-front">
                            </label>
                        </div>
                        {{-- C17 slice R2: choosing the card's photo reads it here and fills the empty details above. --}}
                        @include('courses.partials.id-scan', ['mode' => 'uploads'])
                        @error('id_front')<p class="text-sm text-red-600 mt-1" role="alert" data-testid="learner-id-error">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Email (optional, used for confirmation receipt) --}}
                    <div class="mt-2 pt-4 border-t border-gray-100">
                        <label for="enroll_email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('register.email_receipt') }}</label>
                        @php $existingEmail = $user->contacts()->where('type','email')->first()?->value; @endphp
                        <input id="enroll_email" type="email" name="email" dir="ltr" autocomplete="email"
                               value="{{ old('email', $existingEmail) }}"
                               placeholder="you@example.com"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        <p class="text-xs text-gray-500 mt-1">{{ __($existingEmail ? 'register.email_saved' : 'register.email_confirmation') }}</p>
                    </div>

                    <div class="mt-6 p-3 bg-gray-50 rounded">
                        <p class="font-medium mb-2">{{ __('register.courses_chosen') }}</p>
                        <ul class="list-disc list-inside">
                            @foreach($courses as $c)
                                <li>{{ $c->title }} @if($c->hasRegistrationFee())<span class="text-amber-600">({{ __('register.money', ['amount' => number_format($c->getRegistrationFeeAmount(), 2)]) }})</span>@endif</li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- One code per registration (STATUS §5oc): the terms are accepted here. Someone who has just proved their phone or password goes straight on; a session signed in a while ago is asked one code on the next screen. --}}
                    <div class="mt-4 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-gray-700" data-testid="enroll-terms">
                        <ul class="mb-2 list-disc ps-5 text-xs text-gray-600">
                            <li>{{ __('register.term_seats') }}</li>
                            <li>{{ __('register.term_fees') }}</li>
                            <li>{{ __('register.term_attendance') }}</li>
                        </ul>
                        <label class="flex items-start gap-2">
                            <input type="checkbox" name="terms_accepted" value="1" class="mt-0.5 rounded border-gray-300" @checked(old('terms_accepted')) required data-testid="enroll-terms-accept">
                            {{-- The sentence is the book's, with the two links where its :terms and :refunds stand, in whichever order the language puts them (slice RG1b). --}}
                            <span>@foreach(preg_split('/(:terms|:refunds)/', __('register.terms_agree'), -1, PREG_SPLIT_DELIM_CAPTURE) as $part)@if($part === ':terms')<a href="{{ route('public.page.show', 'terms') }}" target="_blank" class="font-semibold text-brandMaroon-700 underline">{{ __('register.terms_link') }}</a>@elseif($part === ':refunds')<a href="{{ route('public.page.show', 'refund-policy') }}" target="_blank" class="font-semibold text-brandMaroon-700 underline">{{ __('register.refunds_link') }}</a>@else{{ $part }}@endif{{ '' }}@endforeach</span>
                        </label>
                        @error('terms_accepted')<p class="mt-1 text-xs text-red-600" role="alert">{{ $message }}</p>@enderror
                    </div>

                    @if($proven)
                        <button type="submit" class="btn-primary w-full py-3 mt-4" data-testid="enroll-submit">{{ __('register.confirm_enrollment') }} <span class="rtl-flip" aria-hidden="true">→</span></button>
                        @if($courses->contains(fn ($c) => $c->hasRegistrationFee()))
                            <p class="mt-2 text-xs text-gray-500 text-center">{{ __('register.pay_next') }}</p>
                        @endif
                    @else
                        <button type="submit" class="btn-primary w-full py-3 mt-4" data-testid="enroll-submit">{{ __('register.continue') }} <span class="rtl-flip" aria-hidden="true">→</span></button>
                        <p class="mt-2 text-xs text-gray-500 text-center">{{ __('register.one_code_next') }}</p>
                    @endif
                </form>
            </div>
        </div>
    </div>
</section>

<script>
function enrollFlow() {
    @php
        $restoredFlow        = old('flow', $defaultFlow);
        $restoredStudentMode = old('student_mode', $children->isNotEmpty() ? 'existing' : 'new');
        $restoredIdType      = old('id_type', 'national_id');
        $adultIdTypeDefault  = $prefill['id_type'] ?: 'national_id';
    @endphp
    {{-- Old input goes through @js: printed between quotes, a value with a quote or a backslash in it broke the script (slice RG1b). --}}
    return {
        flow: @js($restoredFlow),
        studentMode: @js($restoredStudentMode),
        adultIdType: @js($restoredFlow === 'adult' ? $restoredIdType : $adultIdTypeDefault),
        childIdType: @js($restoredFlow === 'parent' ? $restoredIdType : 'national_id'),
        init() {}
    }
}
</script>
@endsection
