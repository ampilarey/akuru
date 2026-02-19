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
                            <div><label class="block text-sm font-medium mb-1">First name</label><input type="text" name="first_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('first_name') }}"></div>
                            <div><label class="block text-sm font-medium mb-1">Last name</label><input type="text" name="last_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('last_name') }}"></div>
                            <div><label class="block text-sm font-medium mb-1">Date of birth</label><input type="date" name="dob" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'" value="{{ old('dob') }}"></div>
                            <div><label class="block text-sm font-medium mb-1">Relationship</label>
                                <select name="relationship" class="w-full rounded-md border-gray-300" :disabled="flow !== 'parent' || studentMode !== 'new'">
                                    <option value="father">Father</option>
                                    <option value="mother">Mother</option>
                                    <option value="guardian">Guardian</option>
                                    <option value="other">Other</option>
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
                        <div><label class="block text-sm font-medium mb-1">First name</label><input type="text" name="first_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('first_name', $existingProfile?->first_name) }}"></div>
                        <div><label class="block text-sm font-medium mb-1">Last name</label><input type="text" name="last_name" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('last_name', $existingProfile?->last_name) }}"></div>
                        <div><label class="block text-sm font-medium mb-1">Date of birth</label><input type="date" name="dob" class="w-full rounded-md border-gray-300" :disabled="flow !== 'adult'" value="{{ old('dob', $existingProfile?->dob?->format('Y-m-d')) }}"></div>
                    </div>

                    <div class="mt-6 p-3 bg-gray-50 rounded">
                        <p class="font-medium mb-2">Courses selected:</p>
                        <ul class="list-disc list-inside">
                            @foreach($courses as $c)
                                <li>{{ $c->title }} @if($c->hasRegistrationFee())<span class="text-amber-600">(MVR {{ number_format($c->getRegistrationFeeAmount(), 2) }})</span>@endif</li>
                            @endforeach
                        </ul>
                    </div>

                    <button type="submit" class="btn-primary w-full py-3 mt-6">Complete enrollment</button>
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
        init() {
            if (this.studentMode === 'existing' && document.querySelector('input[name="student_mode"][value="existing"]')) {
                this.studentMode = 'existing';
            }
        }
    }
}
</script>
@endsection
