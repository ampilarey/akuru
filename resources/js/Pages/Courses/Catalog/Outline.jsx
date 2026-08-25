import { router, useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';

export default function Outline({ course, modules }) {
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
    });

    return (
        <AppShell title={`Outline — ${course.title}`}>
            <p className="mb-4 text-sm text-gray-600">Workflow: {course.workflow_status}</p>
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
                        })).post(`/catalog/courses/${course.id}/blocks`, { preserveScroll: true });
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
                    <textarea className="form-input mb-2" placeholder="Block content" value={blockForm.data.body} onChange={(e) => blockForm.setData('body', e.target.value)} />
                    <button type="submit" className="btn-primary" disabled={blockForm.processing}>Save block</button>
                    {blockForm.errors.data && <p className="mt-1 text-xs text-red-600">{blockForm.errors.data}</p>}
                    {blockForm.errors.type && <p className="mt-1 text-xs text-red-600">{blockForm.errors.type}</p>}
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
                                    <span className="text-xs uppercase text-gray-500">{lesson.status}{lesson.revision_number ? ` r${lesson.revision_number}` : ''}</span>
                                    <button type="button" className="btn-secondary" onClick={() => router.post(`/catalog/courses/${course.id}/lessons/${lesson.id}/publish`)}>Publish</button>
                                    {lesson.current_revision_id && (
                                        <a className="text-sm text-[#7C2D37] hover:underline" href={`/catalog/player/${lesson.id}`}>Open player</a>
                                    )}
                                </div>
                                <ul className="text-sm text-gray-700">
                                    {lesson.blocks.map((block) => (
                                        <li key={block.id} className="flex justify-between gap-3">
                                            <span>{block.type}: {block.data?.body || block.title || '—'}</span>
                                            <button type="button" className="text-xs text-red-700" onClick={() => router.delete(`/catalog/courses/${course.id}/blocks/${block.id}`)}>Delete draft</button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
