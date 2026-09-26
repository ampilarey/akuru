import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * The admin panel's front door at /admin: one card per section this person
 * may open (docs/ADMIN_PANEL.md §1). A Blade section opens with a full page
 * load; an Inertia one with a visit.
 */
export default function AdminHub({ t, sections }) {
    return (
        <AppShell title={t.hub_title}>
            <p className="mb-6 text-sm text-gray-600">{t.hub_intro}</p>
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="admin-sections">
                {sections.map((section) => {
                    const body = (
                        <>
                            <span className="block text-base font-semibold text-[#7C2D37]">{section.label}</span>
                            <span className="mt-1 block text-sm text-gray-600">{section.description}</span>
                        </>
                    );
                    const className = 'block h-full rounded-lg border border-[#E6D9C8] bg-white p-4 hover:border-[#7C2D37] hover:shadow';

                    return (
                        <li key={section.key} data-testid={`section-${section.key}`}>
                            {section.hard
                                ? <a href={section.href} className={className}>{body}</a>
                                : <Link href={section.href} className={className}>{body}</Link>}
                        </li>
                    );
                })}
            </ul>
            <p className="mt-6 text-sm"><a href="/dashboard" className="text-[#7C2D37] hover:underline" data-testid="hub-dashboard">← {t.hub_dashboard}</a></p>
        </AppShell>
    );
}
