<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Website\Enums\LeadSource;
use App\Domains\Website\Enums\LeadStatus;
use App\Domains\Website\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The leads and funnel screens (docs/ADMIN_PANEL.md; C9 slice 6, STATUS
 * §5jh): Inertia pages with every UI string keyed EN/DV/AR, the filters as
 * props, unknown filter values ignored rather than trusted.
 */
it('lists the leads with the source and status filters and the keyed labels, and refuses the educational admin', function () {
    $super = actingSystemAdmin();
    $tajweed = Course::factory()->create(['title' => 'Tajweed Basics']);
    $fiqh = Course::factory()->create(['title' => 'Fiqh One']);
    Lead::query()->create(['course_id' => $tajweed->id, 'name' => 'Ali Syllabus', 'mobile' => '7770001', 'source' => LeadSource::Syllabus, 'status' => LeadStatus::New]);
    Lead::query()->create(['course_id' => $fiqh->id, 'name' => 'Mariyam Callback', 'mobile' => '7770002', 'source' => LeadSource::Callback, 'status' => LeadStatus::Contacted]);

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.leads.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Leads')
            ->has('leads', 2)
            ->where('filters', ['source' => '', 'status' => '', 'course_id' => ''])
            ->where('statuses', ['new', 'contacted', 'converted', 'closed'])
            ->where('t.leads_source_waiting_list', 'Waiting list')->where('t.leads_status_converted', 'Converted'));

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.leads.index', ['source' => 'callback']))
        ->assertInertia(fn (Assert $page) => $page->has('leads', 1)->where('leads.0.name', 'Mariyam Callback')->where('leads.0.course_title', 'Fiqh One')->where('filters.source', 'callback'));
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.leads.index', ['status' => 'contacted']))
        ->assertInertia(fn (Assert $page) => $page->has('leads', 1)->where('leads.0.name', 'Mariyam Callback'));
    // An unknown value is ignored, not trusted.
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.leads.index', ['source' => 'billboard']))
        ->assertInertia(fn (Assert $page) => $page->has('leads', 2));

    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.funnel.index', ['course_id' => 7]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/Funnel')->where('course_id', 7)->where('reports', [])->where('t.funnel_col_decision', 'Decision'));

    // Dhivehi and Arabic carry every key the pages read.
    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['leads_title', 'leads_none', 'leads_status_new', 'funnel_title', 'funnel_rule', 'funnel_col_rate'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }

    // The website is the system admin's (ADR-040 slice 2).
    $admin = \App\Domains\Identity\Models\User::factory()->create();
    $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('admin', 'web'));
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.leads.index'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('admin.funnel.index'))->assertForbidden();
});
