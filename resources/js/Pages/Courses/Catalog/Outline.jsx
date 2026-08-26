import { router, useForm } from '@inertiajs/react';
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
                        })).post(`/catalog/courses/${course.id}/blocks`, { preserveScroll: true, forceFormData: true });
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
                    <select className="form-input mb-2" value={blockForm.data.direction} onChange={(e) => blockForm.setData('direction', e.target.value)}>
                        <option value="auto">Direction auto</option>
                        <option value="ltr">LTR</option>
                        <option value="rtl">RTL</option>
                    </select>
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
                    <button type="submit" className="btn-primary" disabled={blockForm.processing}>Save block</button>
                    {blockForm.errors.data && <p className="mt-1 text-xs text-red-600">{blockForm.errors.data}</p>}
                    {blockForm.errors.type && <p className="mt-1 text-xs text-red-600">{blockForm.errors.type}</p>}
                    {blockForm.errors.file && <p className="mt-1 text-xs text-red-600">{blockForm.errors.file}</p>}
                </form>
            </div>
            <div className="space-y-4">
                {modules.map((module) => (
                    <section key={module.id} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-2 font-medium">{module.title}</h2>
                        {module.lessons.length === 0 && <p className="text-sm text-gray-500">No lessons yet.</p>}
                        {module.lessons.map((lesson) => (
                            <div key={lesson.id} className="mb-3 border-t pt-3">
                                <div className="mb-2 flex flex-wrap items-center gap-3">
                                    <p className="font-medium">{lesson.title}</p>
                                    <span className="text-xs uppercase text-gray-500">{lesson.status}{lesson.revision_number ? ` r${lesson.revision_number}` : ''}{lesson.is_preview ? ' preview' : ''}</span>
                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${course.id}/lessons/${lesson.id}/preview`)}>{lesson.is_preview ? 'Unmark preview' : 'Mark preview'}</button>
                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${course.id}/lessons/${lesson.id}/publish`)}>Publish</button>
                                    {lesson.current_revision_id && (
                                        <a className="text-sm text-[#7C2D37] hover:underline" href={`/catalog/player/${lesson.id}`}>Open player</a>
                                    )}
                                </div>
                                <ul className="text-sm text-gray-700">
                                    {lesson.blocks.map((block) => (
                                        <li key={block.id} className="flex justify-between gap-3">
                                            <span>{block.type}: {blockLabel(block)}</span>
                                            <button type="button" className="text-xs text-red-700" onClick={() => router.delete(`/catalog/courses/${course.id}/blocks/${block.id}`)}>Delete draft</button>
                                        </li>
                                    ))}
                                </ul>
                                <LessonGlossaryForm courseId={course.id} lesson={lesson} glossaryItems={glossaryItems} />
                            </div>
                        ))}
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
