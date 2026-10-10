import AppShell from '../../../Layouts/AppShell';
import CustomFields from '../../../Components/CustomFields';
import { useState } from 'react';

/**
 * The custom fields an admission application asks for, as the form will show
 * them. Every word is the `people` book's (slice PE2, STATUS §5qq); a field
 * reads by its label in the page's language.
 */
export default function AdmissionPreview({ fields, t = {} }) {
    const [values, setValues] = useState({});

    return (
        <AppShell title={t.preview_title || 'Admission form custom fields'}>
            <p className="mb-4 text-sm text-gray-600">
                {t.preview_intro || 'Fields marked for admission applications render here automatically.'}
            </p>
            {fields.length === 0 ? (
                <p className="rounded border bg-white p-4 text-sm text-gray-500">{t.preview_none || 'No admission custom fields yet.'}</p>
            ) : (
                <div className="rounded-lg border bg-white p-4">
                    <CustomFields
                        fields={fields}
                        values={values}
                        onChange={(id, value) => setValues((current) => ({ ...current, [id]: value }))}
                    />
                </div>
            )}
        </AppShell>
    );
}
