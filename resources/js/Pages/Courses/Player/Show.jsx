import AppShell from '../../../Layouts/AppShell';

function BlockView({ block }) {
    const direction = block.settings?.direction || 'auto';
    if (block.type === 'rich_text') {
        return (
            <article className="rounded-lg border bg-white p-4" dir={direction}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <div className="prose text-sm" dangerouslySetInnerHTML={{ __html: block.data?.html || '' }} />
            </article>
        );
    }
    if (block.type === 'instruction') {
        return (
            <aside className="rounded-lg border border-amber-200 bg-amber-50 p-4" dir={direction}>
                <p className="mb-1 text-xs uppercase tracking-wide text-amber-800">{block.data?.tone || 'note'}</p>
                <p className="whitespace-pre-wrap text-sm">{block.data?.body}</p>
            </aside>
        );
    }
    return (
        <article className="rounded-lg border bg-white p-4" dir={direction}>
            {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
            <p className="whitespace-pre-wrap text-sm">{block.data?.body}</p>
        </article>
    );
}

export default function Show({ snapshot }) {
    return (
        <AppShell title={snapshot.title || 'Lesson'}>
            <p className="mb-4 text-sm text-gray-600">Published revision {snapshot.revision_number}</p>
            {snapshot.description && <p className="mb-4">{snapshot.description}</p>}
            <div className="space-y-4">
                {(snapshot.blocks || []).map((block, index) => (
                    <BlockView key={`${block.id}-${index}`} block={block} />
                ))}
            </div>
        </AppShell>
    );
}
