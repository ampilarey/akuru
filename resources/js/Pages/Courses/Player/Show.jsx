import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

function mediaSrc(mediaShowUrl, mediaId) {
    return `${mediaShowUrl}/${mediaId}`;
}

function termLabels(item) {
    return [item.term, item.term_dv, item.term_ar].filter(Boolean);
}

function meaningFor(item, locale) {
    if (locale === 'dv') {
        return item.meaning_dv || item.meaning_primary || item.meaning_ar || '';
    }
    if (locale === 'ar') {
        return item.meaning_ar || item.meaning_primary || item.meaning_dv || '';
    }

    return item.meaning_primary || item.meaning_secondary || item.meaning_dv || item.meaning_ar || '';
}

function descriptionFor(item, locale) {
    if (locale === 'dv') {
        return item.description_dv || item.description || '';
    }
    if (locale === 'ar') {
        return item.description_ar || item.description || '';
    }

    return item.description || '';
}

function exampleFor(item, locale) {
    if (locale === 'dv') {
        return item.example_text_dv || item.example_text || '';
    }
    if (locale === 'ar') {
        return item.example_text_ar || item.example_text || '';
    }

    return item.example_text || '';
}

function dirForLabel(label, item) {
    if (label && (label === item.term_ar || label === item.term_dv)) {
        return 'rtl';
    }

    return 'auto';
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function longestLabelPairs(items) {
    const pairs = [];
    items.forEach((item) => {
        termLabels(item).forEach((label) => pairs.push({ label, item }));
    });
    pairs.sort((a, b) => b.label.length - a.label.length);

    return pairs;
}

function wrapPlainText(text, items, onSelect) {
    if (!text || !items?.length) {
        return text;
    }
    const pairs = longestLabelPairs(items);
    if (pairs.length === 0) {
        return text;
    }
    const re = new RegExp(`(${pairs.map((pair) => escapeRegExp(pair.label)).join('|')})`, 'gi');
    const parts = String(text).split(re);

    return parts.map((part, index) => {
        const match = pairs.find((pair) => pair.label.toLowerCase() === part.toLowerCase());
        if (!match) {
            return part;
        }

        return (
            <button
                key={`${match.item.id}-${index}`}
                type="button"
                className="glossary-term"
                dir={dirForLabel(part, match.item)}
                onClick={() => onSelect(match.item)}
            >
                {part}
            </button>
        );
    });
}

function wrapHtml(html, items) {
    if (!html || !items?.length) {
        return html;
    }
    const pairs = longestLabelPairs(items);
    if (pairs.length === 0) {
        return html;
    }

    return String(html).split(/(<[^>]+>)/g).map((chunk) => {
        if (!chunk || chunk.startsWith('<')) {
            return chunk;
        }
        let out = chunk;
        pairs.forEach(({ label, item }) => {
            const re = new RegExp(`(${escapeRegExp(label)})`, 'gi');
            out = out.replace(
                re,
                `<button type="button" class="glossary-term" data-glossary-id="${item.id}">$1</button>`,
            );
        });

        return out;
    }).join('');
}

/**
 * SPEC §15.3: direction, content language, alignment and font preference are
 * settings on every text-capable block — never separate block types, and
 * never physical left/right.
 *
 * `textAlign: start|end|center` and `dir` are both logical, so the same block
 * renders correctly in either direction without a second code path, which is
 * what "Never duplicate block logic only because text direction is different"
 * asks for. `lang` is what lets a browser pick line-breaking and digit shaping
 * for Arabic or Thaana.
 */
const BLOCK_FONTS = {
    thaana: '"Noto Sans Thaana", "MV Faseyha", sans-serif',
    arabic: '"Noto Naskh Arabic", "Amiri", serif',
};

/**
 * SPEC §7: "Each course may have its own course language" and "**The platform
 * UI language and course content language are separate concepts.**"
 *
 * `auto` used to resolve to `undefined` — no `lang` attribute at all — so a
 * block inherited the page's, and the page's is the **UI** language. §7's own
 * example (a Dhivehi UI reading an Arabic course) therefore produced Arabic
 * text marked up as Dhivehi on every block an author had not tagged by hand,
 * which is the default. The two concepts were one.
 *
 * `auto` now falls back to the course's own language. `mixed` stays
 * unresolved on purpose: a course that is deliberately more than one language
 * has no single honest answer.
 *
 * Direction is untouched. §15.3 blesses `auto` for direction explicitly, and
 * the browser's first-strong-character heuristic is the right answer there.
 */
function blockTextProps(block, courseLanguage) {
    const s = block.settings || {};
    const direction = s.direction || 'auto';
    const align = s.align || 'start';
    const explicit = s.language && s.language !== 'auto' ? s.language : undefined;
    const language = explicit || courseLanguage || undefined;
    const style = { textAlign: align };
    if (BLOCK_FONTS[s.font]) {
        style.fontFamily = BLOCK_FONTS[s.font];
    }
    return { dir: direction, lang: language, style };
}

function BlockView({ block, mediaShowUrl, glossary = [], onSelectTerm = () => {}, courseLanguage = null, t = {} }) {
    const textProps = blockTextProps(block, courseLanguage);
    const src = block.data?.media_id ? mediaSrc(mediaShowUrl, block.data.media_id) : null;

    if (block.type === 'rich_text') {
        return (
            <article className="rounded-lg border bg-white p-4" {...textProps}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <div
                    className="prose text-sm"
                    dangerouslySetInnerHTML={{ __html: wrapHtml(block.data?.html || '', glossary) }}
                    onClick={(event) => {
                        const id = event.target?.getAttribute?.('data-glossary-id');
                        if (!id) {
                            return;
                        }
                        const item = glossary.find((row) => String(row.id) === String(id));
                        if (item) {
                            onSelectTerm(item);
                        }
                    }}
                />
            </article>
        );
    }
    if (block.type === 'instruction') {
        return (
            <aside className="rounded-lg border border-amber-200 bg-amber-50 p-4" {...textProps}>
                <p className="mb-1 text-xs uppercase tracking-wide text-amber-800">{t[`tone_${block.data?.tone || 'note'}`] || block.data?.tone || 'note'}</p>
                <p className="whitespace-pre-wrap text-sm">{wrapPlainText(block.data?.body, glossary, onSelectTerm)}</p>
            </aside>
        );
    }
    if (block.type === 'image' && src) {
        return (
            <figure className="rounded-lg border bg-white p-4" {...textProps}>
                {block.title && <figcaption className="mb-2 font-medium">{block.title}</figcaption>}
                <img src={src} alt={block.data?.original_name || block.title || ''} className="max-h-[32rem] w-full object-contain" />
            </figure>
        );
    }
    if (block.type === 'audio' && src) {
        return (
            <article className="rounded-lg border bg-white p-4" {...textProps}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <p className="mb-2 text-sm text-gray-600">{block.data?.original_name}</p>
                <audio className="w-full" controls src={src} preload="metadata" />
            </article>
        );
    }
    if (block.type === 'video') {
        if (block.data?.embed_url) {
            return (
                <article className="rounded-lg border bg-white p-4" {...textProps}>
                    {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                    <iframe
                        className="aspect-video w-full rounded border-0"
                        src={block.data.embed_url}
                        title={block.title || t.lesson_video || 'Lesson video'}
                        allow="fullscreen"
                    />
                </article>
            );
        }
        if (src) {
            return (
                <article className="rounded-lg border bg-white p-4" {...textProps}>
                    {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                    <video className="w-full" controls src={src} preload="metadata" />
                </article>
            );
        }
    }
    if (block.type === 'pdf' && src) {
        return (
            <article className="rounded-lg border bg-white p-4" {...textProps}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <iframe className="h-[36rem] w-full rounded border" src={src} title={block.data?.original_name || 'PDF'} />
                <a className="mt-2 inline-block text-sm text-[#7C2D37] hover:underline" href={src}>{block.data?.original_name || t.open_pdf || 'Open PDF'}</a>
            </article>
        );
    }
    if ((block.type === 'glossary' || block.type === 'term') && block.data?.entries?.length) {
        return (
            <article className="rounded-lg border bg-white p-4" {...textProps}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <dl className="space-y-2 text-sm">
                    {block.data.entries.map((entry, index) => (
                        <div key={`${entry.term}-${index}`}>
                            <dt className="font-medium">{entry.term}</dt>
                            <dd className="text-gray-700">{entry.definition}</dd>
                        </div>
                    ))}
                </dl>
            </article>
        );
    }
    if (block.type === 'dialogue' && block.data?.lines?.length) {
        return (
            <article className="rounded-lg border bg-white p-4" {...textProps}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <ol className="space-y-2 text-sm">
                    {block.data.lines.map((line, index) => (
                        <li key={`${line.speaker}-${index}`}>
                            <span className="font-medium">{line.speaker}:</span> {line.text}
                        </li>
                    ))}
                </ol>
            </article>
        );
    }
    if (block.type === 'flashcard' && block.data?.cards?.length) {
        // The card's own text settings. It spread a `textProps` that only
        // existed here, so a lesson with a flashcard threw on render and the
        // whole player went blank (slice CT7b).
        return <FlashcardView cards={block.data.cards} title={block.title} textProps={textProps} t={t} />;
    }
    if (block.type === 'download' && src) {
        return (
            <article className="rounded-lg border bg-white p-4" {...textProps}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <a className="text-sm text-[#7C2D37] hover:underline" href={src} download={block.data?.original_name || true}>
                    {block.data?.original_name || t.download || 'Download'}
                </a>
            </article>
        );
    }
    if (block.type === 'quiz_embed' || block.type === 'assignment_embed') {
        const quiz = block.type === 'quiz_embed';
        const id = quiz ? block.data?.quiz_id : block.data?.assignment_id;
        const kind = quiz ? (t.embed_quiz || 'Quiz') : (t.embed_assignment || 'Assignment');
        const label = block.data?.title || (id ? `${kind} ${id}` : '');
        return (
            <article className="rounded-lg border bg-white p-4" {...textProps}>
                <p className="mb-1 text-xs uppercase tracking-wide text-gray-500">{kind}</p>
                <p className="font-medium">{label || t.embedded_activity || 'Embedded activity'}</p>
                {block.data?.url && <a className="mt-2 inline-block text-sm text-[#7C2D37] hover:underline" href={block.data.url}>{t.open || 'Open'}</a>}
                {/* A quiz or assignment named by id alone has no player of its
                    own yet; the pupil is told so in words, not engine terms. */}
                {!block.data?.url && <p className="mt-2 text-sm text-gray-500">{t.embed_not_here || 'This cannot be opened from the lesson yet.'}</p>}
            </article>
        );
    }

    return (
        <article className="rounded-lg border bg-white p-4" {...textProps}>
            {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
            <p className="whitespace-pre-wrap text-sm">{wrapPlainText(block.data?.body, glossary, onSelectTerm)}</p>
        </article>
    );
}

/**
 * SPEC §22 "Glossary Media": audio, image, example audio, diagram — all through
 * the centralized media system.
 *
 * The four columns exist, are fillable, are validated against `media_files`,
 * and `GlossaryItem::toPayload()` sends every one of them to this page. Nothing
 * drew them, so a term's **pronunciation recording** — the thing an Arabic
 * vocabulary entry most needs, and the first item §22 lists — was invisible.
 * (Nothing uploaded one either; both ends were shut.)
 */
function TermMedia({ item, mediaShowUrl, t }) {
    const audio = item.audio_media_id;
    const exampleAudio = item.example_audio_media_id;
    const image = item.image_media_id;
    const diagram = item.diagram_media_id;

    if (!audio && !exampleAudio && !image && !diagram) {
        return null;
    }

    return (
        <div className="mt-2 space-y-2">
            {audio && (
                <label className="block text-xs text-gray-600">
                    {t.term_pronunciation || 'Pronunciation'}
                    <audio className="w-full" controls preload="none" src={mediaSrc(mediaShowUrl, audio)} />
                </label>
            )}
            {exampleAudio && (
                <label className="block text-xs text-gray-600">
                    {t.term_example || 'Example'}
                    <audio className="w-full" controls preload="none" src={mediaSrc(mediaShowUrl, exampleAudio)} />
                </label>
            )}
            {image && (
                <img className="max-h-56 w-full object-contain" src={mediaSrc(mediaShowUrl, image)} alt={item.term || ''} />
            )}
            {diagram && (
                <img className="max-h-56 w-full object-contain" src={mediaSrc(mediaShowUrl, diagram)} alt={(t.term_diagram || ':term diagram').replace(':term', () => item.term || '')} />
            )}
        </div>
    );
}

function FlashcardView({ cards, title, textProps, t }) {
    const [index, setIndex] = useState(0);
    const [showBack, setShowBack] = useState(false);
    const card = cards[index];

    return (
        <article className="rounded-lg border bg-white p-4" {...textProps}>
            {title && <h2 className="mb-2 font-medium">{title}</h2>}
            <button type="button" className="min-h-24 w-full rounded border bg-[#F3EBE0] p-4 text-start text-sm" onClick={() => setShowBack((value) => !value)}>
                {showBack ? card.back : card.front}
            </button>
            <div className="mt-2 flex items-center justify-between text-xs text-gray-500">
                <span>{index + 1} / {cards.length}</span>
                <span>{showBack ? (t.card_back || 'Back') : (t.card_front || 'Front')} · {t.card_flip || 'tap to flip'}</span>
            </div>
            {cards.length > 1 && (
                <div className="mt-2 flex gap-2">
                    <button type="button" className="btn-secondary" disabled={index === 0} onClick={() => { setIndex((value) => value - 1); setShowBack(false); }}>{t.card_previous || 'Previous'}</button>
                    <button type="button" className="btn-secondary" disabled={index === cards.length - 1} onClick={() => { setIndex((value) => value + 1); setShowBack(false); }}>{t.card_next || 'Next'}</button>
                </div>
            )}
        </article>
    );
}

export default function Show({
    snapshot,
    mediaShowUrl = '/catalog/media',
    canComplete = false,
    completeUrl = null,
    // SPEC §7. Null for a `mixed` course, or one whose language is not one the
    // browser can be told about — in which case `auto` stays the browser's own
    // per-block guess, which is the honest answer.
    courseLanguage = null,
}) {
    const t = usePage().props.i18n?.learn || {};
    const locale = usePage().props.locale || 'en';
    const glossary = snapshot.glossary || [];
    const [selected, setSelected] = useState(null);
    // SPEC §27's completion rules refuse a premature "Mark complete" server
    // side. The refusal has to be *readable*: without this the button simply
    // does nothing from the student's side, which is indistinguishable from a
    // broken page — and is exactly the failure the walk for this slice caught.
    const completionError = usePage().props.errors?.lesson;

    return (
        <AppShell title={snapshot.title || t.lesson || 'Lesson'}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-gray-600">{(t.published_revision || 'Published revision :number').replace(':number', snapshot.revision_number)}</p>
                {canComplete && completeUrl && (
                    <button type="button" className="btn-primary" onClick={() => router.post(completeUrl, {}, { preserveScroll: true })}>{t.mark_complete || 'Mark complete'}</button>
                )}
            </div>
            {completionError && (
                <p className="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{completionError}</p>
            )}
            {snapshot.description && <p className="mb-4">{wrapPlainText(snapshot.description, glossary, setSelected)}</p>}
            {selected && (
                <aside className="mb-4 rounded-lg border border-[#7C2D37]/30 bg-white p-4" dir="auto">
                    <p className="text-xs uppercase tracking-wide text-gray-500">{t.definition || 'Definition'}</p>
                    <p className="font-medium" dir={dirForLabel(selected.term, selected)}>{selected.term}</p>
                    {selected.transliteration && <p className="text-sm text-gray-600">{selected.transliteration}</p>}
                    {meaningFor(selected, locale) && <p className="mt-1 text-sm">{meaningFor(selected, locale)}</p>}
                    {descriptionFor(selected, locale) && <p className="mt-1 text-sm text-gray-700">{descriptionFor(selected, locale)}</p>}
                    {exampleFor(selected, locale) && (
                        <p className="mt-1 text-sm italic">
                            {exampleFor(selected, locale)}
                            {selected.example_translation && locale === 'en' ? ` — ${selected.example_translation}` : ''}
                        </p>
                    )}
                    <TermMedia item={selected} mediaShowUrl={mediaShowUrl} t={t} />
                    <button type="button" className="mt-2 text-xs text-[#7C2D37] hover:underline" onClick={() => setSelected(null)}>{t.close || 'Close'}</button>
                </aside>
            )}
            <div className="space-y-4">
                {(snapshot.blocks || []).map((block, index) => (
                    <BlockView
                        key={`${block.id}-${index}`}
                        block={block}
                        mediaShowUrl={mediaShowUrl}
                        glossary={glossary}
                        onSelectTerm={setSelected}
                        courseLanguage={courseLanguage}
                        t={t}
                    />
                ))}
            </div>
            {glossary.length > 0 && (
                <section className="mt-6 rounded-lg border bg-white p-4">
                    <h2 className="mb-3 font-medium">{t.glossary || 'Glossary'}</h2>
                    <dl className="space-y-3">
                        {glossary.map((item) => (
                            <div key={item.id}>
                                <dt className="font-medium">
                                    <button type="button" className="glossary-term" dir="auto" onClick={() => setSelected(item)}>{item.term}</button>
                                    {item.term_dv && <span className="ms-2 text-sm font-normal" dir="rtl">{item.term_dv}</span>}
                                    {item.term_ar && <span className="ms-2 text-sm font-normal" dir="rtl">{item.term_ar}</span>}
                                    {item.is_required && <span className="ms-2 text-xs uppercase text-amber-800">{t.required || 'required'}</span>}
                                </dt>
                                <dd className="text-sm text-gray-700" dir="auto">
                                    {meaningFor(item, locale) || descriptionFor(item, locale) || '—'}
                                    <TermMedia item={item} mediaShowUrl={mediaShowUrl} t={t} />
                                </dd>
                            </div>
                        ))}
                    </dl>
                </section>
            )}
        </AppShell>
    );
}
