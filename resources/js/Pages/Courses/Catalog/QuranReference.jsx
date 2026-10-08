import AppShell from '../../../Layouts/AppShell';

export default function QuranReference({ surahs = [], selected_surah = null, ayahs = [], t = {} }) {
    const exportHref = selected_surah
        ? `/catalog/quran/export?surah=${selected_surah.index}`
        : '/catalog/quran/export';

    return (
        <AppShell title={t.qref_title || 'Qur’an reference'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.qref_intro || 'The surah list and ayah text every Qur’an screen reads from — one Qur’an dataset for the whole site.'}
            </p>
            <div className="mb-4 flex justify-end">
                <a className="btn-secondary" href={exportHref}>{t.catalog_export || 'Export CSV'}</a>
            </div>
            <div className="overflow-x-auto rounded-lg border bg-white">
                <table className="min-w-full text-sm">
                    <thead className="bg-[#F3EBE0] text-start">
                        <tr>
                            <th className="px-3 py-2">#</th>
                            <th className="px-3 py-2">{t.qref_col_arabic || 'Arabic'}</th>
                            <th className="px-3 py-2">{t.qref_col_english || 'English'}</th>
                            <th className="px-3 py-2">{t.qt_col_ayahs || 'Ayahs'}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {surahs.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">{row.index}</td>
                                <td className="px-3 py-2" dir="rtl">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/quran?surah=${row.index}`}>
                                        {row.arabic_name}
                                    </a>
                                </td>
                                <td className="px-3 py-2">
                                    <a className="text-[#7C2D37] hover:underline" href={`/catalog/quran?surah=${row.index}`}>
                                        {row.english_name}
                                    </a>
                                </td>
                                <td className="px-3 py-2">{row.ayah_count}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {selected_surah && (
                <div className="mt-6 overflow-x-auto rounded-lg border bg-white">
                    <h2 className="border-b px-3 py-2 text-sm font-semibold">
                        {selected_surah.english_name} — {selected_surah.arabic_name}
                    </h2>
                    <table className="min-w-full text-sm">
                        <thead className="bg-[#F3EBE0] text-start">
                            <tr>
                                <th className="px-3 py-2">{t.qt_ayah || 'Ayah'}</th>
                                <th className="px-3 py-2">{t.qref_col_text || 'Text'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ayahs.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">{row.ayah_number}</td>
                                    <td className="px-3 py-2 text-lg" dir="rtl">{row.text_uthmani}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AppShell>
    );
}
