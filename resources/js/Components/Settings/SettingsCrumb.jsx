import { Link } from '@inertiajs/react';

/**
 * ST1 (STATUS §5ut): the way back from a product's settings page to the
 * Settings index, the same on every one of them.
 */
export default function SettingsCrumb({ t = {} }) {
    return (
        <p className="mb-3 text-sm" data-testid="settings-crumb">
            <Link href="/admin/settings" className="inline-flex min-h-[2rem] items-center text-blue-700 underline">← {t.settings_all || 'All settings'}</Link>
        </p>
    );
}
