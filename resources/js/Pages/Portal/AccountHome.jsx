import { Link, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * *My account* (docs/SIGN_IN_PLAN.md ID2b): the home of a person who holds
 * no other workspace — no role, no course of their own. It replaced the
 * public course dashboard, a Blade page in the website's layout with the
 * courses open for enrolment down its side.
 *
 * Its doors are the workspace's own menu laid out as tiles, so a tile can
 * never lead anywhere the menu does not, and a door the route would refuse
 * is not offered.
 */
const tile = 'block rounded-lg border bg-white p-4 text-sm font-medium text-[#7C2D37] hover:bg-[#F3EBE0]';

export default function AccountHome({ t = {}, children_waiting = [], enrolments = [], enrolments_total = 0 }) {
    const { nav = { primary: [], groups: [] } } = usePage().props;
    const seen = new Set(['/my-account']);
    const doors = [...(nav.primary || []), ...(nav.groups || []).flatMap((group) => group.items)].filter((item) => {
        if (seen.has(item.href)) {
            return false;
        }
        seen.add(item.href);

        return true;
    });

    return (
        <AppShell title={t.home_title || 'My account'}>
            <p className="mb-4 text-sm text-gray-600">{t.home_intro}</p>

            {/* ID2c: the office's check is what opens Family; until then the
                children a parent registered on the website wait here. */}
            {children_waiting.length > 0 && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4" role="status" data-testid="children-waiting">
                    <p className="font-medium text-amber-900">{t.children_title}</p>
                    <p className="mt-1 text-sm text-amber-800">{t.children_body}</p>
                    <ul className="mt-2 list-disc ps-5 text-sm text-amber-900">
                        {children_waiting.map((child) => (
                            <li key={child.id}>{child.name} · {child.refused ? t.child_refused : t.child_awaiting}</li>
                        ))}
                    </ul>
                </div>
            )}

            <section className="mb-6 rounded-lg border bg-white p-4" data-testid="recent-enrolments">
                <h2 className="mb-2 font-medium">{t.recent_title}</h2>
                {enrolments.length === 0 ? (
                    <p className="text-sm text-gray-600">
                        {t.enrolments_empty}{' '}
                        <Link href="/learn/catalog" className="text-[#7C2D37] underline">{t.browse_courses}</Link>
                    </p>
                ) : (
                    <ul>
                        {enrolments.map((row) => (
                            <li key={row.id} className="flex flex-wrap justify-between gap-2 border-t py-2 text-sm first:border-t-0">
                                <span>
                                    <span className="font-medium">{row.course}</span>
                                    <span className="text-gray-600"> · {row.own ? t.you : row.student}</span>
                                </span>
                                <span className="text-gray-700">{t[`state_${row.state}`] || row.state}</span>
                            </li>
                        ))}
                    </ul>
                )}
                {enrolments_total > 0 && (
                    <Link href="/my-enrollments" className="mt-2 inline-block text-sm text-[#7C2D37] underline" data-testid="all-enrolments">
                        {(t.all_enrolments || 'All my enrolments (:count)').replace(':count', enrolments_total)}
                    </Link>
                )}
            </section>

            {doors.length > 0 && (
                <section>
                    <h2 className="mb-2 font-medium">{t.doors_title}</h2>
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3" data-testid="account-doors">
                        {doors.map((item) => (
                            <li key={item.href}>
                                {/* A Blade page (the Library, the Bookstore) loads whole. */}
                                {item.hard
                                    ? <a href={item.href} className={tile}>{item.label}</a>
                                    : <Link href={item.href} className={tile}>{item.label}</Link>}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </AppShell>
    );
}
