import AppShell from '../../Layouts/AppShell';

export default function Work({ work = [] }) {
    return (
        <AppShell title="My child’s work">
            {work.length === 0 && (
                <p className="rounded-lg border bg-white p-4 text-sm text-gray-600">
                    Nothing has been shared yet. Teachers photograph paper work as it is done.
                </p>
            )}

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {work.map((item) => (
                    <figure key={item.id} className="rounded-lg border bg-white p-3">
                        <img
                            src={`/portal/work/${item.id}/photo`}
                            alt={item.title || `Work by ${item.student}`}
                            className="mb-2 w-full rounded border object-cover"
                            style={{ aspectRatio: '4 / 3', maxWidth: '100%' }}
                        />
                        <figcaption>
                            <p className="text-sm font-medium">{item.student}</p>
                            {item.title && <p className="text-sm">{item.title}</p>}
                            {item.note && <p className="text-xs text-gray-600">{item.note}</p>}
                            <p className="mt-1 text-xs text-gray-500">{item.done_on}</p>
                        </figcaption>
                    </figure>
                ))}
            </div>
        </AppShell>
    );
}
