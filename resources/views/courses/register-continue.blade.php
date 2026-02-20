@extends('public.layouts.public')

@section('title', 'Complete registration')

@section('content')
<section class="py-12">
    <div class="container mx-auto px-4 max-w-2xl">
        <div class="card p-6">
            <h1 class="text-2xl font-bold text-brandMaroon-900 mb-6">Complete your enrollment</h1>

            @if($errors->any())
                <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">{{ $errors->first() }}</div>
            @endif

            <div x-data="enrollFlow()" x-init="init()">
                <div class="mb-6">
                    <p class="text-gray-600 mb-4">How would you like to enroll?</p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <button type="button" @click="flow = 'parent'"
                            :class="flow === 'parent' ? 'ring-2 ring-brandMaroon-500' : ''"
                            class="p-4 border rounded-lg text-left hover:bg-gray-50 transition">
                            <span class="font-medium block">I am a parent/guardian enrolling my child</span>
                            <span class="text-sm text-gray-500">Add or select your child's details</span>
                        </button>
                        <button type="button" @click="flow = 'adult'"
                            :class="flow === 'adult' ? 'ring-2 ring-brandMaroon-500' : ''"
                            class="p-4 border rounded-lg text-left hover:bg-gray-50 transition">
                            <span class="font-medium block">I am 18+ enrolling myself</span>
                            <span class="text-sm text-gray-500">Enter your own details</span>
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('courses.register.enroll') }}">
                    @csrf
                    <input type="hidden" name="flow" x-model="flow">
                    <input type="hidden" name="term_id" value="{{ $termId }}">

                    @foreach($courses as $c)
                        <input type="hidden" name="course_ids[]" value="{{ $c->id }}">
                    @endforeach

                    <div x-show="flow === 'parent'" x-cloak>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Student mode</label>
                            <div class="flex gap-4 mb-4">
                                <label><input type="radio" name="student_mode" value="new" x-model="studentMode"> Add new child</label>
                                @if($user->guardianStudents->isNotEmpty())
                                    <label><input type="radio" name="student_mode" value="existing" x-model="studentMode"> Select existing</label>
                                @endif
                            </div>
                        </div>
                        <div x-show="studentMode === 'existing'" x-cloak class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select child</label>
                            <select name="student_id" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'existing'" required>
                                <option value="">Select a child</option>
                                @foreach($user->guardianStudents as $s)
                                    <option value="{{ $s->id }}">{{ $s->full_name }} ({{ $s->age() }} years)</option>
                                @endforeach
                            </select>
                        </div>
                        <div x-show="studentMode === 'new'" x-cloak class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div><label class="block text-sm font-medium mb-1">First name <span class="text-red-500">*</span></label><input type="text" name="first_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('first_name') }}" required></div>
                                <div><label class="block text-sm font-medium mb-1">Last name <span class="text-red-500">*</span></label><input type="text" name="last_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('last_name') }}" required></div>
                            </div>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div><label class="block text-sm font-medium mb-1">Date of birth <span class="text-red-500">*</span></label><input type="date" name="dob" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('dob') }}" required></div>
                                <div>
                                    <label class="block text-sm font-medium mb-1">Gender</label>
                                    <select name="gender" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'">
                                        <option value="">Prefer not to say</option>
                                        <option value="male" @selected(old('gender') === 'male')>Male</option>
                                        <option value="female" @selected(old('gender') === 'female')>Female</option>
                                    </select>
                                </div>
                            </div>
                            {{-- ID document --}}
                            <div>
                                <label class="block text-sm font-medium mb-2">ID document <span class="text-red-500">*</span></label>
                                <div class="flex gap-4 mb-3">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="id_type" value="national_id" x-model="childIdType" :disabled="flow !== 'parent' || studentMode !== 'new'"> Maldivian ID card
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="radio" name="id_type" value="passport" x-model="childIdType" :disabled="flow !== 'parent' || studentMode !== 'new'"> Passport
                                    </label>
                                </div>
                                <div x-show="childIdType === 'national_id'">
                                    <input type="text" name="national_id" placeholder="e.g. A211217"
                                           class="w-full rounded-md border-gray-300 uppercase" maxlength="10"
                                           :disabled="flow !== 'parent' || studentMode !== 'new' || childIdType !== 'national_id'"
                                           value="{{ old('national_id') }}"
                                           oninput="this.value=this.value.toUpperCase()">
                                    <p class="text-xs text-gray-500 mt-1">Format: letter followed by digits (e.g. A211217)</p>
                                </div>
                                <div x-show="childIdType === 'passport'" x-cloak>
                                    <input type="text" name="passport" placeholder="Passport number"
                                           class="w-full rounded-md border-gray-300 uppercase" maxlength="20"
                                           :disabled="flow !== 'parent' || studentMode !== 'new' || childIdType !== 'passport'"
                                           value="{{ old('passport') }}"
                                           oninput="this.value=this.value.toUpperCase()">
                                </div>
                            </div>
                            <div><label class="block text-sm font-medium mb-1">Relationship to child <span class="text-red-500">*</span></label>
                                <select name="relationship" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'">
                                    <option value="father" @selected(old('relationship') === 'father')>Father</option>
                                    <option value="mother" @selected(old('relationship') === 'mother')>Mother</option>
                                    <option value="guardian" @selected(old('relationship') === 'guardian')>Guardian</option>
                                    <option value="other" @selected(old('relationship') === 'other')>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div x-show="flow === 'adult'" class="space-y-4">
                        @if($existingProfile)
                            <p class="text-sm text-green-700 bg-green-50 rounded p-2">
                                Your details are pre-filled from your profile. Update if needed.
                            </p>
                        @endif
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium mb-1">First name <span class="text-red-500">*</span></label><input type="text" name="first_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('first_name', $existingProfile?->first_name) }}" required></div>
                            <div><label class="block text-sm font-medium mb-1">Last name <span class="text-red-500">*</span></label><input type="text" name="last_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('last_name', $existingProfile?->last_name) }}" required></div>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium mb-1">Date of birth <span class="text-red-500">*</span></label><input type="date" name="dob" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('dob', $existingProfile?->dob?->format('Y-m-d')) }}" required></div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Gender</label>
                                <select name="gender" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'">
                                    <option value="">Prefer not to say</option>
                                    <option value="male" @selected(old('gender', $existingProfile?->gender) === 'male')>Male</option>
                                    <option value="female" @selected(old('gender', $existingProfile?->gender) === 'female')>Female</option>
                                </select>
                            </div>
                        </div>
                        {{-- ID document --}}
                        <div>
                            <label class="block text-sm font-medium mb-2">ID document <span class="text-red-500">*</span></label>
                            <div class="flex gap-4 mb-3">
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="id_type" value="national_id" x-model="adultIdType" :disabled="flow !== 'adult'"> Maldivian ID card
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="id_type" value="passport" x-model="adultIdType" :disabled="flow !== 'adult'"> Passport
                                </label>
                            </div>
                            <div x-show="adultIdType === 'national_id'">
                                <input type="text" name="national_id" placeholder="e.g. A211217"
                                       class="w-full rounded-md border-gray-300 uppercase" maxlength="10"
                                       :disabled="flow !== 'adult' || adultIdType !== 'national_id'"
                                       value="{{ old('national_id', $existingProfile?->national_id) }}"
                                       oninput="this.value=this.value.toUpperCase()">
                                <p class="text-xs text-gray-500 mt-1">Format: letter followed by digits (e.g. A211217)</p>
                            </div>
                            <div x-show="adultIdType === 'passport'" x-cloak>
                                <input type="text" name="passport" placeholder="Passport number"
                                       class="w-full rounded-md border-gray-300 uppercase" maxlength="20"
                                       :disabled="flow !== 'adult' || adultIdType !== 'passport'"
                                       value="{{ old('passport', $existingProfile?->passport) }}"
                                       oninput="this.value=this.value.toUpperCase()">
                            </div>
                        </div>
                    </div>

                    {{-- Email (optional, used for confirmation receipt) --}}
                    <div class="mt-2 pt-4 border-t border-gray-100">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email address <span class="text-gray-400 font-normal">(optional — for your receipt)</span>
                        </label>
                        @php $existingEmail = $user->contacts()->where('type','email')->first()?->value; @endphp
                        <input type="email" name="email"
                               value="{{ old('email', $existingEmail) }}"
                               placeholder="you@example.com"
                               class="w-full rounded-md border-gray-300 shadow-sm focus:border-brandMaroon-500 focus:ring-brandMaroon-500">
                        @if($existingEmail)
                            <p class="text-xs text-gray-500 mt-1">Your email is already saved. Update if needed.</p>
                        @else
                            <p class="text-xs text-gray-500 mt-1">We'll send your enrollment confirmation here.</p>
                        @endif
                    </div>

                    <div class="mt-6 p-3 bg-gray-50 rounded">
                        <p class="font-medium mb-2">Courses selected:</p>
                        <ul class="list-disc list-inside">
                            @foreach($courses as $c)
                                <li>{{ $c->title }} @if($c->hasRegistrationFee())<span class="text-amber-600">(MVR {{ number_format($c->getRegistrationFeeAmount(), 2) }})</span>@endif</li>
                            @endforeach
                        </ul>
                    </div>

                    {{-- Terms acceptance --}}
                    <div class="mt-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <label class="flex items-start gap-3 cursor-pointer select-none">
                            <input type="checkbox" name="terms_accepted" id="terms_accepted" value="1" required
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brandMaroon-600 focus:ring-brandMaroon-500 shrink-0">
                            <span class="text-sm text-gray-700">
                                I have read and agree to the
                                <a href="{{ route('public.page.show', 'terms') }}" target="_blank"
                                   class="text-brandMaroon-600 hover:underline font-medium">terms and conditions</a>
                                and
                                <a href="{{ route('public.page.show', 'refund-policy') }}" target="_blank"
                                   class="text-brandMaroon-600 hover:underline font-medium">refund policy</a>
                                of Akuru Institute. I understand that enrollment fees are non-refundable unless the course is cancelled.
                            </span>
                        </label>
                        @error('terms_accepted')
                            <p class="text-red-600 text-xs mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="btn-primary w-full py-3 mt-4">Complete enrollment</button>

                    <x-payment-trust-bar />
                </form>
            </div>
        </div>
    </div>
</section>

<script>
function enrollFlow() {
    return {
        flow: '{{ $defaultFlow }}',
        studentMode: '{{ $user->guardianStudents->isNotEmpty() ? "existing" : "new" }}',
        adultIdType: '{{ old("id_type", $existingProfile?->national_id ? "national_id" : ($existingProfile?->passport ? "passport" : "national_id")) }}',
        childIdType: '{{ old("id_type", "national_id") }}',
        init() {
            if (this.studentMode === 'existing' && document.querySelector('input[name="student_mode"][value="existing"]')) {
                this.studentMode = 'existing';
            }
        }
    }
}
</script>
@endsection
