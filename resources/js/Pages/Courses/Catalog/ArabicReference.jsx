import { useForm } from '@inertiajs/react';
import AppShell from '../../../Layouts/AppShell';
import FormErrors from '../../../Components/FormErrors';

export default function ArabicReference({ letters, harakas, t = {} }) {
    const letterForm = useForm({
        key_name: '',
        arabic_character: '',
        display_name: '',
        sort_order: letters.length + 1,
        is_active: true,
    });
    const harakahForm = useForm({
        key_name: '',
        symbol: '',
        display_name: '',
        sort_order: harakas.length + 1,
        is_active: true,
    });

    return (
        <AppShell title={t.arref_title || 'Arabic letters and harakas'}>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href="/catalog/arabic/export">{t.catalog_export || 'Export CSV'}</a>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    letterForm.post('/catalog/arabic/letters', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <input className="form-input" dir="ltr" placeholder={t.arref_key || 'Key'} aria-label={t.arref_key || 'Key'} value={letterForm.data.key_name} onChange={(e) => letterForm.setData('key_name', e.target.value)} />
                <input className="form-input" dir="rtl" placeholder="ا" aria-label={t.arref_letter_glyph || 'Letter'} value={letterForm.data.arabic_character} onChange={(e) => letterForm.setData('arabic_character', e.target.value)} />
                <input className="form-input" placeholder={t.arref_display_name || 'Display name'} aria-label={t.arref_display_name || 'Display name'} value={letterForm.data.display_name} onChange={(e) => letterForm.setData('display_name', e.target.value)} />
                <input className="form-input" type="number" aria-label={t.arref_order || 'Order'} value={letterForm.data.sort_order} onChange={(e) => letterForm.setData('sort_order', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={letterForm.processing}>{t.arref_save_letter || 'Save letter'}</button>
                <FormErrors errors={letterForm.errors} />
            </form>
            <div className="mb-6 overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.arref_col_glyph || 'Glyph'}</th>
                            <th className="px-3 py-2">{t.arref_key || 'Key'}</th>
                            <th className="px-3 py-2">{t.arref_col_name || 'Name'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {letters.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2 text-xl">{row.arabic_character}</td>
                                <td className="px-3 py-2">{row.key_name}</td>
                                <td className="px-3 py-2">{row.display_name}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    harakahForm.post('/catalog/arabic/harakas', { preserveScroll: true });
                }}
                className="mb-4 grid gap-3 rounded-lg border bg-white p-4 md:grid-cols-5"
            >
                <input className="form-input" dir="ltr" placeholder={t.arref_key || 'Key'} aria-label={t.arref_key || 'Key'} value={harakahForm.data.key_name} onChange={(e) => harakahForm.setData('key_name', e.target.value)} />
                <input className="form-input" dir="rtl" placeholder="َ" aria-label={t.arref_harakah_glyph || 'Harakah'} value={harakahForm.data.symbol} onChange={(e) => harakahForm.setData('symbol', e.target.value)} />
                <input className="form-input" placeholder={t.arref_display_name || 'Display name'} aria-label={t.arref_display_name || 'Display name'} value={harakahForm.data.display_name} onChange={(e) => harakahForm.setData('display_name', e.target.value)} />
                <input className="form-input" type="number" aria-label={t.arref_order || 'Order'} value={harakahForm.data.sort_order} onChange={(e) => harakahForm.setData('sort_order', e.target.value)} />
                <button type="submit" className="btn-primary" disabled={harakahForm.processing}>{t.arref_save_harakah || 'Save harakah'}</button>
                <FormErrors errors={harakahForm.errors} />
            </form>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">{t.arref_col_symbol || 'Symbol'}</th>
                            <th className="px-3 py-2">{t.arref_key || 'Key'}</th>
                            <th className="px-3 py-2">{t.arref_col_name || 'Name'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {harakas.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2 text-xl">{row.symbol}</td>
                                <td className="px-3 py-2">{row.key_name}</td>
                                <td className="px-3 py-2">{row.display_name}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppShell>
    );
}
