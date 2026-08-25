<?php

use App\Domains\HR\Actions\ExpiringDocumentsReportAction;
use App\Domains\HR\Actions\NotifyExpiringDocumentsAction;
use App\Domains\HR\Actions\SaveStaffContractAction;
use App\Domains\HR\Enums\StaffContractStatus;
use App\Domains\HR\Enums\StaffContractType;
use App\Domains\HR\Models\DocumentExpiryNotice;
use App\Domains\HR\Models\StaffContract;
use App\Domains\Media\Actions\StoreDocumentAction;
use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows only one active contract and keeps superseded history', function () {
    $admin = actingPeopleAdmin(['hr.manage']);
    $staff = makeStaffProfile();

    $first = app(SaveStaffContractAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'contract_type' => StaffContractType::Permanent->value,
        'start_date' => '2024-01-01',
        'basic_salary' => 8000,
        'status' => StaffContractStatus::Active->value,
    ]);

    $second = app(SaveStaffContractAction::class)->execute([
        'staff_profile_id' => $staff->id,
        'contract_type' => StaffContractType::FixedTerm->value,
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
        'basic_salary' => 9500,
        'status' => StaffContractStatus::Active->value,
    ]);

    expect(StaffContract::query()->where('status', StaffContractStatus::Active)->count())->toBe(1)
        ->and($first->fresh()->status)->toBe(StaffContractStatus::Superseded)
        ->and($second->fresh()->status)->toBe(StaffContractStatus::Active)
        ->and(StaffContract::query()->count())->toBe(2);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.contracts.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Contracts/Index')
            ->has('rows', 2)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.contracts.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('superseded')->toContain('active');
});

it('reports expiring documents and notifies each horizon only once', function () {
    $admin = actingPeopleAdmin(['hr.manage']);
    $staff = makeStaffProfile();

    $document = app(StoreDocumentAction::class)->execute([
        'documentable_type' => 'staff_profile',
        'documentable_id' => $staff->id,
        'media_path' => 'documents/permit.html',
        'document_type' => 'other',
        'title' => 'Work permit',
        'expires_at' => now('Indian/Maldives')->addDays(25)->toDateString(),
        'uploaded_by' => $admin->id,
    ]);

    $rows = app(ExpiringDocumentsReportAction::class)->execute(90);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['title'])->toBe('Work permit');

    $first = app(NotifyExpiringDocumentsAction::class)->execute([90, 60, 30]);
    $second = app(NotifyExpiringDocumentsAction::class)->execute([90, 60, 30]);

    expect($first)->toBeGreaterThan(0)
        ->and($second)->toBe(0)
        ->and(DocumentExpiryNotice::query()->where('document_id', $document['id'])->count())->toBe(3)
        ->and(UserNotification::query()->where('title', 'like', 'Document expiring%')->count())->toBeGreaterThan(0);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('hr.compliance.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('HR/Compliance/Index')
            ->has('rows', 1)
        );
});
