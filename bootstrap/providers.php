<?php

return [
    App\Providers\AppServiceProvider::class,

    // Domain service providers (Phase 0 skeleton — bindings/routes load in later slices)
    App\Domains\Identity\Providers\IdentityServiceProvider::class,
    App\Domains\People\Providers\PeopleServiceProvider::class,
    App\Domains\Academics\Providers\AcademicsServiceProvider::class,
    App\Domains\ExamsGrades\Providers\ExamsGradesServiceProvider::class,
    App\Domains\Hifz\Providers\HifzServiceProvider::class,
    App\Domains\Admissions\Providers\AdmissionsServiceProvider::class,
    App\Domains\Finance\Providers\FinanceServiceProvider::class,
    App\Domains\HR\Providers\HRServiceProvider::class,
    App\Domains\Commerce\Providers\CommerceServiceProvider::class,
    App\Domains\Library\Providers\LibraryServiceProvider::class,
    App\Domains\Courses\Providers\CoursesServiceProvider::class,
    App\Domains\Offerings\Providers\OfferingsServiceProvider::class,
    App\Domains\Progress\Providers\ProgressServiceProvider::class,
    App\Domains\Pronunciation\Providers\PronunciationServiceProvider::class,
    App\Domains\Media\Providers\MediaServiceProvider::class,
    App\Domains\Notifications\Providers\NotificationsServiceProvider::class,
    App\Domains\Portal\Providers\PortalServiceProvider::class,
    App\Domains\Website\Providers\WebsiteServiceProvider::class,
    App\Domains\Settings\Providers\SettingsServiceProvider::class,
];
