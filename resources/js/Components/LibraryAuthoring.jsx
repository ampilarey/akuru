/**
 * R1 (RESEARCH_ARTICLES_PLAN): two controls the writer's editor and the
 * office's form share.
 *
 * - DeliveryChoice: how readers get a research item or article — read online,
 *   download the PDF, or both (the owner's decision D1). Books stay in the
 *   protected reader, so the choice is not shown for them.
 * - TeacherAuthors: the institute's teachers who wrote it, each linked to
 *   their public profile on the item page.
 */
export const DELIVERY_CHOOSERS = ['research', 'article'];

export const defaultDelivery = (contentType, accessType) => {
    if (!DELIVERY_CHOOSERS.includes(contentType)) return 'reader';

    return ['free_public', 'free_login'].includes(accessType) ? 'both' : 'reader';
};

export function DeliveryChoice({ contentType, value, onChange, hasPdf, t = {}, className = '' }) {
    if (!DELIVERY_CHOOSERS.includes(contentType)) return null;
    const options = [
        ['reader', t.library_delivery_reader || 'Read online only (protected reader — the file never leaves)'],
        ['download', t.library_delivery_download || 'Download the PDF only'],
        ['both', t.library_delivery_both || 'Both: read online or download'],
    ];

    return (
        <fieldset className={`rounded border p-3 ${className}`} data-testid="delivery-choice">
            <legend className="px-1 text-sm font-medium">{t.library_delivery_legend || 'How readers get it'}</legend>
            <div className="flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:gap-4">
                {options.map(([key, label]) => (
                    <label key={key} className="flex items-start gap-2 text-sm">
                        <input type="radio" name="delivery" value={key} checked={value === key} onChange={() => onChange(key)} data-testid={`delivery-${key}`} />
                        <span>{label}</span>
                    </label>
                ))}
            </div>
            {value !== 'reader' && !hasPdf && (
                <p className="mt-1 text-xs text-gray-500">{t.library_delivery_needs_pdf || 'Downloading needs an original PDF attached below.'}</p>
            )}
        </fieldset>
    );
}

export function TeacherAuthors({ teachers = [], value = [], onChange, t = {}, className = '' }) {
    if (teachers.length === 0) return null;
    const toggle = (id, on) => onChange(on ? [...value, id] : value.filter((other) => other !== id));

    return (
        <fieldset className={`rounded border p-3 ${className}`} data-testid="teacher-authors">
            <legend className="px-1 text-sm font-medium">{t.library_teacher_authors || 'Akuru teachers who are authors (linked to their profiles)'}</legend>
            <div className="grid max-h-40 gap-1 overflow-y-auto sm:grid-cols-2">
                {teachers.map((teacher) => (
                    <label key={teacher.id} className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={value.includes(teacher.id)} onChange={(e) => toggle(teacher.id, e.target.checked)} data-testid={`teacher-author-${teacher.slug}`} />
                        <span>{teacher.name}</span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
