<?php

namespace App\Domains\People\Http\Controllers;

use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\DetachGuardianAction;
use App\Domains\People\Actions\ListStudentsAction;
use App\Domains\People\Actions\SaveCustomFieldValuesAction;
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

        return Inertia::render('People/Students/Index', [
            'filters' => $filters,
            'statuses' => array_map(fn (StudentStatus $status) => $status->value, StudentStatus::cases()),
            'students' => $students,
        ]);
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
            'relationships' => array_map(fn (GuardianRelationship $rel) => $rel->value, GuardianRelationship::cases()),
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
}
