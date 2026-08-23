import AppShell from '../../../Layouts/AppShell';
import CustomFields from '../../../Components/CustomFields';
import { useState } from 'react';

export default function AdmissionPreview({ fields }) {
    const [values, setValues] = useState({});

    return (
        <AppShell title="Admission form custom fields">
            <p className="mb-4 text-sm text-gray-600">
                Fields marked for admission applications render here automatically.
            </p>
            {fields.length === 0 ? (
                <p className="rounded border bg-white p-4 text-sm text-gray-500">No admission custom fields yet.</p>
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
