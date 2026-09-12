import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import AppShell from '../../../Layouts/AppShell';

const MEDIA_TYPES = ['image', 'audio', 'video', 'pdf', 'download'];
const PAIR_TYPES = ['glossary', 'term', 'dialogue', 'flashcard'];
const EMBED_TYPES = ['quiz_embed', 'assignment_embed'];

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
function LessonBlockList({ courseId, lesson }) {
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
        router.post(
            `/catalog/courses/${courseId}/blocks/reorder`,
            { lesson_id: lesson.id, block_ids: ids },
            { preserveScroll: true },
        );
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
        return <p className="text-sm text-gray-500">No blocks yet.</p>;
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
                        aria-label={`Block ${index + 1} of ${order.length}: ${block.type}`}
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
                                {index + 1}. {block.type}: {blockLabel(block)}
                                {block.is_required && (
                                    <span className="ms-2 text-xs uppercase text-amber-800">required</span>
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
                                Up
                            </button>
                            <button
                                type="button"
                                className="btn-secondary"
                                disabled={index === order.length - 1}
                                onClick={() => moveTo(index, index + 1)}
                            >
                                Down
                            </button>
                            <button
                                type="button"
                                className="text-xs text-[#7C2D37]"
                                onClick={() => router.post(
                                    `/catalog/courses/${courseId}/blocks/${block.id}/duplicate`,
                                    {},
                                    { preserveScroll: true },
                                )}
                            >
                                Duplicate
                            </button>
                            <button
                                type="button"
                                className="text-xs text-red-700"
                                onClick={() => router.delete(`/catalog/courses/${courseId}/blocks/${block.id}`, { preserveScroll: true })}
                            >
                                Delete draft
                            </button>
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}

function LessonGlossaryForm({ courseId, lesson, glossaryItems }) {
    const form = useForm({
        glossary_item_id: glossaryItems[0]?.id || '',
        is_required: false,
    });
    const attachedIds = new Set((lesson.glossary || []).map((row) => row.id));
    const available = glossaryItems.filter((item) => !attachedIds.has(item.id));

    return (
        <div className="mt-3 rounded border bg-[#F9F4EE] p-3">
            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-gray-600">Lesson glossary</p>
            {(lesson.glossary || []).length === 0 && <p className="mb-2 text-xs text-gray-500">No terms attached. Add a term in Glossary first.</p>}
            <ul className="mb-2 space-y-1 text-sm">
                {(lesson.glossary || []).map((item) => (
                    <li key={item.id} className="flex flex-wrap items-center justify-between gap-2">
                        <span>
                            <span dir="auto">{item.term}</span>
                            {item.term_ar && <span className="ms-2" dir="rtl">{item.term_ar}</span>}
                            {item.is_required && <span className="ms-2 text-xs uppercase text-amber-800">required</span>}
                        </span>
                        <button
                            type="button"
                            className="text-xs text-red-700"
                            onClick={() => router.delete(`/catalog/courses/${courseId}/lessons/${lesson.id}/glossary/${item.id}`)}
                        >
                            Remove
                        </button>
                    </li>
                ))}
            </ul>
            {available.length > 0 && (
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(`/catalog/courses/${courseId}/lessons/${lesson.id}/glossary`, { preserveScroll: true });
                    }}
                    className="flex flex-wrap items-end gap-2"
                >
                    <select
                        className="form-input"
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
                        Required
                    </label>
                    <button type="submit" className="btn-secondary" disabled={form.processing}>Attach term</button>
                    {form.errors.glossary_item_id && <span className="text-xs text-red-600">{form.errors.glossary_item_id}</span>}
                </form>
            )}
        </div>
    );
}

export default function Outline({ course, modules, glossaryItems = [] }) {
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
    const isMedia = MEDIA_TYPES.includes(blockForm.data.type);
    const isPair = PAIR_TYPES.includes(blockForm.data.type);
    const isEmbed = EMBED_TYPES.includes(blockForm.data.type);

    return (
        <AppShell title={`Outline — ${course.title}`}>
            <p className="mb-4 text-sm text-gray-600">
                Workflow: {course.workflow_status}
                {' · '}
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/activities`}>Activities</a>
                {' · '}
                <a className="text-[#7C2D37] hover:underline" href={`/catalog/courses/${course.id}/assessments`}>Assessments</a>
                {' · '}
                <a className="text-[#7C2D37] hover:underline" href="/catalog/glossary">Glossary</a>
            </p>
            <div className="mb-4 grid gap-3 md:grid-cols-3">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        moduleForm.post(`/catalog/courses/${course.id}/modules`, { preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">Add module</p>
                    <input className="form-input mb-2" placeholder="Module title" value={moduleForm.data.title} onChange={(e) => moduleForm.setData('title', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={moduleForm.processing}>Save module</button>
                </form>
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        lessonForm.post(`/catalog/courses/${course.id}/lessons`, { preserveScroll: true });
                    }}
                    className="rounded-lg border bg-white p-4"
                >
                    <p className="mb-2 text-sm font-medium">Add lesson</p>
                    <select className="form-input mb-2" value={lessonForm.data.course_module_id || modules[0]?.id || ''} onChange={(e) => lessonForm.setData('course_module_id', e.target.value)}>
                        {modules.map((module) => <option key={module.id} value={module.id}>{module.title}</option>)}
                    </select>
                    <input className="form-input mb-2" placeholder="Lesson title" value={lessonForm.data.title} onChange={(e) => lessonForm.setData('title', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={lessonForm.processing || modules.length === 0}>Save lesson</button>
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
                    <p className="mb-2 text-sm font-medium">Add draft block</p>
                    <select className="form-input mb-2" value={blockForm.data.lesson_id || modules.flatMap((module) => module.lessons)[0]?.id || ''} onChange={(e) => blockForm.setData('lesson_id', e.target.value)}>
                        {modules.flatMap((module) => module.lessons).map((lesson) => <option key={lesson.id} value={lesson.id}>{lesson.title}</option>)}
                    </select>
                    <select className="form-input mb-2" value={blockForm.data.type} onChange={(e) => blockForm.setData('type', e.target.value)}>
                        <option value="text">Text</option>
                        <option value="rich_text">Rich text</option>
                        <option value="instruction">Instruction</option>
                        <option value="image">Image</option>
                        <option value="audio">Audio</option>
                        <option value="video">Video</option>
                        <option value="pdf">PDF</option>
                        <option value="glossary">Glossary</option>
                        <option value="term">Term</option>
                        <option value="dialogue">Dialogue</option>
                        <option value="flashcard">Flashcard</option>
                        <option value="download">Download</option>
                        <option value="quiz_embed">Quiz embed</option>
                        <option value="assignment_embed">Assignment embed</option>
                    </select>
                    {/* SPEC §15.3: direction, content language, alignment and
                        font are settings on every text-capable block, never
                        separate block types. Alignment is start/end, not
                        left/right — physical values are silently wrong the
                        moment the same block is read the other way. */}
                    <div className="mb-2 grid gap-2 sm:grid-cols-2">
                        <select className="form-input" aria-label="Text direction" value={blockForm.data.direction} onChange={(e) => blockForm.setData('direction', e.target.value)}>
                            <option value="auto">Direction auto</option>
                            <option value="ltr">LTR</option>
                            <option value="rtl">RTL</option>
                        </select>
                        <select className="form-input" aria-label="Text alignment" value={blockForm.data.align} onChange={(e) => blockForm.setData('align', e.target.value)}>
                            <option value="start">Align to start</option>
                            <option value="end">Align to end</option>
                            <option value="center">Align centre</option>
                        </select>
                        <select className="form-input" aria-label="Content language" value={blockForm.data.language} onChange={(e) => blockForm.setData('language', e.target.value)}>
                            <option value="auto">Language of the lesson</option>
                            <option value="en">English</option>
                            <option value="dv">Dhivehi</option>
                            <option value="ar">Arabic</option>
                        </select>
                        <select className="form-input" aria-label="Font preference" value={blockForm.data.font} onChange={(e) => blockForm.setData('font', e.target.value)}>
                            <option value="default">Default font</option>
                            <option value="thaana">Thaana face</option>
                            <option value="arabic">Arabic face</option>
                        </select>
                    </div>
                    {blockForm.data.type === 'instruction' && (
                        <select className="form-input mb-2" value={blockForm.data.tone} onChange={(e) => blockForm.setData('tone', e.target.value)}>
                            <option value="note">Note</option>
                            <option value="tip">Tip</option>
                            <option value="warning">Warning</option>
                        </select>
                    )}
                    {blockForm.data.type === 'video' && (
                        <input className="form-input mb-2" placeholder="YouTube or Vimeo URL (optional)" value={blockForm.data.embed_url} onChange={(e) => blockForm.setData('embed_url', e.target.value)} />
                    )}
                    {(blockForm.data.type === 'glossary' || blockForm.data.type === 'term') && (
                        <>
                            <input className="form-input mb-2" placeholder="Term" value={blockForm.data.term} onChange={(e) => blockForm.setData('term', e.target.value)} />
                            <textarea className="form-input mb-2" placeholder="Definition" value={blockForm.data.definition} onChange={(e) => blockForm.setData('definition', e.target.value)} />
                            <textarea className="form-input mb-2" placeholder="More entries: term | definition" value={blockForm.data.entries_text} onChange={(e) => blockForm.setData('entries_text', e.target.value)} />
                        </>
                    )}
                    {blockForm.data.type === 'dialogue' && (
                        <textarea className="form-input mb-2" placeholder="speaker | line" value={blockForm.data.lines_text} onChange={(e) => blockForm.setData('lines_text', e.target.value)} />
                    )}
                    {blockForm.data.type === 'flashcard' && (
                        <textarea className="form-input mb-2" placeholder="front | back" value={blockForm.data.cards_text} onChange={(e) => blockForm.setData('cards_text', e.target.value)} />
                    )}
                    {isEmbed && (
                        <>
                            <input className="form-input mb-2" placeholder="Title (optional)" value={blockForm.data.title} onChange={(e) => blockForm.setData('title', e.target.value)} />
                            <input
                                className="form-input mb-2"
                                placeholder={blockForm.data.type === 'quiz_embed' ? 'Quiz id (optional)' : 'Assignment id (optional)'}
                                value={blockForm.data.type === 'quiz_embed' ? blockForm.data.quiz_id : blockForm.data.assignment_id}
                                onChange={(e) => blockForm.setData(blockForm.data.type === 'quiz_embed' ? 'quiz_id' : 'assignment_id', e.target.value)}
                            />
                            <input className="form-input mb-2" placeholder="https://… (optional)" value={blockForm.data.embed_url} onChange={(e) => blockForm.setData('embed_url', e.target.value)} />
                        </>
                    )}
                    {isMedia ? (
                        <input
                            className="form-input mb-2"
                            type="file"
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
                        <textarea className="form-input mb-2" placeholder="Block content" value={blockForm.data.body} onChange={(e) => blockForm.setData('body', e.target.value)} />
                    ) : null}
                    <label className="mb-2 flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={!!blockForm.data.is_required}
                            onChange={(e) => blockForm.setData('is_required', e.target.checked)}
                        />
                        Required to complete the lesson
                    </label>
                    <button type="submit" className="btn-primary" disabled={blockForm.processing}>Save block</button>
                    {blockForm.errors.data && <p className="mt-1 text-xs text-red-600">{blockForm.errors.data}</p>}
                    {blockForm.errors.type && <p className="mt-1 text-xs text-red-600">{blockForm.errors.type}</p>}
                    {blockForm.errors.file && <p className="mt-1 text-xs text-red-600">{blockForm.errors.file}</p>}
                </form>
            </div>
            <div className="space-y-4">
                {modules.map((module) => (
                    <section key={module.id} className="rounded-lg border bg-white p-4">
                        <div className="mb-2 flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-medium">{module.title}</h2>
                            {/* §12 "Delete draft modules if safe". Offered only
                                when the module is empty: the server refuses
                                otherwise, and a button that always fails is
                                worse than no button. */}
                            {module.lessons.length === 0 && (
                                <button
                                    type="button"
                                    className="text-xs text-red-700"
                                    onClick={() => {
                                        if (window.confirm(`Delete the empty module "${module.title}"?`)) {
                                            router.delete(`/catalog/courses/${course.id}/modules/${module.id}`);
                                        }
                                    }}
                                >
                                    Delete module
                                </button>
                            )}
                        </div>
                        {module.lessons.length === 0 && <p className="text-sm text-gray-500">No lessons yet.</p>}
                        {module.lessons.map((lesson) => (
                            <div key={lesson.id} className="mb-3 border-t pt-3">
                                <div className="mb-2 flex flex-wrap items-center gap-3">
                                    <p className="font-medium">{lesson.title}</p>
                                    <span className="text-xs uppercase text-gray-500">{lesson.status}{lesson.revision_number ? ` r${lesson.revision_number}` : ''}{lesson.is_preview ? ' preview' : ''}</span>
                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${course.id}/lessons/${lesson.id}/preview`)}>{lesson.is_preview ? 'Unmark preview' : 'Mark preview'}</button>
                                    {/* SPEC §13 Lesson Management: "Set completion rules".
                                        Only the two rules the engine enforces are
                                        offered — §26's lesson, that a rule an admin
                                        can pick and the engine ignores is worse than
                                        no rule at all. */}
                                    <select
                                        className="form-input ms-2 inline-block w-auto text-xs"
                                        aria-label="Completion rule"
                                        value={lesson.completion_rule || 'click'}
                                        onChange={(e) => router.post(
                                            `/catalog/courses/${course.id}/lessons/${lesson.id}/completion-rule`,
                                            { completion_rule: e.target.value },
                                            { preserveScroll: true },
                                        )}
                                    >
                                        <option value="click">Completes on click</option>
                                        <option value="required_activities">Requires all required activities</option>
                                    </select>
                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${course.id}/lessons/${lesson.id}/publish`)}>Publish</button>
                                    {lesson.current_revision_id && (
                                        <a className="text-sm text-[#7C2D37] hover:underline" href={`/catalog/player/${lesson.id}`}>Open player</a>
                                    )}
                                </div>
                                <LessonBlockList courseId={course.id} lesson={lesson} />
                                <LessonGlossaryForm courseId={course.id} lesson={lesson} glossaryItems={glossaryItems} />
                            </div>
                        ))}
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
