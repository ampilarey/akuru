<?php

namespace App\Domains\People\Http\Controllers;

use App\Domains\People\Enums\EmploymentType;
use App\Domains\People\Enums\StaffStatus;
use App\Domains\People\Models\StaffProfile;
use App\Domains\People\Models\StaffQualification;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffDirectoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('People/Staff/Index', [
            'staff' => StaffProfile::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (StaffProfile $profile) => $this->serialize($profile)),
        ]);
    }

    /**
     * CLAUDE.md's standing rule: *"every listing gets CSV export."*
     *
     * The student directory has had one since S1.1; the staff directory is the
     * same shape of list and never got one, so the only way to take a staff
     * roll off the system was to copy it off the screen. Found by counting
     * `*.index` routes against `*.export` routes: 20 of 92 staff listings have
     * no export, and most of the rest are settings forms rather than lists.
     *
     * Deliberately **not** the whole serialiser. `national_id` and
     * `date_of_birth` are on the screen behind a login; a spreadsheet leaves
     * the building. The columns here are the ones a staff roll needs — who,
     * what they do, and whether they are still here.
     */
    public function export(): StreamedResponse
    {
        $staff = StaffProfile::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->streamDownload(function () use ($staff): void {
            $handle = fopen('php://output', 'w');
            Csv::put($handle, ['id', 'staff_number', 'first_name', 'last_name', 'department', 'designation', 'employment_type', 'status', 'joined_date']);

            foreach ($staff as $profile) {
                Csv::put($handle, [
                    $profile->id,
                    $profile->staff_number,
                    $profile->first_name,
                    $profile->last_name,
                    $profile->department,
                    $profile->designation,
                    $profile->employment_type?->value,
                    $profile->status?->value,
                    $profile->joined_date?->toDateString(),
                ]);
            }

            fclose($handle);
        }, 'staff.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function show(StaffProfile $staffProfile): Response
    {
        $staffProfile->load('qualifications');

        return Inertia::render('People/Staff/Show', [
            'staff' => $this->serialize($staffProfile, true),
            'employmentTypes' => array_map(fn (EmploymentType $type) => $type->value, EmploymentType::cases()),
            'statuses' => array_map(fn (StaffStatus $status) => $status->value, StaffStatus::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $profile = StaffProfile::query()->create($data);

        return redirect()
            ->route('people.staff.show', $profile)
            ->with('success', 'Staff profile created.');
    }

    public function update(Request $request, StaffProfile $staffProfile): RedirectResponse
    {
        $staffProfile->update($this->validated($request, $staffProfile->id));

        return redirect()
            ->route('people.staff.show', $staffProfile)
            ->with('success', 'Staff profile updated.');
    }

    public function storeQualification(Request $request, StaffProfile $staffProfile): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'document_id' => ['nullable', 'exists:documents,id'],
        ]);

        $staffProfile->qualifications()->create($data);

        return redirect()
            ->route('people.staff.show', $staffProfile)
            ->with('success', 'Qualification added.');
    }

    public function destroyQualification(StaffProfile $staffProfile, StaffQualification $qualification): RedirectResponse
    {
        abort_unless($qualification->staff_profile_id === $staffProfile->id, 404);
        $qualification->delete();

        return redirect()
            ->route('people.staff.show', $staffProfile)
            ->with('success', 'Qualification removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $uniqueUser = 'unique:staff_profiles,user_id';
        if ($ignoreId !== null) {
            $uniqueUser .= ','.$ignoreId;
        }

        return $request->validate([
            'user_id' => ['required', 'exists:users,id', $uniqueUser],
            'staff_number' => ['nullable', 'string', 'max:64'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'first_name_dhivehi' => ['nullable', 'string', 'max:255'],
            'last_name_dhivehi' => ['nullable', 'string', 'max:255'],
            'first_name_arabic' => ['nullable', 'string', 'max:255'],
            'last_name_arabic' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'passport' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string'],
            'joined_date' => ['nullable', 'date'],
            'employment_type' => ['required', 'in:'.implode(',', array_column(EmploymentType::cases(), 'value'))],
            'status' => ['required', 'in:'.implode(',', array_column(StaffStatus::cases(), 'value'))],
            'department' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(StaffProfile $profile, bool $withQualifications = false): array
    {
        $payload = [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'staff_number' => $profile->staff_number,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'first_name_dhivehi' => $profile->first_name_dhivehi,
            'last_name_dhivehi' => $profile->last_name_dhivehi,
            'first_name_arabic' => $profile->first_name_arabic,
            'last_name_arabic' => $profile->last_name_arabic,
            'date_of_birth' => $profile->date_of_birth?->toDateString(),
            'gender' => $profile->gender,
            'national_id' => $profile->national_id,
            'phone' => $profile->phone,
            'employment_type' => $profile->employment_type?->value,
            'status' => $profile->status?->value,
            'department' => $profile->department,
            'designation' => $profile->designation,
            'joined_date' => $profile->joined_date?->toDateString(),
        ];

        if ($withQualifications) {
            $payload['qualifications'] = $profile->qualifications->map(fn (StaffQualification $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'institution' => $row->institution,
                'year' => $row->year,
                'document_id' => $row->document_id,
            ]);
        }

        return $payload;
    }
}
