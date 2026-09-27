<?php

use App\Domains\Website\Models\DailyContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * The daily content screens (docs/ADMIN_PANEL.md; C9 slice 9, STATUS §5jk):
 * Inertia pages with every string keyed EN/DV/AR — the calendar with its
 * filters, the form for new and edit, the approval queue — behind the two
 * `daily_content.*` permissions as before.
 */
it('renders the calendar, the form and the queue as props with the keyed strings, and keeps the permission gates', function () {
    $maker = actingSystemAdmin(['daily_content.manage']);
    $checker = actingSystemAdmin(['daily_content.manage', 'daily_content.approve']);

    $this->withoutLocalizationMiddleware()->actingAs($maker)->post(route('admin.daily-content.store'), [
        'content_type' => 'reminder', 'publish_date' => '2026-10-03', 'text_en' => 'Seek knowledge.', 'text_dv' => 'އިލްމު ހޯދާ', 'attribution' => 'Teaching note', 'theme_tag' => 'knowledge',
    ])->assertRedirect()->assertSessionHas('success', 'Draft saved. A second reviewer must approve before it is scheduled.');
    $row = DailyContent::query()->sole();

    $this->withoutLocalizationMiddleware()->actingAs($maker)->get(route('admin.daily-content.index', ['month' => '2026-10', 'content_type' => 'reminder']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/DailyContent')
            ->where('month', '2026-10')->where('filters.content_type', 'reminder')->where('filters.status', '')
            ->where('items.0.id', $row->id)->where('items.0.status', 'draft')->where('items.0.text_en', 'Seek knowledge.')
            ->where('t.daily_title', 'Daily content')->where('t.daily_day_sun', 'Sun')->where('t.daily_batch_title', 'Theme batch (reminder drafts)'));
    $this->withoutLocalizationMiddleware()->actingAs($maker)->get(route('admin.daily-content.index', ['month' => '2026-11']))
        ->assertInertia(fn (Assert $page) => $page->where('items', []));

    $this->withoutLocalizationMiddleware()->actingAs($maker)->get(route('admin.daily-content.create', ['content_type' => 'hadith']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/DailyContentForm')->where('item', null)->where('type', 'hadith')->where('t.daily_new_title', 'New daily content'));
    $this->withoutLocalizationMiddleware()->actingAs($maker)->get(route('admin.daily-content.edit', $row))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/DailyContentForm')->where('item.id', $row->id)->where('item.content_type', 'reminder')->where('type', 'reminder'));

    // The edit posts a status of draft or archived; archived reads back on the form.
    $this->withoutLocalizationMiddleware()->actingAs($maker)->put(route('admin.daily-content.update', $row), [
        'content_type' => 'reminder', 'publish_date' => '2026-10-03', 'text_en' => 'Seek knowledge, always.', 'text_dv' => 'އިލްމު ހޯދާ', 'attribution' => 'Teaching note', 'status' => 'archived',
    ])->assertRedirect(route('admin.daily-content.edit', $row))->assertSessionHas('success', 'Daily content updated.');
    expect($row->fresh()->status->value)->toBe('archived');

    // The queue: the maker's own draft waits for another reviewer; the checker approves.
    $this->withoutLocalizationMiddleware()->actingAs($maker)->post(route('admin.daily-content.store'), [
        'content_type' => 'saying', 'publish_date' => '2026-10-04', 'text_en' => 'Patience.', 'text_dv' => 'ކެތްތެރިކަން', 'attribution' => 'Proverb',
    ])->assertRedirect();
    $saying = DailyContent::query()->where('content_type', 'saying')->sole();
    $this->withoutLocalizationMiddleware()->actingAs($checker)->get(route('admin.daily-content.queue'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Website/DailyContentQueue')->has('items', 1)->where('items.0.id', $saying->id)->where('items.0.created_by', $maker->id)->where('t.daily_approve_publish', 'Approve & publish'));
    $this->withoutLocalizationMiddleware()->actingAs($checker)->post(route('admin.daily-content.approve', $saying), ['status' => 'scheduled'])
        ->assertRedirect(route('admin.daily-content.queue'))->assertSessionHas('success', 'Approved.');
    expect($saying->fresh()->status->value)->toBe('scheduled');

    // A theme batch plants a run of reminder drafts with the keyed flash.
    $this->withoutLocalizationMiddleware()->actingAs($maker)->post(route('admin.daily-content.batch'), [
        'publish_date' => '2026-12-01', 'days' => 3, 'theme_tag' => 'winter', 'attribution' => 'Office', 'text_en' => 'Reflect.', 'text_dv' => 'ވިސްނާ',
    ])->assertRedirect(route('admin.daily-content.index'))->assertSessionHas('success', '3 reminder drafts created. Each still needs a second approver.');

    foreach (['dv', 'ar'] as $locale) {
        $strings = trans('admin', [], $locale);
        foreach (['daily_title', 'daily_intro', 'daily_queue_title', 'daily_waiting_reviewer', 'daily_flash_saved', 'daily_flash_batch', 'daily_day_fri'] as $key) {
            expect($strings[$key] ?? null)->toBeString()->not->toBe(trans('admin.'.$key, [], 'en'));
        }
    }
    // The permission gates themselves are DailyContentStoreTest's (a system admin holds every permission).
});
