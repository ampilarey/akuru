import { usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Learning({ children }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.children_learning || 'Children learning'}>
            {children.length === 0 && <p className="text-sm text-gray-600">{t.no_children || 'No linked children.'}</p>}
            <div className="space-y-4">
                {children.map((child) => (
                    <section key={child.id} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-2 font-medium">{child.name}</h2>
                        <p className="mb-2 text-xs uppercase text-gray-500">{child.relationship}</p>
                        {child.enrollments.length === 0 && <p className="text-sm text-gray-500">{t.not_enrolled}</p>}
                        <ul className="space-y-2 text-sm">
                            {child.enrollments.map((row) => (
                                <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                                    <span>{row.title} · {row.progress_percentage}% · {row.status}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
