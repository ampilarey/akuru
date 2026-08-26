<?php

namespace App\Domains\People\Http\Controllers;

use App\Domains\Academics\Actions\ListBehaviorRecordsAction;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\DetachGuardianAction;
use App\Domains\People\Actions\ListStudentFormOptionsAction;
use App\Domains\People\Actions\ListStudentsAction;
use App\Domains\People\Actions\SaveCustomFieldValuesAction;
use App\Domains\People\Actions\SaveStudentAction;
use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Enums\ConsentType;
use App\Domains\People\Enums\CustomFieldEntityType;
use App\Domains\People\Enums\GuardianRelationship;
use App\Domains\People\Enums\StudentStatus;
use App\Domains\People\Models\Consent;
use App\Domains\People\Models\CustomFieldDefinition;
use App\Domains\People\Models\CustomFieldValue;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status', 'class_id']);
        $students = app(ListStudentsAction::class)->execute($filters);

        $options = app(ListStudentFormOptionsAction::class)->execute();

        return Inertia::render('People/Students/Index', [
            'filters' => $filters,
            'statuses' => $options['statuses'],
            'students' => $students,
            'schools' => $options['schools'],
            'classes' => $options['classes'],
            'guardians' => $options['guardians'],
            'relationships' => $options['relationships'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = app(SaveStudentAction::class)->execute(
            $this->validatedStudent($request),
            (int) $request->user()->id,
        );

        return redirect()
            ->route('people.students.show', $student)
            ->with('success', 'Student created.');
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        app(SaveStudentAction::class)->execute(
            $this->validatedStudent($request, $student->id),
            (int) $request->user()->id,
            $student,
        );

        return redirect()
            ->route('people.students.show', ['student' => $student, 'tab' => 'overview'])
            ->with('success', 'Student updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $students = app(ListStudentsAction::class)->execute($request->only(['search', 'status', 'class_id']));

        return response()->streamDownload(function () use ($students): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['id', 'student_number', 'first_name', 'last_name', 'national_id', 'status', 'class']);

            foreach ($students as $student) {
                fputcsv($handle, [
                    $student->id,
                    $student->student_id,
                    $student->first_name,
                    $student->last_name,
                    $student->national_id,
                    $student->status,
                    trim(($student->class_name ?? '').' '.($student->class_section ?? '')),
                ]);
            }

            fclose($handle);
        }, 'students.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(Request $request, Student $student): Response
    {
        $canViewSensitive = (bool) $request->user()?->can('students.view-sensitive');

        $definitions = CustomFieldDefinition::query()
            ->forEntity(CustomFieldEntityType::Students)
            ->forProfile()
            ->get();

        $values = CustomFieldValue::query()
            ->where('entity_type', CustomFieldEntityType::Students->value)
            ->where('entity_id', $student->id)
            ->get()
            ->keyBy('definition_id');

        $student->load(['guardians', 'emergencyContacts', 'statusHistory']);
        $options = app(ListStudentFormOptionsAction::class)->execute();

        return Inertia::render('People/Students/Show', [
            'tab' => $request->string('tab')->toString() ?: 'overview',
            'canViewSensitive' => $canViewSensitive,
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'first_name_dhivehi' => $student->first_name_dhivehi,
                'last_name_dhivehi' => $student->last_name_dhivehi,
                'first_name_arabic' => $student->first_name_arabic,
                'last_name_arabic' => $student->last_name_arabic,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'gender' => $student->gender,
                'national_id' => $student->national_id,
                'passport' => $student->passport,
                'email' => $student->email,
                'nationality' => $student->nationality,
                'phone' => $student->phone,
                'address' => $student->address,
                'status' => $student->status?->value,
                'class_id' => $student->class_id,
                'school_id' => $student->school_id,
                'admission_date' => $student->admission_date?->toDateString(),
                'place_of_birth' => $student->place_of_birth,
                'medical' => $canViewSensitive ? [
                    'medical_conditions' => $student->medical_conditions,
                    'allergies' => $student->allergies,
                    'doctor_name' => $student->doctor_name,
                    'doctor_phone' => $student->doctor_phone,
                ] : null,
            ],
            'customFields' => $definitions->map(fn (CustomFieldDefinition $definition) => [
                'id' => $definition->id,
                'key' => $definition->key,
                'label' => $definition->localizedLabel(),
                'field_type' => $definition->field_type->value,
                'options' => $definition->options ?? [],
                'required' => $definition->required,
                'value' => $values->get($definition->id)?->rawValue(),
            ]),
            'guardians' => $student->guardians->map(fn (ParentGuardian $guardian) => [
                'id' => $guardian->id,
                'name' => $guardian->full_name,
                'phone' => $guardian->phone,
                'relationship' => $guardian->pivot->relationship,
                'is_primary' => (bool) $guardian->pivot->is_primary,
                'can_pickup' => (bool) $guardian->pivot->can_pickup,
                'financial_responsible' => (bool) $guardian->pivot->financial_responsible,
            ]),
            'availableGuardians' => ParentGuardian::query()
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name'])
                ->map(fn (ParentGuardian $guardian) => [
                    'id' => $guardian->id,
                    'name' => $guardian->full_name,
                ]),
            'relationships' => $options['relationships'],
            'statuses' => $options['statuses'],
            'schools' => $options['schools'],
            'classes' => $options['classes'],
            'statusHistory' => $student->statusHistory->map(fn ($row) => [
                'id' => $row->id,
                'from_status' => $row->from_status?->value,
                'to_status' => $row->to_status?->value,
                'reason' => $row->reason,
                'effective_date' => $row->effective_date?->toDateString(),
            ]),
            'consentTypes' => array_map(fn (ConsentType $type) => $type->value, ConsentType::cases()),
            'consents' => Consent::query()
                ->where('person_type', ConsentPersonType::Student->value)
                ->where('person_id', $student->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn (Consent $consent) => [
                    'id' => $consent->id,
                    'consent_type' => $consent->consent_type->value,
                    'granted' => $consent->granted,
                    'granted_at' => $consent->granted_at?->toDateTimeString(),
                    'revoked_at' => $consent->revoked_at?->toDateTimeString(),
                    'source' => $consent->source->value,
                ]),
            'documents' => [],
            'behaviorRecords' => app(ListBehaviorRecordsAction::class)->execute(['student_id' => $student->id]),
        ]);
    }

    public function updateCustomFields(Request $request, Student $student): RedirectResponse
    {
        try {
            app(SaveCustomFieldValuesAction::class)->execute(
                CustomFieldEntityType::Students,
                $student->id,
                $request->input('values', []),
            );
        } catch (ValidationException $exception) {
            throw $exception;
        }

        return redirect()
            ->route('people.students.show', ['student' => $student, 'tab' => 'overview'])
            ->with('success', 'Custom fields saved.');
    }

    public function attachGuardian(Request $request, Student $student): RedirectResponse
    {
        $data = $request->validate([
            'guardian_id' => ['required', 'exists:parent_guardians,id'],
            'relationship' => ['required', 'in:'.implode(',', array_column(GuardianRelationship::cases(), 'value'))],
            'is_primary' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
            'financial_responsible' => ['sometimes', 'boolean'],
        ]);

        app(AttachGuardianAction::class)->execute(
            $student,
            ParentGuardian::query()->findOrFail((int) $data['guardian_id']),
            $data['relationship'],
            (bool) ($data['is_primary'] ?? false),
            (bool) ($data['can_pickup'] ?? true),
            (bool) ($data['financial_responsible'] ?? false),
        );

        return redirect()
            ->route('people.students.show', ['student' => $student, 'tab' => 'guardians'])
            ->with('success', 'Guardian attached.');
    }

    public function detachGuardian(Student $student, ParentGuardian $guardian): RedirectResponse
    {
        app(DetachGuardianAction::class)->execute($student, $guardian);

        return redirect()
            ->route('people.students.show', ['student' => $student, 'tab' => 'guardians'])
            ->with('success', 'Guardian detached.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedStudent(Request $request, ?int $studentId = null): array
    {
        $request->merge([
            'school_id' => $this->emptyToNull($request->input('school_id')),
            'class_id' => $this->emptyToNull($request->input('class_id')),
            'user_id' => $this->emptyToNull($request->input('user_id')),
            'guardian_id' => $this->emptyToNull($request->input('guardian_id')),
            'student_id' => $this->emptyToNull($request->input('student_id')),
            'admission_date' => $this->emptyToNull($request->input('admission_date')),
            'national_id' => $this->emptyToNull($request->input('national_id')),
            'passport' => $this->emptyToNull($request->input('passport')),
            'email' => $this->emptyToNull($request->input('email')),
            'place_of_birth' => $this->emptyToNull($request->input('place_of_birth')),
            'phone' => $this->emptyToNull($request->input('phone')),
            'address' => $this->emptyToNull($request->input('address')),
            'notes' => $this->emptyToNull($request->input('notes')),
            'first_name_arabic' => $this->emptyToNull($request->input('first_name_arabic')),
            'last_name_arabic' => $this->emptyToNull($request->input('last_name_arabic')),
            'first_name_dhivehi' => $this->emptyToNull($request->input('first_name_dhivehi')),
            'last_name_dhivehi' => $this->emptyToNull($request->input('last_name_dhivehi')),
        ]);

        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name_arabic' => ['nullable', 'string', 'max:255'],
            'last_name_arabic' => ['nullable', 'string', 'max:255'],
            'first_name_dhivehi' => ['nullable', 'string', 'max:255'],
            'last_name_dhivehi' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date'],
            'gender' => ['required', 'in:male,female'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'passport' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'place_of_birth' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'student_id' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('students', 'student_id')->ignore($studentId),
            ],
            'admission_date' => ['nullable', 'date'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'notes' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guardian_id' => ['nullable', 'integer', 'exists:parent_guardians,id'],
            'guardian_relationship' => ['nullable', 'required_with:guardian_id', Rule::enum(GuardianRelationship::class)],
            'is_primary' => ['sometimes', 'boolean'],
            'can_pickup' => ['sometimes', 'boolean'],
            'financial_responsible' => ['sometimes', 'boolean'],
        ]);
    }

    private function emptyToNull(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}
