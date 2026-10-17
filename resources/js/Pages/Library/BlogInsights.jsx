import { router } from '@inertiajs/react';
import { useState } from 'react';
import AppShell from '../../Layouts/AppShell';
import FormErrors, { useRowRefusals } from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL7: the blogs at a glance — what was published, followed and
 * tipped this month, the age of the oldest thing in each checking queue —
 * and *Featured this month*, with the reward where the office turned it on.
 */
const days = (since) => (since ? Math.max(0, Math.floor((Date.now() - new Date(since.replace(' ', 'T')).getTime()) / 86400000)) : null);

export default function BlogInsights({ totals = {}, queues = {}, recent = [], reward = { enabled: false, cap: 0 }, t = {} }) {
    const refusals = useRowRefusals();
    const [amounts, setAmounts] = useState({});
    const feature = (id, on) => refusals.actOn(`post:${id}`, () => router.post(`/admin/library/blog-insights/${id}/feature`, { feature: on, reward: on && amounts[id] ? amounts[id] : null }, { preserveScroll: true }));
    const tiles = [
        ['blogs', t.insights_blogs || 'Blogs'], ['students', t.insights_students || 'Student blogs'], ['posts_published', t.insights_posts || 'Posts published'],
        ['posts_this_month', t.insights_posts_month || 'Posts this month'], ['follows', t.insights_follows || 'Follows'],
        ['tips_this_month', t.insights_tips_month || 'Tips this month (MVR)'], ['rewards_this_month', t.insights_rewards_month || 'Rewards this month (MVR)'],
    ];
    const queueNames = { requests: t.insights_q_requests || 'Blog requests', teacher: t.insights_q_teacher || 'Posts with class teachers', office: t.insights_q_office || 'Posts with the office', pages: t.insights_q_pages || 'Page changes', comments: t.insights_q_comments || 'Comments', tip_notes: t.insights_q_tip_notes || 'Tip notes' };

    return (
        <AppShell title={t.insights_title || 'Blog insights'}>
            <div className="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4" data-testid="insights-totals">
                {tiles.map(([key, label]) => (
                    <div key={key} className="rounded-lg border bg-white p-3" data-testid={`insights-${key}`}>
                        <p className="text-xs text-gray-500">{label}</p>
                        <p className="text-2xl font-semibold">{totals[key] ?? 0}</p>
                    </div>
                ))}
            </div>

            <h2 className="mb-2 text-lg font-semibold">{t.insights_queues || 'Checking queues'}</h2>
            <ul className="mb-6 grid gap-2 md:grid-cols-3" data-testid="insights-queues">
                {Object.entries(queueNames).map(([key, label]) => (
                    <li key={key} className="rounded border bg-white p-3 text-sm" data-testid={`insights-queue-${key}`}>
                        <p className="font-medium">{label}: {queues[key]?.count ?? 0}</p>
                        {queues[key]?.oldest && <p className="text-xs text-gray-600">{(t.insights_oldest || 'oldest :days days').replace(':days', days(queues[key].oldest))}</p>}
                    </li>
                ))}
            </ul>

            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-lg font-semibold">{t.insights_recent || 'Recent posts, to feature'}</h2>
                <a className="btn-secondary" href="/admin/library/blog-insights/export" data-testid="insights-export">{t.export_csv || 'Export CSV'}</a>
            </div>
            <p className="mb-2 text-xs text-gray-600">{reward.enabled ? (t.insights_reward_on || 'A featured post may get a reward from Akuru, up to MVR :cap, once.').replace(':cap', reward.cap) : t.insights_reward_off || 'Rewards are off. Turn them on in the Library settings.'}</p>
            <FormErrors errors={refusals.unplaced} className="mb-2" />
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="table-stack min-w-full text-sm" data-testid="insights-recent">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.col_post || 'Post'}</th>
                            <th className="px-3 py-2">{t.insights_readers || 'Readers'}</th>
                            <th className="px-3 py-2">{t.col_actions || 'Actions'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {recent.length === 0 && <tr><td className="px-3 py-4 text-gray-500" colSpan={3}>{t.insights_none || 'No posts in the last 60 days.'}</td></tr>}
                        {recent.map((p) => (
                            <tr key={p.id} className="border-t align-top" data-testid={`insights-post-${p.id}`}>
                                <td className="px-3 py-2" data-label={t.col_post || 'Post'}>
                                    <a className="font-medium underline" href={`/library/${p.slug}`}>{p.title}</a>
                                    <p className="text-xs text-gray-600">{p.blog}{p.student ? ` · ${t.badge_student || 'Student writer'}` : ''} · {p.published_at}</p>
                                    {p.featured && <p className="text-xs font-medium text-[#7C2D37]" data-testid={`insights-featured-${p.id}`}>{t.insights_featured || 'Featured this month'}{p.reward !== null ? ` · MVR ${p.reward.toFixed(2)}` : ''}</p>}
                                </td>
                                <td className="px-3 py-2" data-label={t.insights_readers || 'Readers'}>{p.reads}</td>
                                <td className="table-actions px-3 py-2" data-label={t.col_actions || 'Actions'}>
                                    {p.featured ? (
                                        <button type="button" className="btn-secondary" onClick={() => feature(p.id, false)} data-testid={`insights-unfeature-${p.id}`}>{t.insights_unfeature || 'Stop featuring'}</button>
                                    ) : (
                                        <div className="flex flex-wrap items-center gap-2">
                                            {reward.enabled && p.reward === null && <input className="form-input w-24" type="number" min="0" max={reward.cap} placeholder={t.insights_reward || 'Reward'} aria-label={t.insights_reward || 'Reward'} value={amounts[p.id] || ''} onChange={(e) => setAmounts({ ...amounts, [p.id]: e.target.value })} data-testid={`insights-reward-${p.id}`} />}
                                            <button type="button" className="btn-primary" onClick={() => feature(p.id, true)} data-testid={`insights-feature-${p.id}`}>{t.insights_feature || 'Feature this month'}</button>
                                        </div>
                                    )}
                                    <FormErrors errors={refusals.errorsFor(`post:${p.id}`)} className="mt-1" />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
