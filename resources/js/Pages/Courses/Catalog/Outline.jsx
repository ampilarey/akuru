import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import FormErrors, { useRowRefusals } from '../../../Components/FormErrors';
import AppShell from '../../../Layouts/AppShell';

const MEDIA_TYPES = ['image', 'audio', 'video', 'pdf', 'download'];
const PAIR_TYPES = ['glossary', 'term', 'dialogue', 'flashcard'];
const EMBED_TYPES = ['quiz_embed', 'assignment_embed'];
// The block types the form offers, in its order. Their names are keys in
// the `teach` book (`block_<type>`, STATUS §5ok), so they read in Dhivehi and
// Arabic; the English is the fallback.
const BLOCK_TYPES = ['text', 'rich_text', 'instruction', 'image', 'audio', 'video', 'pdf', 'glossary', 'term', 'dialogue', 'flashcard', 'download', 'quiz_embed', 'assignment_embed'];
const BLOCK_NAMES = {
    text: 'Text',
    rich_text: 'Rich text',
    instruction: 'Instruction',
    image: 'Image',
    audio: 'Audio',
    video: 'Video',
    pdf: 'PDF',
    glossary: 'Glossary',
    term: 'Term',
    dialogue: 'Dialogue',
    flashcard: 'Flashcard',
    download: 'Download',
    quiz_embed: 'Quiz embed',
    assignment_embed: 'Assignment embed',
};
const blockType = (t, type) => t[`block_${type}`] || BLOCK_NAMES[type] || type;

function blockLabel(block) {
    return block.data?.body
        || block.data?.entries?.[0]?.term
        || block.data?.lines?.[0]?.text
        || block.data?.cards?.[0]?.front
        || block.data?.title
        || block.data?.original_name
        || block.data?.url
        || block.data?.embed_url
        || block.title
        || '—';
}

/**
 * SPEC §16: "Block reordering must use drag and drop" and "Reordering must
 * persist correctly." The backend and its route existed; nothing in the UI
 * ever called them, so the order a block was created in was the only order it
 * could ever have.
 *
 * Drag and drop is native HTML5 — no dependency added for it. Dragging is also
 * not reachable by keyboard and is awkward on touch, so each block keeps Up
 * and Down buttons that post the same payload. §16 asks for drag and drop; it
 * does not ask for drag and drop *only*.
 *
 * The list is optimistic: the new order paints immediately and is posted with
 * `preserveScroll`. If the server refuses it, the reload brings back the real
 * order and the effect below re-seeds from it.
 */
function LessonBlockList({ courseId, lesson, t, actOn }) {
    const blocks = lesson.blocks || [];
    const [order, setOrder] = useState(() => blocks.map((block) => block.id));
    // The dragged index lives in a ref, not state. `onDrop` has to read the
    // value `onDragStart` wrote, and a state write is only visible to a later
    // render — fine when a real pointer puts time between the two events,
    // but the drop silently did nothing when they arrived in one task. The
    // highlight is separate because that genuinely is a render concern.
    const draggingRef = useRef(null);
    const [dragging, setDragging] = useState(null);

    // Re-seed whenever the server sends a different set — after a save, a
    // delete, a duplicate, or a rejected reorder.
    const signature = blocks.map((block) => block.id).join(',');
    useEffect(() => {
        setOrder(blocks.map((block) => block.id));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [signature]);

    const persist = (ids) => {
        setOrder(ids);
        actOn(`lesson:${lesson.id}`, () => router.post(
            `/catalog/courses/${courseId}/blocks/reorder`,
            { lesson_id: lesson.id, block_ids: ids },
            { preserveScroll: true },
        ));
    };

    const moveTo = (fromIndex, toIndex) => {
        if (toIndex < 0 || toIndex >= order.length || fromIndex === toIndex) {
            return;
        }
        const next = [...order];
        const [moved] = next.splice(fromIndex, 1);
        next.splice(toIndex, 0, moved);
        persist(next);
    };

    if (blocks.length === 0) {
        return <p className="text-sm text-gray-500">{t.outline_no_blocks || 'No blocks yet.'}</p>;
    }

    const byId = new Map(blocks.map((block) => [block.id, block]));

    return (
        <ul className="space-y-1 text-sm text-gray-700">
            {order.map((id, index) => {
                const block = byId.get(id);
                if (!block) {
                    return null;
                }

                return (
                    <li
                        key={id}
                        draggable
                        aria-label={(t.outline_block_aria || 'Block :n of :total: :type').replace(':n', index + 1).replace(':total', order.length).replace(':type', blockType(t, block.type))}
                        onDragStart={() => {
                            draggingRef.current = index;
                            setDragging(index);
                        }}
                        onDragEnd={() => {
                            draggingRef.current = null;
                            setDragging(null);
                        }}
                        onDragOver={(e) => e.preventDefault()}
                        onDrop={(e) => {
                            e.preventDefault();
                            const from = draggingRef.current;
                            draggingRef.current = null;
                            setDragging(null);
                            if (from !== null) {
                                moveTo(from, index);
                            }
                        }}
                        className={`flex flex-wrap items-center justify-between gap-3 rounded border p-2 ${
                            dragging === index ? 'border-[#7C2D37] bg-[#F9F4EE]' : 'border-transparent'
                        }`}
                    >
                        <span className="flex items-center gap-2">
                            <span aria-hidden="true" className="cursor-grab text-gray-400">⠿</span>
                            <span>
                                {index + 1}. {blockType(t, block.type)}: {blockLabel(block)}
                                {block.is_required && (
                                    <span className="ms-2 text-xs uppercase text-amber-800">{t.outline_required_badge || 'required'}</span>
                                )}
                            </span>
                        </span>
                        <span className="flex items-center gap-2">
                            <button
                                type="button"
                                className="btn-secondary"
                                disabled={index === 0}
                                onClick={() => moveTo(index, index - 1)}
                            >
                                {t.outline_up || 'Up'}
                            </button>
                            <button
                                type="button"
                                className="btn-secondary"
                                disabled={index === order.length - 1}
                                onClick={() => moveTo(index, index + 1)}
                            >
                                {t.outline_down || 'Down'}
                            </button>
                            <button
                                type="button"
                                className="text-xs text-[#7C2D37]"
                                onClick={() => actOn(`lesson:${lesson.id}`, () => router.post(
                                    `/catalog/courses/${courseId}/blocks/${block.id}/duplicate`,
                                    {},
                                    { preserveScroll: true },
                                ))}
                            >
                                {t.outline_duplicate || 'Duplicate'}
                            </button>
                            <button
                                type="button"
                                className="text-xs text-red-700"
                                onClick={() => actOn(`lesson:${lesson.id}`, () => router.delete(`/catalog/courses/${courseId}/blocks/${block.id}`, { preserveScroll: true }))}
                            >
                                {t.outline_delete_draft || 'Delete draft'}
                            </button>
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

function LessonGlossaryForm({ courseId, lesson, glossaryItems, t, actOn }) {
    const form = useForm({
        glossary_item_id: glossaryItems[0]?.id || '',
        is_required: false,
    });
    const attachedIds = new Set((lesson.glossary || []).map((row) => row.id));
    const available = glossaryItems.filter((item) => !attachedIds.has(item.id));

    return (
        <div className="mt-3 rounded border bg-[#F9F4EE] p-3">
            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-gray-600">{t.outline_lesson_glossary || 'Lesson glossary'}</p>
            {(lesson.glossary || []).length === 0 && <p className="mb-2 text-xs text-gray-500">{t.outline_no_terms || 'No terms attached. Add a term in Glossary first.'}</p>}
            <ul className="mb-2 space-y-1 text-sm">
                {(lesson.glossary || []).map((item) => (
                    <li key={item.id} className="flex flex-wrap items-center justify-between gap-2">
                        <span>
                            <span dir="auto">{item.term}</span>
                            {item.term_ar && <span className="ms-2" dir="rtl">{item.term_ar}</span>}
                            {item.is_required && <span className="ms-2 text-xs uppercase text-amber-800">{t.outline_required_badge || 'required'}</span>}
                        </span>
                        <button
                            type="button"
                            className="text-xs text-red-700"
                            onClick={() => actOn(`lesson:${lesson.id}`, () => router.delete(`/catalog/courses/${courseId}/lessons/${lesson.id}/glossary/${item.id}`, { preserveScroll: true }))}
                        >
                            {t.outline_remove || 'Remove'}
                        </button>
                    </li>
                ))}
            </ul>
            {available.length > 0 && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        // Marks this form as the last thing acted on, so the
                        // lesson rows above do not also list its refusal.
                        actOn(`glossary:${lesson.id}`, () => form.post(`/catalog/courses/${courseId}/lessons/${lesson.id}/glossary`, { preserveScroll: true }));
                    }}
                    className="flex flex-wrap items-end gap-2"
                >
                    <select
                        className="form-input"
                        aria-label={t.outline_term || 'Term'}
                        value={form.data.glossary_item_id}
                        onChange={(e) => form.setData('glossary_item_id', e.target.value)}
                    >
                        {available.map((item) => (
                            <option key={item.id} value={item.id}>{item.term}{item.term_ar ? ` / ${item.term_ar}` : ''}</option>
                        ))}
                    </select>
                    <label className="flex items-center gap-1 text-xs">
                        <input
                            type="checkbox"
                            checked={!!form.data.is_required}
                            onChange={(e) => form.setData('is_required', e.target.checked)}
                        />
                        {t.outline_required || 'Required'}
                    </label>
                    <button type="submit" className="btn-secondary" disabled={form.processing}>{t.outline_attach_term || 'Attach term'}</button>
                    {form.errors.glossary_item_id && <span className="text-xs text-red-600">{form.errors.glossary_item_id}</span>}
                    <FormErrors errors={form.errors} except={['glossary_item_id']} className="w-full" />
                </form>
            )}
        </div>
    );
}

export default function Outline({ course, modules, glossaryItems = [], assessments = [], canPublish = false, t = {} }) {
    // §12 "Reorder modules". `position` was set once at creation and never
    // changed, so the order modules were typed in was the order students saw.
    // The server refuses a list that does not name every module exactly once,
    // so the whole order is always sent.
    const moveModule = (module, delta) => {
        const ids = modules.map((m) => m.id);
        const from = ids.indexOf(module.id);
        const to = from + delta;
        if (from < 0 || to < 0 || to >= ids.length) {
            return;
        }
        ids.splice(to, 0, ids.splice(from, 1)[0]);
        refusals.actOn(`module:${module.id}`, () => router.post(`/catalog/courses/${course.id}/modules/reorder`, { order: ids }, { preserveScroll: true }));
    };
    const moduleForm = useForm({ title: '' });
    const lessonForm = useForm({
        course_module_id: modules[0]?.id || '',
        title: '',
    });
    const blockForm = useForm({
        lesson_id: modules[0]?.lessons?.[0]?.id || '',
        type: 'text',
        body: '',
        tone: 'note',
        direction: 'auto',
        // SPEC §15.3's other text settings. Only direction was ever settable.
        align: 'start',
        language: 'auto',
        font: 'default',
        // SPEC §28.1 carries required/optional into the revision snapshot,
        // and §16 lets the author set it. The column and the Action always
        // supported it; no form ever sent it.
        is_required: false,
        embed_url: '',
        term: '',
        definition: '',
        entries_text: '',
        lines_text: '',
        cards_text: '',
        quiz_id: '',
        assignment_id: '',
        title: '',
        file: null,
    });
    // §12's "delete draft modules if safe" refusal names exactly what is in
    // the way ("still has 1 lesson, 2 content blocks"), and it was the only
    // refusal of this page's buttons that was ever rendered — once, above the
    // list. A refused reorder, unlock rule, completion rule, lesson publish or
    // block order said nothing (slice CT6b-2b). Each now says what came back
    // beside the module or lesson whose button was pressed.
    const refusals = useRowRefusals(moduleForm, lessonForm, blockForm);
    const isMedia = MEDIA_TYPES.includes(blockForm.data.type);
    const isPair = PAIR_TYPES.includes(blockForm.data.type);
    const isEmbed = EMBED_TYPES.includes(blockForm.data.type);

    return (
        <AppShell title={(t.outline_title || 'Outline — :course').replace(':course', course.title)}>
            <p className="mb-4 text-sm text-gray-600">
                {t.outline_workflow || 'Workflow:'} {t[`workflow_${course.workflow_status}`] || course.workflow_status}
                {' · '}
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/activities`}>{t.catalog_activities || 'Activities'}</a>
                {' · '}
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/assessments`}>{t.outline_assessments || 'Assessments'}</a>
                {' · '}
                <a className="text-[#7C2D37] hover:underline" href="/catalog/glossary">{t.outline_glossary || 'Glossary'}</a>
            </p>
            <div className="mb-4 grid gap-3 md:grid-cols-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        moduleForm.post(`/catalog/courses/${course.id}/modules`, { preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">{t.outline_add_module || 'Add module'}</p>
                    <input className="form-input mb-2" placeholder={t.outline_module_title || 'Module title'} aria-label={t.outline_module_title || 'Module title'} value={moduleForm.data.title} onChange={(e) => moduleForm.setData('title', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={moduleForm.processing}>{t.outline_save_module || 'Save module'}</button>
                    <FormErrors errors={moduleForm.errors} className="mt-1" />
                </form>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        // The select *shows* the first module as its fallback,
                        // but the form's state was captured when the page
                        // mounted — on a new course, before any module existed
                        // — so it still held ''. The post then failed
                        // `required` and, with no error rendered, the first
                        // lesson of every new course could not be saved from
                        // this screen at all: with one module there is nothing
                        // to re-select. Send what the select shows (the same
                        // fix the block form below already had; 1A audit,
                        // STATUS §5fg).
                        lessonForm.transform((data) => ({
                            ...data,
                            course_module_id: data.course_module_id || modules[0]?.id || '',
                        }));
                        lessonForm.post(`/catalog/courses/${course.id}/lessons`, { preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">{t.outline_add_lesson || 'Add lesson'}</p>
                    <select className="form-input mb-2" aria-label={t.outline_in_module || 'Module'} value={lessonForm.data.course_module_id || modules[0]?.id || ''} onChange={(e) => lessonForm.setData('course_module_id', e.target.value)}>
                        {modules.map((module) => <option key={module.id} value={module.id}>{module.title}</option>)}
                    </select>
                    <input className="form-input mb-2" placeholder={t.outline_lesson_title || 'Lesson title'} aria-label={t.outline_lesson_title || 'Lesson title'} value={lessonForm.data.title} onChange={(e) => lessonForm.setData('title', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={lessonForm.processing || modules.length === 0}>{t.outline_save_lesson || 'Save lesson'}</button>
                    {lessonForm.errors.title && <p className="mt-1 text-xs text-red-600">{lessonForm.errors.title}</p>}
                    {lessonForm.errors.course_module_id && <p className="mt-1 text-xs text-red-600">{lessonForm.errors.course_module_id}</p>}
                    <FormErrors errors={lessonForm.errors} except={['title', 'course_module_id']} className="mt-1" />
                </form>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        blockForm.transform((data) => ({
                            ...data,
                            lesson_id: data.lesson_id || modules.flatMap((module) => module.lessons)[0]?.id || '',
                        }));
                        blockForm.post(`/catalog/courses/${course.id}/blocks`, { preserveScroll: true, forceFormData: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">{t.outline_add_block || 'Add draft block'}</p>
                    <select className="form-input mb-2" aria-label={t.outline_in_lesson || 'Lesson'} value={blockForm.data.lesson_id || modules.flatMap((module) => module.lessons)[0]?.id || ''} onChange={(e) => blockForm.setData('lesson_id', e.target.value)}>
                        {modules.flatMap((module) => module.lessons).map((lesson) => <option key={lesson.id} value={lesson.id}>{lesson.title}</option>)}
                    </select>
                    <select className="form-input mb-2" aria-label={t.outline_block_type || 'Block type'} value={blockForm.data.type} onChange={(e) => blockForm.setData('type', e.target.value)}>
                        {BLOCK_TYPES.map((type) => <option key={type} value={type}>{blockType(t, type)}</option>)}
                    </select>
                    {/* SPEC §15.3: direction, content language, alignment and
                        font are settings on every text-capable block, never
                        separate block types. Alignment is start/end, not
                        left/right — physical values are silently wrong the
                        moment the same block is read the other way. */}
                    <div className="mb-2 grid gap-2 sm:grid-cols-2">
                        <select className="form-input" aria-label={t.outline_direction || 'Text direction'} value={blockForm.data.direction} onChange={(e) => blockForm.setData('direction', e.target.value)}>
                            <option value="auto">{t.outline_direction_auto || 'Direction auto'}</option>
                            <option value="ltr">{t.outline_direction_ltr || 'Left to right'}</option>
                            <option value="rtl">{t.outline_direction_rtl || 'Right to left'}</option>
                        </select>
                        <select className="form-input" aria-label={t.outline_alignment || 'Text alignment'} value={blockForm.data.align} onChange={(e) => blockForm.setData('align', e.target.value)}>
                            <option value="start">{t.outline_align_start || 'Align to start'}</option>
                            <option value="end">{t.outline_align_end || 'Align to end'}</option>
                            <option value="center">{t.outline_align_center || 'Align centre'}</option>
                        </select>
                        <select className="form-input" aria-label={t.outline_language || 'Content language'} value={blockForm.data.language} onChange={(e) => blockForm.setData('language', e.target.value)}>
                            <option value="auto">{t.outline_language_auto || 'Language of the lesson'}</option>
                            <option value="en">{t.outline_lang_en || 'English'}</option>
                            <option value="dv">{t.outline_lang_dv || 'Dhivehi'}</option>
                            <option value="ar">{t.outline_lang_ar || 'Arabic'}</option>
                        </select>
                        <select className="form-input" aria-label={t.outline_font || 'Font preference'} value={blockForm.data.font} onChange={(e) => blockForm.setData('font', e.target.value)}>
                            <option value="default">{t.outline_font_default || 'Default font'}</option>
                            <option value="thaana">{t.outline_font_thaana || 'Thaana face'}</option>
                            <option value="arabic">{t.outline_font_arabic || 'Arabic face'}</option>
                        </select>
                    </div>
                    {blockForm.data.type === 'instruction' && (
                        <select className="form-input mb-2" aria-label={t.outline_tone || 'Instruction type'} value={blockForm.data.tone} onChange={(e) => blockForm.setData('tone', e.target.value)}>
                            <option value="note">{t.outline_tone_note || 'Note'}</option>
                            <option value="tip">{t.outline_tone_tip || 'Tip'}</option>
                            <option value="warning">{t.outline_tone_warning || 'Warning'}</option>
                        </select>
                    )}
                    {blockForm.data.type === 'video' && (
                        <input className="form-input mb-2" placeholder={t.outline_video_url || 'YouTube or Vimeo URL (optional)'} aria-label={t.outline_video_url || 'YouTube or Vimeo URL (optional)'} value={blockForm.data.embed_url} onChange={(e) => blockForm.setData('embed_url', e.target.value)} />
                    )}
                    {(blockForm.data.type === 'glossary' || blockForm.data.type === 'term') && (
                        <>
                            <input className="form-input mb-2" placeholder={t.outline_term || 'Term'} aria-label={t.outline_term || 'Term'} value={blockForm.data.term} onChange={(e) => blockForm.setData('term', e.target.value)} />
                            <textarea className="form-input mb-2" placeholder={t.outline_definition || 'Definition'} aria-label={t.outline_definition || 'Definition'} value={blockForm.data.definition} onChange={(e) => blockForm.setData('definition', e.target.value)} />
                            <textarea className="form-input mb-2" placeholder={t.outline_more_entries || 'More entries: term | definition'} aria-label={t.outline_more_entries || 'More entries: term | definition'} value={blockForm.data.entries_text} onChange={(e) => blockForm.setData('entries_text', e.target.value)} />
                        </>
                    )}
                    {blockForm.data.type === 'dialogue' && (
                        <textarea className="form-input mb-2" placeholder={t.outline_dialogue_lines || 'speaker | line'} aria-label={t.outline_dialogue_lines || 'speaker | line'} value={blockForm.data.lines_text} onChange={(e) => blockForm.setData('lines_text', e.target.value)} />
                    )}
                    {blockForm.data.type === 'flashcard' && (
                        <textarea className="form-input mb-2" placeholder={t.outline_flashcards || 'front | back'} aria-label={t.outline_flashcards || 'front | back'} value={blockForm.data.cards_text} onChange={(e) => blockForm.setData('cards_text', e.target.value)} />
                    )}
                    {isEmbed && (
                        <>
                            <input className="form-input mb-2" placeholder={t.outline_embed_title || 'Title (optional)'} aria-label={t.outline_embed_title || 'Title (optional)'} value={blockForm.data.title} onChange={(e) => blockForm.setData('title', e.target.value)} />
                            <input
                                className="form-input mb-2"
                                placeholder={blockForm.data.type === 'quiz_embed' ? (t.outline_quiz_id || 'Quiz id (optional)') : (t.outline_assignment_id || 'Assignment id (optional)')}
                                aria-label={blockForm.data.type === 'quiz_embed' ? (t.outline_quiz_id || 'Quiz id (optional)') : (t.outline_assignment_id || 'Assignment id (optional)')}
                                value={blockForm.data.type === 'quiz_embed' ? blockForm.data.quiz_id : blockForm.data.assignment_id}
                                onChange={(e) => blockForm.setData(blockForm.data.type === 'quiz_embed' ? 'quiz_id' : 'assignment_id', e.target.value)}
                            />
                            <input className="form-input mb-2" placeholder={t.outline_embed_url || 'https://… (optional)'} aria-label={t.outline_embed_url || 'https://… (optional)'} value={blockForm.data.embed_url} onChange={(e) => blockForm.setData('embed_url', e.target.value)} />
                        </>
                    )}
                    {isMedia ? (
                        <input
                            className="form-input mb-2"
                            type="file"
                            aria-label={t.outline_media_file || 'File'}
                            accept={
                                blockForm.data.type === 'image' ? 'image/*'
                                    : blockForm.data.type === 'audio' ? 'audio/*'
                                        : blockForm.data.type === 'video' ? 'video/*'
                                            : blockForm.data.type === 'download' ? '.pdf,.zip,.txt,.doc,.docx,application/pdf,application/zip,text/plain'
                                                : 'application/pdf'
                            }
                            onChange={(e) => blockForm.setData('file', e.target.files?.[0] || null)}
                        />
                    ) : !isPair && !isEmbed ? (
                        <textarea className="form-input mb-2" placeholder={t.outline_block_content || 'Block content'} aria-label={t.outline_block_content || 'Block content'} value={blockForm.data.body} onChange={(e) => blockForm.setData('body', e.target.value)} />
                    ) : null}
                    <label className="mb-2 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={!!blockForm.data.is_required}
                            onChange={(e) => blockForm.setData('is_required', e.target.checked)}
                        />
                        {t.outline_required_to_complete || 'Required to complete the lesson'}
                    </label>
                    <button type="submit" className="btn-primary" disabled={blockForm.processing}>{t.outline_save_block || 'Save block'}</button>
                    {blockForm.errors.data && <p className="mt-1 text-xs text-red-600">{blockForm.errors.data}</p>}
                    {blockForm.errors.type && <p className="mt-1 text-xs text-red-600">{blockForm.errors.type}</p>}
                    {blockForm.errors.file && <p className="mt-1 text-xs text-red-600">{blockForm.errors.file}</p>}
                    <FormErrors errors={blockForm.errors} except={['data', 'type', 'file']} className="mt-1" />
                </form>
            </div>
            {/* Whatever came back before any button here was pressed — the
                page opened straight onto a refusal. */}
            <FormErrors errors={refusals.unplaced} except={['glossary_item_id']} className="mb-3 rounded border border-red-200 bg-red-50 py-2 pe-3" />
            <div className="space-y-4">
                {modules.map((module) => (
                    <section key={module.id} className="rounded-lg border bg-white p-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-medium">
                                {module.title}
                                <span className="ms-2 text-xs uppercase tracking-wide text-gray-500">{t[`status_${module.status || 'draft'}`] || module.status || 'draft'}</span>
                            </h2>
                            {/* SPEC §12 Module Management. Only create and
                                delete existed: no edit, no reorder, and no way
                                to change a status that nothing ever wrote. */}
                            <div className="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    aria-label={(t.outline_rename_aria || 'Rename module :title').replace(':title', module.title)}
                                    className="text-xs text-[#7C2D37] hover:underline"
                                    onClick={() => {
                                        const title = window.prompt(t.outline_module_title || 'Module title', module.title);
                                        if (title === null || title.trim() === '') {
                                            return;
                                        }
                                        refusals.actOn(`module:${module.id}`, () => router.put(
                                            `/catalog/courses/${course.id}/modules/${module.id}`,
                                            { title: title.trim(), description: module.description || '' },
                                            { preserveScroll: true },
                                        ));
                                    }}
                                >
                                    {t.outline_rename || 'Rename'}
                                </button>
                                <button
                                    type="button"
                                    className="text-xs text-[#7C2D37] hover:underline disabled:text-gray-400"
                                    disabled={modules.indexOf(module) === 0}
                                    onClick={() => moveModule(module, -1)}
                                >
                                    {t.outline_move_up || '↑ Move up'}
                                </button>
                                <button
                                    type="button"
                                    className="text-xs text-[#7C2D37] hover:underline disabled:text-gray-400"
                                    disabled={modules.indexOf(module) === modules.length - 1}
                                    onClick={() => moveModule(module, 1)}
                                >
                                    {t.outline_move_down || '↓ Move down'}
                                </button>
                                {/* Publishing a module needs `courses.publish`; offered
                                    to an author without it, the button was a bare
                                    "Forbidden" (slice CT6b-2b). */}
                                {canPublish && (
                                    <button
                                        type="button"
                                        aria-label={(module.status === 'published' ? (t.outline_unpublish_aria || 'Unpublish module :title') : (t.outline_publish_aria || 'Publish module :title')).replace(':title', module.title)}
                                        className="text-xs text-[#7C2D37] hover:underline"
                                        onClick={() => refusals.actOn(`module:${module.id}`, () => router.post(
                                            `/catalog/courses/${course.id}/modules/${module.id}/status`,
                                            { status: module.status === 'published' ? 'draft' : 'published' },
                                            { preserveScroll: true },
                                        ))}
                                    >
                                        {module.status === 'published' ? (t.outline_unpublish || 'Unpublish') : (t.outline_publish || 'Publish')}
                                    </button>
                                )}
                            </div>
                            {/* §12 "Delete draft modules if safe". Offered only
                                when the module is empty: the server refuses
                                otherwise, and a button that always fails is
                                worse than no button. */}
                            {module.lessons.length === 0 && (
                                <button
                                    type="button"
                                    className="text-xs text-red-700"
                                    onClick={() => {
                                        if (window.confirm((t.outline_delete_module_confirm || 'Delete the empty module ":title"?').replace(':title', module.title))) {
                                            refusals.actOn(`module:${module.id}`, () => router.delete(`/catalog/courses/${course.id}/modules/${module.id}`, { preserveScroll: true }));
                                        }
                                    }}
                                >
                                    {t.outline_delete_module || 'Delete module'}
                                </button>
                            )}
                        </div>
                        <FormErrors errors={refusals.errorsFor(`module:${module.id}`)} className="mb-2 rounded border border-red-200 bg-red-50 py-2 pe-3" />
                        {module.lessons.length === 0 && <p className="text-sm text-gray-500">{t.outline_no_lessons || 'No lessons yet.'}</p>}
                        {module.lessons.map((lesson) => (
                            <div key={lesson.id} className="mb-3 border-t pt-3">
                                <div className="mb-2 flex flex-wrap items-center gap-3">
                                    <p className="font-medium">{lesson.title}</p>
                                    <span className="text-xs uppercase text-gray-500">{t[`status_${lesson.status}`] || lesson.status}{lesson.revision_number ? ` r${lesson.revision_number}` : ''}{lesson.is_preview ? ` ${t.outline_preview_badge || 'preview'}` : ''}</span>
                                    <button type="button" className="btn-secondary" onClick={() => refusals.actOn(`lesson:${lesson.id}`, () => router.post(`/catalog/courses/${course.id}/lessons/${lesson.id}/preview`, {}, { preserveScroll: true }))}>{lesson.is_preview ? (t.outline_unmark_preview || 'Unmark preview') : (t.outline_mark_preview || 'Mark preview')}</button>
                                    {/* SPEC §13 Lesson Management: "Set completion rules".
                                        Only the two rules the engine enforces are
                                        offered — §26's lesson, that a rule an admin
                                        can pick and the engine ignores is worse than
                                        no rule at all. */}
                                    {/* SPEC §26 "Pass quiz first" at lesson level
                                        (§13's "Unlock rule"). Only the rules the
                                        evaluator enforces are offered. */}
                                    <select
                                        className="form-input ms-2 inline-block w-auto text-xs"
                                        aria-label={(t.outline_unlock_aria || 'Unlock rule for :title').replace(':title', lesson.title)}
                                        value={lesson.unlock_rule?.assessment_id ? String(lesson.unlock_rule.assessment_id) : ''}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            refusals.actOn(`lesson:${lesson.id}`, () => router.post(
                                                `/catalog/courses/${course.id}/lessons/${lesson.id}/unlock-rule`,
                                                value
                                                    ? { mode: 'pass_assessment', assessment_id: value }
                                                    : { mode: '' },
                                                { preserveScroll: true },
                                            ));
                                        }}
                                    >
                                        <option value="">{t.outline_unlock_course_rule || 'Unlocks with the course rule'}</option>
                                        {assessments.map((a) => (
                                            <option key={a.id} value={a.id}>{(t.outline_unlock_needs || 'Needs a pass in: :title').replace(':title', a.title)}</option>
                                        ))}
                                    </select>
                                    <select
                                        className="form-input ms-2 inline-block w-auto text-xs"
                                        aria-label={t.outline_completion_rule || 'Completion rule'}
                                        value={lesson.completion_rule || 'click'}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            refusals.actOn(`lesson:${lesson.id}`, () => router.post(
                                                `/catalog/courses/${course.id}/lessons/${lesson.id}/completion-rule`,
                                                { completion_rule: value },
                                                { preserveScroll: true },
                                            ));
                                        }}
                                    >
                                        <option value="click">{t.outline_completes_click || 'Completes on click'}</option>
                                        <option value="required_activities">{t.outline_completes_required || 'Requires all required activities'}</option>
                                    </select>
                                    <button type="button" className="btn-secondary" onClick={() => refusals.actOn(`lesson:${lesson.id}`, () => router.post(`/catalog/courses/${course.id}/lessons/${lesson.id}/publish`, {}, { preserveScroll: true }))}>{t.outline_publish || 'Publish'}</button>
                                    {lesson.current_revision_id && (
                                        <a className="text-sm text-[#7C2D37] hover:underline" href={`/catalog/player/${lesson.id}`}>{t.outline_open_player || 'Open player'}</a>
                                    )}
                                </div>
                                <FormErrors errors={refusals.errorsFor(`lesson:${lesson.id}`)} className="mb-2 rounded border border-red-200 bg-red-50 py-2 pe-3" />
                                <LessonBlockList courseId={course.id} lesson={lesson} t={t} actOn={refusals.actOn} />
                                <LessonGlossaryForm courseId={course.id} lesson={lesson} glossaryItems={glossaryItems} t={t} actOn={refusals.actOn} />
                            </div>
                        ))}
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
