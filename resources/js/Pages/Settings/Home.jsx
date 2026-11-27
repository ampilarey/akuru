import { Link } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * ST1 (admin audit 2026-11-27, STATUS §5ut): the Settings index. One card
 * per product's own settings page and nothing else — no setting is edited
 * here. The School's own stay in the School workspace and are linked.
 */
function Cards({ cards, t }) {
    return (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {cards.map((card) => (
                <li key={card.key}>
                    <Link href={card.href} className="flex h-full min-h-[6rem] flex-col justify-between rounded-lg border bg-white p-4 hover:shadow-md" data-testid={`settings-card-${card.key}`}>
                        <span>
                            <span className="block font-semibold text-gray-900">{card.title}</span>
                            <span className="mt-1 block text-sm text-gray-600">{card.description}</span>
                        </span>
                        <span className="mt-3 text-sm font-semibold text-blue-700">{t.settings_index_open} <span className="rtl-flip" aria-hidden="true">→</span></span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

export default function Home({ cards = [], t = {} }) {
    const institute = cards.filter((c) => c.group === 'institute');
    const school = cards.filter((c) => c.group === 'school');

    return (
        <AppShell title={t.settings_index_title}>
            <p className="mb-4 text-sm text-gray-600">{t.settings_index_intro}</p>
            {institute.length > 0 && (
                <section className="mb-6" data-phone-main data-testid="settings-institute">
                    <h2 className="mb-2 text-lg font-semibold">{t.settings_index_institute}</h2>
                    <Cards cards={institute} t={t} />
                </section>
            )}
            {school.length > 0 && (
                <section data-testid="settings-school">
                    <h2 className="mb-1 text-lg font-semibold">{t.settings_index_school}</h2>
                    <p className="mb-2 text-sm text-gray-600">{t.settings_index_school_note}</p>
                    <Cards cards={school} t={t} />
                </section>
            )}
        </AppShell>
    );
}
