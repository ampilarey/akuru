import { usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

export default function Learning({ children }) {
    const t = usePage().props.i18n?.learn || {};

    return (
        <AppShell title={t.children_learning || 'Children learning'}>
            {children.length === 0 && <p className="text-sm text-gray-600">{t.no_children || 'No linked children.'}</p>}
            <div className="space-y-4" data-phone-main>
                {children.map((child) => (
                    <section key={child.id} className="rounded-lg border bg-white p-4">
                        <h2 className="mb-2 font-medium">{child.name}</h2>
                        {/* The relationship and the enrolment's state in words, not their codes (HP1). */}
                        <p className="mb-2 text-xs text-gray-500">{t[`relationship_${child.relationship}`] || child.relationship}</p>
                        {child.enrollments.length === 0 && <p className="text-sm text-gray-500">{t.not_enrolled}</p>}
                        <ul className="space-y-2 text-sm">
                            {child.enrollments.map((row) => (
                                <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                                    <span>{row.title} · {row.progress_percentage}% · {t[`enrol_status_${row.status}`] || row.status}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))}
            </div>
        </AppShell>
    );
}
