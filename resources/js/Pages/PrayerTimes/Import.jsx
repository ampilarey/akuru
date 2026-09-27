import { Link, router, useForm, usePage } from '@inertiajs/react';
import AppShell from '../../Layouts/AppShell';

/**
 * Import prayer times (C9 slice 12, STATUS §5jn): the bundled Maldivian
 * dataset in one click, an uploaded salat.db, or the synthetic Malé fixture.
 * Each is a request back to this page, which shows the flash or the error.
 */
export default function Import({ cache_version = 1, t = {} }) {
    const { flash = {}, errors = {} } = usePage().props;
    const upload = useForm({ salat_db: null });
    const firstError = Object.values(errors)[0];
    const post = (data) => router.post('/admin/prayer-times/import', data, { preserveScroll: true });
    const submitUpload = (e) => {
        e.preventDefault();
        upload.post('/admin/prayer-times/import', { forceFormData: true, preserveScroll: true });
    };

    return (
        <AppShell title={t.prayer_import_title || 'Import prayer times'}>
            <p className="mb-4 text-sm"><Link href="/admin/prayer-times/islands" className="text-gray-500 underline" data-testid="prayer-islands-link">{t.prayer_link_islands || 'Islands →'}</Link></p>
            {flash.success && <p className="mb-4 rounded bg-green-50 p-3 text-green-700" data-testid="prayer-flash">✓ {flash.success}</p>}
            {firstError && <p className="mb-4 rounded bg-red-50 p-3 text-sm text-red-700" data-testid="prayer-error">✗ {firstError}</p>}
            <p className="mb-4 max-w-2xl text-sm text-gray-600">{(t.prayer_import_note || 'Cache version :version. Import fails unless every category has 366 rows.').replace(':version', cache_version)}</p>

            <div className="max-w-2xl space-y-4">
                <form action="/admin/prayer-times/import" method="post" onSubmit={(e) => { e.preventDefault(); post({ use_bundled: 1 }); }} className="rounded-lg border bg-white p-6" data-testid="prayer-import-bundled">
                    <p className="mb-3 text-sm text-gray-600">{t.prayer_import_bundled_note || 'Import the bundled Maldivian dataset shipped with the app (42 zones, 205 islands, 366 days). Sets Malé as the default island if none is set.'}</p>
                    <button type="submit" className="btn-primary">{t.prayer_import_bundled || 'Import bundled dataset'}</button>
                </form>
                <form action="/admin/prayer-times/import" method="post" encType="multipart/form-data" onSubmit={submitUpload} className="rounded-lg border bg-white p-6" data-testid="prayer-import-upload">
                    <label className="mb-2 block text-sm" htmlFor="prayer-salat-db">{t.prayer_import_file || 'salat.db'}</label>
                    <input id="prayer-salat-db" type="file" name="salat_db" accept=".db,.sqlite" className="form-input mb-4" onChange={(e) => upload.setData('salat_db', e.target.files[0] ?? null)} />
                    <button type="submit" className="btn-primary" disabled={upload.processing || !upload.data.salat_db}>{t.prayer_import_upload || 'Import'}</button>
                </form>
                <form action="/admin/prayer-times/import" method="post" onSubmit={(e) => { e.preventDefault(); post({ seed_fixture: 1 }); }} data-testid="prayer-import-seed">
                    <button type="submit" className="btn-secondary">{t.prayer_import_seed || 'Seed synthetic Malé 366-day fixture'}</button>
                </form>
            </div>
        </AppShell>
    );
}
