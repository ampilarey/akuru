import { Link, router, useForm } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';
import FormErrors from '../../Components/FormErrors';

/**
 * BLOG_PLAN BL1 (D7): a parent sees their child's request to start a blog
 * and agrees, or says not now. With a yes they choose whether the child's
 * photo and school name may show. Nothing is published until the office
 * approves too.
 */
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.split(`:${key}`).join(String(value ?? '')), String(text));

export default function ChildBlog({ child, request = null, links_allowed: linksAllowed = false, t = {} }) {
    const form = useForm({ request_id: request?.id ?? 0, agree: true, show_photo: false, show_school: false });
    const decide = (agree) => {
        form.transform((data) => ({ ...data, agree }));
        form.post(`/portal/children/${child.id}/blog/consent`, { preserveScroll: true });
    };

    return (
        <AppShell title={fill(t.child_title || ':name’s blog', { name: child.name })}>
            <div className="mb-4 text-sm">
                <Link href="/portal/children" className="underline">{t.my_children || 'My children'}</Link>
            </div>

            {!request && <p className="max-w-xl rounded-lg border bg-white p-4 text-sm text-gray-700" data-testid="child-blog-none">{t.child_none || 'Your child has not asked to start a blog.'}</p>}

            {request && (
                <section className="max-w-xl rounded-lg border bg-white p-4" data-testid="child-blog-request" data-status={request.status}>
                    <p className="text-sm text-gray-800">{fill(t.child_asked || ':name would like to start a blog called “:title”, written as “:pen”.', { name: child.name, title: request.blog_title, pen: request.pen_name })}</p>
                    <p className="mt-2 inline-block rounded bg-amber-50 px-2 py-1 text-sm font-medium text-amber-900" data-testid="child-blog-status">{t[`status_${request.status}`] || request.status}</p>

                    {request.status === 'waiting_parent' && (
                        <form className="mt-4 grid gap-3" onSubmit={(e) => e.preventDefault()} data-testid="child-blog-consent">
                            <ul className="list-disc space-y-1 ps-5 text-sm text-gray-700">
                                <li>{t.child_point_checked || 'Every post and comment is checked before anyone can read it.'}</li>
                                <li>{t.child_point_name || 'Readers see the pen name, not your child’s full name.'}</li>
                                <li>{t.child_point_money || 'If the blog earns anything while your child is under 18, it goes to your Akuru wallet.'}</li>
                                <li>{t.child_point_withdraw || 'You can withdraw this consent later through the school office.'}</li>
                            </ul>
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" checked={form.data.show_photo} onChange={(e) => form.setData('show_photo', e.target.checked)} data-testid="child-blog-photo" />
                                <span>{t.choice_photo || 'My child’s photo may show on the blog'}</span>
                            </label>
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" checked={form.data.show_school} onChange={(e) => form.setData('show_school', e.target.checked)} data-testid="child-blog-school" />
                                <span>{t.choice_school || 'The school’s name may show on the blog'}</span>
                            </label>
                            <FormErrors errors={form.errors} />
                            <div className="flex flex-wrap gap-2">
                                <button type="button" className="btn-primary" disabled={form.processing} onClick={() => decide(true)} data-testid="child-blog-agree">{t.btn_agree || 'I agree'}</button>
                                <button type="button" className="btn-secondary" disabled={form.processing} onClick={() => decide(false)} data-testid="child-blog-decline">{t.btn_decline || 'Not now'}</button>
                            </div>
                        </form>
                    )}

                    {request.status === 'approved' && (
                        <div className="mt-4 rounded border p-3 text-sm" data-testid="child-blog-links" data-allowed={linksAllowed ? 'yes' : 'no'}>
                            {/* BL3b: links to other sites only with the parent's yes. */}
                            <p className="font-medium">{t.links_heading || 'Links to other sites'}</p>
                            <p className="text-gray-700">{linksAllowed ? t.links_on || 'Your child’s blog may show links to other sites, such as a YouTube channel. The office checks each one.' : t.links_off || 'Your child’s blog shows no links to other sites.'}</p>
                            <button
                                type="button"
                                className={`mt-2 ${linksAllowed ? 'btn-secondary' : 'btn-primary'}`}
                                onClick={() => router.post(`/portal/children/${child.id}/blog/links`, { allow: !linksAllowed }, { preserveScroll: true })}
                                data-testid="child-blog-links-toggle"
                            >
                                {linksAllowed ? t.links_stop || 'Stop links' : t.links_allow || 'Allow links'}
                            </button>
                        </div>
                    )}
                </section>
            )}
        </AppShell>
    );
}
