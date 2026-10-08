<?php

use App\Domains\Courses\Models\GlossaryItem;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A form answers in the language of the page it was sent from (STATUS §5os).
 *
 * The screens send their forms to addresses without a language
 * (`/catalog/glossary`), and LaravelLocalization leaves POST, PUT, PATCH and
 * DELETE in the default language — so the course screens' translated
 * "Term saved." was English on a Dhivehi page. Found by the CT5b walk:
 * a mushaf uploaded from the Dhivehi form said *Mushaf created.*
 *
 * The localization middleware stays on here (no `withoutLocalizationMiddleware`):
 * it is the subject.
 */
function glossaryEditor(): User
{
    return actingPeopleAdmin(['courses.manage']);
}

it('says what was saved in Dhivehi when the form came from a Dhivehi page', function () {
    $this->actingAs(glossaryEditor())
        ->withHeader('Referer', url('/dv/catalog/glossary'))
        ->post('/catalog/glossary', ['term' => 'Tajweed'])
        ->assertSessionHas('success', trans('teach.flash_term_saved', [], 'dv'))
        // …and the save's redirect comes back to the Dhivehi page.
        ->assertSessionHas('locale', 'dv');
});

it('answers a change and a delete in Arabic from an Arabic page', function () {
    $editor = glossaryEditor();
    $term = GlossaryItem::query()->create(['term' => 'Madd', 'created_by' => $editor->id]);

    $this->actingAs($editor)
        ->withHeader('Referer', url('/ar/catalog/glossary'))
        ->put("/catalog/glossary/{$term->id}", ['term' => 'Madd'])
        ->assertSessionHas('success', trans('teach.flash_term_updated', [], 'ar'));
    $this->actingAs($editor)
        ->withHeader('Referer', url('/ar/catalog/glossary'))
        ->delete("/catalog/glossary/{$term->id}")
        ->assertSessionHas('success', trans('teach.flash_term_deleted', [], 'ar'));
});

it('takes the remembered language when the browser sends no Referer', function () {
    $this->actingAs(glossaryEditor())
        ->withSession(['locale' => 'dv'])
        ->post('/catalog/glossary', ['term' => 'Ghunnah'])
        ->assertSessionHas('success', trans('teach.flash_term_saved', [], 'dv'));
});

it('lets the page posted from win over the remembered language', function () {
    // Two tabs: the Arabic one was opened last, the English one saves.
    $this->actingAs(glossaryEditor())
        ->withSession(['locale' => 'ar'])
        ->withHeader('Referer', url('/en/catalog/glossary'))
        ->post('/catalog/glossary', ['term' => 'Qalqalah'])
        ->assertSessionHas('success', 'Term saved.')
        ->assertSessionHas('locale', 'en');
});

it('does not take a language from another site’s page', function () {
    $this->actingAs(glossaryEditor())
        ->withHeader('Referer', 'https://elsewhere.example/dv/catalog/glossary')
        ->post('/catalog/glossary', ['term' => 'Idgham'])
        ->assertSessionHas('success', 'Term saved.');
});

it('leaves a page that is only read to the localization package', function () {
    // A GET without a language is sent on to the remembered one, as before.
    $this->actingAs(glossaryEditor())
        ->withSession(['locale' => 'dv'])
        ->get('/catalog/glossary')
        ->assertRedirectContains('/dv');
});
