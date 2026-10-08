import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AppShell from '../../../../Layouts/AppShell';

// `:name` placeholders in a phrase, filled in.
const fill = (text, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), text);

export default function QuranPageShow({
    mushaf,
    page,
    page_number,
    last_page = null,
    ayahs = [],
    positions = [],
    words = [],
    can_manage = false,
    t = {},
}) {
    const [mapping, setMapping] = useState(false);
    const form = useForm({ quran_word_id: '', x: 10, y: 10, width: 5, height: 3 });
    // The box, as a percentage of the page from its left and top edges.
    const boxFields = {
        x: t.qpage_x || 'Left %',
        y: t.qpage_y || 'Top %',
        width: t.qpage_width || 'Width %',
        height: t.qpage_height || 'Height %',
    };

    const save = (event) => {
        event.preventDefault();
        form.post(`/quran/mushafs/${mushaf.id}/pages/${page.id}/positions`, { preserveScroll: true });
    };

    return (
        <AppShell title={fill(t.qpage_title || ':mushaf — page :n', { mushaf: mushaf.name, n: page_number })}>
            <div className="mb-4 flex flex-wrap items-center justify-end gap-2">
                <Link className="btn-secondary" href={`/quran/mushafs/${mushaf.id}`}>{t.qpage_back || 'Back to mushaf'}</Link>
                {page_number > 1 && (
                    <Link className="btn-secondary" href={`/quran/mushafs/${mushaf.id}/pages/${page_number - 1}`}>
                        {t.qpage_previous || 'Previous'}
                    </Link>
                )}
                {/* The last page has no next one; the link led to a 404 (slice CT5b). */}
                {(last_page === null || page_number < last_page) && (
                    <Link className="btn-secondary" href={`/quran/mushafs/${mushaf.id}/pages/${page_number + 1}`}>
                        {t.qpage_next || 'Next'}
                    </Link>
                )}
            </div>

            {can_manage && (
                <button type="button" className="btn-secondary mb-4 text-sm" onClick={() => setMapping(!mapping)}>
                    {mapping ? (t.qpage_stop_mapping || 'Stop mapping') : (t.qpage_map || 'Map word positions')}
                </button>
            )}

            <div className="grid gap-4 lg:grid-cols-2">
                <div className="relative rounded-lg border bg-white p-4">
                    {page.image_url ? (
                        <img src={page.image_url} alt={fill(t.qpage_image_alt || 'Page :n', { n: page_number })} className="w-full rounded" />
                    ) : (
                        <div className="flex h-96 items-center justify-center bg-gray-200 p-4 text-center text-gray-500">
                            {t.qpage_no_image || 'This page has no image yet. Word boxes are saved and drawn over the image once there is one.'}
                        </div>
                    )}
                    {positions.map((box) => (
                        <div
                            key={box.id}
                            title={box.word_text ?? ''}
                            className="absolute border border-red-400"
                            style={{
                                left: `${box.x}%`,
                                top: `${box.y}%`,
                                width: `${box.width}%`,
                                height: `${box.height}%`,
                            }}
                        />
                    ))}
                </div>

                <div className="rounded-lg border bg-white p-4">
                    <h2 className="mb-2 font-semibold">{t.qpage_ayahs || 'Ayahs on this page'}</h2>
                    {ayahs.length === 0 && <p className="text-sm text-gray-500">{t.qpage_no_ayahs || 'No ayahs imported for this page yet.'}</p>}
                    {ayahs.map((ayah) => (
                        <p key={ayah.id} className="border-b py-1 text-end text-sm" dir="rtl">
                            {ayah.surah_number}:{ayah.ayah_number} {ayah.text_uthmani}
                        </p>
                    ))}

                    {can_manage && mapping && (
                        <form onSubmit={save} className="mt-4 border-t pt-4">
                            <p className="mb-2 text-sm">{t.qpage_pick_hint || 'Select a word, then set its box as a percentage of the page:'}</p>
                            <select
                                className="form-input mb-2 w-full text-sm"
                                aria-label={t.qpage_word || 'Select word'}
                                value={form.data.quran_word_id}
                                onChange={(e) => form.setData('quran_word_id', e.target.value)}
                                required
                            >
                                <option value="">{t.qpage_word || 'Select word'}</option>
                                {words.map((word) => (
                                    <option key={word.id} value={word.id}>{word.label}</option>
                                ))}
                            </select>
                            <div className="mb-2 grid grid-cols-4 gap-1">
                                {Object.entries(boxFields).map(([field, label]) => (
                                    <input
                                        key={field}
                                        type="number" min="0" max="100" step="0.1"
                                        className="form-input text-xs"
                                        aria-label={label}
                                        placeholder={label}
                                        value={form.data[field]}
                                        onChange={(e) => form.setData(field, e.target.value)}
                                    />
                                ))}
                            </div>
                            <button type="submit" className="btn-primary text-sm" disabled={form.processing}>
                                {t.qpage_save || 'Save position'}
                            </button>
                            {Object.values(form.errors).map((message) => (
                                <p key={message} className="mt-1 text-sm text-red-600">{message}</p>
                            ))}
                        </form>
                    )}
                    {can_manage && mapping && words.length === 0 && (
                        <p className="mt-2 text-sm text-gray-500">
                            {t.qpage_no_words || 'No words imported for this page — import an ayah with its words first.'}
                        </p>
                    )}
                </div>
            </div>
        </AppShell>
    );
}
