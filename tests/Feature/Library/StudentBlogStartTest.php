<?php

use App\Domains\Identity\Models\User;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\HasActiveConsentAction;
use App\Domains\People\Actions\RecordConsentAction;
use App\Domains\People\Enums\ConsentPersonType;
use App\Domains\People\Enums\ConsentSource;
use App\Domains\People\Enums\ConsentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * BLOG_PLAN BL1 (STATUS §5se): a student asks to start a blog, a parent
 * agrees if the student is under 18, and the office approves.
 */
uses(RefreshDatabase::class);

function bl1Child(string $born = '2014-05-01'): array
{
    $student = makeStudent(['first_name' => 'Aisha', 'last_name' => 'Rasheed', 'date_of_birth' => $born]);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'mother', true);

    return [$student, User::query()->findOrFail($student->user_id), User::query()->findOrFail($guardian->user_id)];
}

function bl1Ask(User $user, array $data = []): \Illuminate\Testing\TestResponse
{
    return test()->withoutLocalizationMiddleware()->actingAs($user)->post('/my-blog', $data + ['pen_name' => 'Starling', 'blog_title' => 'Notes from Malé', 'about' => 'Short stories and what I read this term.']);
}

it('takes an under-18 student\'s request to their parent, then to the office, and makes a student blog', function () {
    [$student, $child, $parent] = bl1Child();
    $web = test()->withoutLocalizationMiddleware();

    $web->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->component('Library/MyBlog')->where('state', 'start')->where('under_18', true)->where('guardian_linked', true));
    bl1Ask($child)->assertRedirect(route('my-blog'))->assertSessionHas('success', __('blog.flash_asked_parent'));
    $request = BlogRequest::query()->sole();
    expect($request->status->value)->toBe('waiting_parent')->and($request->under_18)->toBeTrue();
    // The parent heard, with a link to the request.
    expect(\Illuminate\Support\Facades\DB::table('user_notifications')->where('user_id', $parent->id)->pluck('data')->map(fn ($d) => json_decode((string) $d, true)['href'] ?? null)->all())->toContain("/portal/children/{$student->id}/blog");

    // The office cannot approve before the parent.
    $office = actingSystemAdmin(['library.manage']);
    $web->actingAs($office)->post("/admin/library/blog-requests/{$request->id}/decide", ['decision' => 'approve'])->assertSessionHasErrors('decision');

    // The parent sees it on My children and agrees, allowing the school name but not the photo.
    $web->actingAs($parent)->get('/portal/children')->assertInertia(fn (Assert $p) => $p->where("blogs.{$student->id}.status", 'waiting_parent'));
    $web->actingAs($parent)->get("/portal/children/{$student->id}/blog")->assertInertia(fn (Assert $p) => $p->component('Portal/ChildBlog')->where('request.pen_name', 'Starling'));
    $web->actingAs($parent)->post("/portal/children/{$student->id}/blog/consent", ['request_id' => $request->id, 'agree' => true, 'show_photo' => false, 'show_school' => true])
        ->assertSessionHas('success', __('blog.flash_parent_agreed'));
    expect($request->fresh()->status->value)->toBe('waiting_office')
        ->and(app(HasActiveConsentAction::class)->execute(ConsentPersonType::Student, $student->id, ConsentType::BlogPublishing))->toBeTrue();

    // The office sees the real name beside the pen name, and approves.
    $web->actingAs($office)->get('/admin/library/blog-requests')->assertInertia(fn (Assert $p) => $p->component('Library/BlogRequests')
        ->where('requests.0.pen_name', 'Starling')->where('requests.0.student_name', fn ($n) => str_contains($n, 'Rasheed'))->where('requests.0.status', 'waiting_office'));
    $web->actingAs($office)->post("/admin/library/blog-requests/{$request->id}/decide", ['decision' => 'approve'])->assertSessionHas('success', __('blog.flash_approved'));

    $profile = WriterProfile::query()->where('user_id', $child->id)->sole();
    expect($profile->kind)->toBe('student')->and($profile->display_name)->toBe('Starling')->and($profile->blog_title)->toBe('Notes from Malé')
        ->and($profile->show_photo)->toBeFalse()->and($profile->show_school)->toBeTrue()->and($profile->guardian_user_id)->toBe($parent->id)
        ->and($child->fresh()->hasRole('writer'))->toBeTrue();
    $web->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('state', 'blog')->where('blog.blog_title', 'Notes from Malé'));
});

it('never approves an under-18 student without a parent\'s consent in force', function () {
    // No verified parent: the request waits, the student and the office are told why.
    $alone = makeStudent(['first_name' => 'Nimal', 'last_name' => 'Hussain', 'date_of_birth' => '2013-01-01']);
    $user = User::query()->findOrFail($alone->user_id);
    test()->withoutLocalizationMiddleware()->actingAs($user)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('guardian_linked', false));
    bl1Ask($user)->assertRedirect();
    $office = actingSystemAdmin(['library.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($office)->get('/admin/library/blog-requests')->assertInertia(fn (Assert $p) => $p->where('requests.0.no_guardian', true));

    // A parent who agrees and then withdraws the consent on the record stops the approval.
    [$student, $child, $parent] = bl1Child();
    bl1Ask($child);
    $request = BlogRequest::query()->where('user_id', $child->id)->sole();
    test()->withoutLocalizationMiddleware()->actingAs($parent)->post("/portal/children/{$student->id}/blog/consent", ['request_id' => $request->id, 'agree' => true]);
    app(RecordConsentAction::class)->execute(ConsentPersonType::Student, $student->id, ConsentType::BlogPublishing, false, $parent->id, ConsentSource::Admin);
    test()->withoutLocalizationMiddleware()->actingAs($office)->post("/admin/library/blog-requests/{$request->id}/decide", ['decision' => 'approve'])->assertSessionHasErrors('decision');
    expect(WriterProfile::query()->where('user_id', $child->id)->exists())->toBeFalse();
});

it('lets only the child\'s own verified parent answer, and a no closes the request', function () {
    [$student, $child, $parent] = bl1Child();
    bl1Ask($child);
    $request = BlogRequest::query()->sole();

    $stranger = User::query()->findOrFail(makeGuardian()->user_id);
    test()->withoutLocalizationMiddleware()->actingAs($stranger)->get("/portal/children/{$student->id}/blog")->assertForbidden();
    test()->withoutLocalizationMiddleware()->actingAs($stranger)->post("/portal/children/{$student->id}/blog/consent", ['request_id' => $request->id, 'agree' => true])->assertForbidden();

    test()->withoutLocalizationMiddleware()->actingAs($parent)->post("/portal/children/{$student->id}/blog/consent", ['request_id' => $request->id, 'agree' => false])
        ->assertSessionHas('success', __('blog.flash_parent_declined'));
    expect($request->fresh()->status->value)->toBe('parent_declined');
    // Declined, the student may ask again.
    test()->withoutLocalizationMiddleware()->actingAs($child)->get('/my-blog')->assertInertia(fn (Assert $p) => $p->where('state', 'request')->where('request.can_ask_again', true));
});

it('sends an adult student straight to the office, needs a note to refuse, and keeps the pen name off the surname', function () {
    [, $adult] = bl1Child('2000-01-01');
    bl1Ask($adult, ['pen_name' => 'Aisha Rasheed'])->assertSessionHasErrors('pen_name');
    bl1Ask($adult)->assertSessionHas('success', __('blog.flash_asked_office'));
    $request = BlogRequest::query()->sole();
    expect($request->status->value)->toBe('waiting_office')->and($request->under_18)->toBeFalse();
    bl1Ask($adult, ['pen_name' => 'Another'])->assertSessionHasErrors('pen_name');

    $office = actingSystemAdmin(['library.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($office)->post("/admin/library/blog-requests/{$request->id}/decide", ['decision' => 'refuse'])->assertSessionHasErrors('note');
    test()->withoutLocalizationMiddleware()->actingAs($office)->post("/admin/library/blog-requests/{$request->id}/decide", ['decision' => 'refuse', 'note' => 'Please tell us more about the topics.'])
        ->assertSessionHas('success', __('blog.flash_refused'));
    expect($request->fresh()->status->value)->toBe('refused');

    $csv = test()->withoutLocalizationMiddleware()->actingAs($office)->get('/admin/library/blog-requests/export')->assertOk()->streamedContent();
    expect($csv)->toContain('pen_name,blog_title,student')->toContain('Starling')->toContain('Please tell us more');
    test()->withoutLocalizationMiddleware()->actingAs($adult)->get('/admin/library/blog-requests')->assertForbidden();
});

it('keeps a student blog out of the full writer\'s tools, and leaves a writer application as it was', function () {
    [$student, $child, $parent] = bl1Child();
    bl1Ask($child);
    $request = BlogRequest::query()->sole();
    test()->withoutLocalizationMiddleware()->actingAs($parent)->post("/portal/children/{$student->id}/blog/consent", ['request_id' => $request->id, 'agree' => true]);
    test()->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin(['library.manage']))->post("/admin/library/blog-requests/{$request->id}/decide", ['decision' => 'approve']);

    $web = test()->withoutLocalizationMiddleware()->actingAs($child);
    $web->get('/write')->assertInertia(fn (Assert $p) => $p->where('dashboard.profile.kind', 'student'));
    $web->post('/write/items', ['title' => 'A book', 'content_type' => 'book', 'access_type' => 'free_public', 'language' => 'en'])->assertSessionHasErrors('writer');
    $web->post('/write/bank-details', ['bank_name' => 'BML', 'account_name' => 'A', 'account_number' => '7730000000001'])->assertSessionHasErrors('writer');
    $web->post('/write/profile', ['display_name' => 'Aisha Rasheed'])->assertSessionHasErrors('writer');
    expect(WriterProfile::query()->where('user_id', $child->id)->value('display_name'))->toBe('Starling');

    // A full writer's profile, as an approved application makes it, is a writer's.
    $writer = User::factory()->create();
    expect(WriterProfile::query()->create(['user_id' => $writer->id, 'display_name' => 'Adult Writer', 'slug' => 'adult-writer', 'status' => 'active'])->fresh()->kind)->toBe('writer');
});

it('says My blog in the student\'s and the learner\'s menus, and Blog requests under the Library office', function () {
    $hrefs = fn (array $items) => array_column($items, 'href');
    $bars = \App\Support\Navigation\NavigationMap::primary();
    expect($hrefs($bars['student']))->toContain('/my-blog')->and($hrefs($bars['learner']))->toContain('/my-blog')
        ->and(\App\Support\Navigation\NavigationMap::hrefs())->toContain('/admin/library/blog-requests');
    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(__('nav.my_blog', [], $locale))->not->toBe('nav.my_blog')->and(__('blog.start_heading', [], $locale))->not->toBe('blog.start_heading');
    }
    expect(__('blog.start_heading', [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('blog.start_heading', [], 'ar'))->toMatch('/\p{Arabic}/u');
});
