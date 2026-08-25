import AppShell from '../../../Layouts/AppShell';

export default function Show({ snapshot }) {
    return (
        <AppShell title={snapshot.title || 'Lesson'}>
            <p className="mb-4 text-sm text-gray-600">Published revision {snapshot.revision_number}</p>
            {snapshot.description && <p className="mb-4">{snapshot.description}</p>}
            <div className="space-y-4">
                {(snapshot.blocks || []).map((block, index) => (
                    <article key={`${block.id}-${index}`} className="rounded-lg border bg-white p-4" dir="auto">
                        {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                        <div className="whitespace-pre-wrap text-sm">{block.data?.body || block.data?.html || ''}</div>
                    </article>
                ))}
            </div>
        </AppShell>
    );
}
