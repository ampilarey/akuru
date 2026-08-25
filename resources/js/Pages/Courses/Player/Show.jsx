import AppShell from '../../../Layouts/AppShell';

function mediaSrc(mediaShowUrl, mediaId) {
    return `${mediaShowUrl}/${mediaId}`;
}

function BlockView({ block, mediaShowUrl }) {
    const direction = block.settings?.direction || 'auto';
    const src = block.data?.media_id ? mediaSrc(mediaShowUrl, block.data.media_id) : null;

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
    if (block.type === 'image' && src) {
        return (
            <figure className="rounded-lg border bg-white p-4" dir={direction}>
                {block.title && <figcaption className="mb-2 font-medium">{block.title}</figcaption>}
                <img src={src} alt={block.data?.original_name || block.title || ''} className="max-h-[32rem] w-full object-contain" />
            </figure>
        );
    }
    if (block.type === 'audio' && src) {
        return (
            <article className="rounded-lg border bg-white p-4" dir={direction}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <p className="mb-2 text-sm text-gray-600">{block.data?.original_name}</p>
                <audio className="w-full" controls src={src} preload="metadata" />
            </article>
        );
    }
    if (block.type === 'video') {
        if (block.data?.embed_url) {
            return (
                <article className="rounded-lg border bg-white p-4" dir={direction}>
                    {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                    <iframe
                        className="aspect-video w-full rounded border-0"
                        src={block.data.embed_url}
                        title={block.title || 'Lesson video'}
                        allow="fullscreen"
                    />
                </article>
            );
        }
        if (src) {
            return (
                <article className="rounded-lg border bg-white p-4" dir={direction}>
                    {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                    <video className="w-full" controls src={src} preload="metadata" />
                </article>
            );
        }
    }
    if (block.type === 'pdf' && src) {
        return (
            <article className="rounded-lg border bg-white p-4" dir={direction}>
                {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
                <iframe className="h-[36rem] w-full rounded border" src={src} title={block.data?.original_name || 'PDF'} />
                <a className="mt-2 inline-block text-sm text-[#7C2D37] hover:underline" href={src}>{block.data?.original_name || 'Open PDF'}</a>
            </article>
        );
    }

    return (
        <article className="rounded-lg border bg-white p-4" dir={direction}>
            {block.title && <h2 className="mb-2 font-medium">{block.title}</h2>}
            <p className="whitespace-pre-wrap text-sm">{block.data?.body}</p>
        </article>
    );
}

export default function Show({ snapshot, mediaShowUrl = '/catalog/media' }) {
    return (
        <AppShell title={snapshot.title || 'Lesson'}>
            <p className="mb-4 text-sm text-gray-600">Published revision {snapshot.revision_number}</p>
            {snapshot.description && <p className="mb-4">{snapshot.description}</p>}
            <div className="space-y-4">
                {(snapshot.blocks || []).map((block, index) => (
                    <BlockView key={`${block.id}-${index}`} block={block} mediaShowUrl={mediaShowUrl} />
                ))}
            </div>
        </AppShell>
    );
}
