<?php

use Illuminate\Support\Arr;

/**
 * A phrase book's Dhivehi and Arabic are translations, not placeholders
 * (slice LT1, STATUS §5pa).
 *
 * The learn book's first Dhivehi batch was a few words pasted across rows:
 * *Open*, *Continue* and *Next* all read "then"; *Learn catalog*, *Course* and
 * the catalog's introduction all read "electronic education"; *Preview only —
 * enroll to track progress.* read "profile". The common book said "Submit" for
 * *Delete* and "men" for *Back*. Every key existed and none was English, so
 * every language test passed. A pasted batch gives itself away twice — a
 * sentence translated by a word, and one translation standing for sentences
 * that say different things — and these tests hold both across every book.
 */

/** @return array<string, array{en: array<string, string>, dv: array<string, string>, ar: array<string, string>}> */
function everyPhraseBook(): array
{
    $books = [];
    foreach (glob(resource_path('lang/en/*.php')) as $file) {
        $book = basename($file, '.php');
        foreach (['en', 'dv', 'ar'] as $locale) {
            $path = resource_path("lang/{$locale}/{$book}.php");
            $phrases = is_file($path) ? Arr::dot((array) require $path) : [];
            $books[$book][$locale] = array_filter($phrases, 'is_string');
        }
    }

    return $books;
}

/** The English, lowercased without placeholders or punctuation. */
function englishSense(string $english): string
{
    $words = preg_replace('/[^a-z0-9 ]+/', ' ', strtolower(preg_replace('/:[a-z_]+/', '', $english)));

    return trim(preg_replace('/\s+/', ' ', $words));
}

/**
 * Reviewed (2026-10-08): each group is one translation standing for English
 * phrases that say the same thing in other words. A new group fails the test
 * until someone reads it and either fixes the copy or adds it here.
 *
 * @return list<string>
 */
function reviewedSharedTranslations(): array
{
    return [
        'account.dv: child_awaiting, id_status_pending, state_waiting_office',
        'account.ar: child_awaiting, id_status_pending, state_waiting_office',
        'account.ar: id_learner_title, id_title',
        'lending.dv: book_status_on_loan, loan_status_out, on_loan_badge',
        'lending.ar: book_status_on_loan, loan_status_out, on_loan_badge',
        'nav.dv: lost_and_found, lost_property',
        'nav.ar: lost_and_found, lost_property',
        'public.dv: Contact, Get in Touch',
        'shop.dv: name_dv, title_dv',
        'shop.dv: name_ar, title_ar',
        'shop.dv: low_stock, notice_low_stock_title',
        'shop.dv: add_to_cart, move_to_cart',
        'shop.dv: error_answer_empty, error_reply_empty',
        'shop.dv: book_list_unavailable, saved_unavailable',
        'shop.dv: customer_note_due, customers_follow_ups',
        'shop.ar: book_list_grade, grade',
        'shop.ar: driver_note, slip_note',
        'shop.ar: event_return_requested, notice_return_requested_title, return_requested',
        'shop.ar: notice_event_review, notice_review_title',
        'shop.ar: campaign_send, quote_send',
        'shop.ar: book_list_unavailable, saved_unavailable',
        'shop.ar: akuru_handling_line, akuru_page_title',
        'teach.dv: pattern_arrange, question_type_arrange',
        'teach.ar: catalog_waiting_review, review_pending',
        'teach.ar: arref_order, question_type_arrange',
    ];
}

it('translates no sentence with a word', function () {
    $short = [];
    foreach (everyPhraseBook() as $book => $locales) {
        foreach (['dv', 'ar'] as $locale) {
            foreach ($locales['en'] as $key => $english) {
                $translated = $locales[$locale][$key] ?? null;
                if ($translated !== null && mb_strlen($english) >= 16 && mb_strlen($translated) / mb_strlen($english) < 0.35) {
                    $short[] = "{$book}.{$locale}.{$key}: “{$translated}” for “{$english}”";
                }
            }
        }
    }

    expect($short)->toBe([]);
});

it('gives two different sentences one translation only where they mean the same', function () {
    $found = [];
    foreach (everyPhraseBook() as $book => $locales) {
        foreach (['dv', 'ar'] as $locale) {
            $byTranslation = [];
            foreach ($locales[$locale] as $key => $translated) {
                $english = $locales['en'][$key] ?? null;
                // An untranslated row is another test's business.
                if ($english === null || $translated === $english) {
                    continue;
                }
                $byTranslation[$translated][englishSense($english)][] = $key;
            }
            foreach ($byTranslation as $senses) {
                $sentences = array_filter(array_keys($senses), fn (string $sense) => str_word_count($sense) >= 3);
                if (count($senses) < 2 || $sentences === []) {
                    continue;
                }
                $keys = array_merge(...array_values($senses));
                sort($keys);
                $found[] = "{$book}.{$locale}: ".implode(', ', $keys);
            }
        }
    }

    $reviewed = reviewedSharedTranslations();
    expect(array_values(array_diff($found, $reviewed)))->toBe([], 'One translation for different sentences — fix the copy, or review it into reviewedSharedTranslations()')
        ->and(array_values(array_diff($reviewed, $found)))->toBe([], 'Reviewed groups that no longer exist — take them out');
});
