export default function CustomFields({ fields, values, errors = {}, onChange }) {
    return (
        <div className="grid gap-4">
            {fields.map((field) => {
                const name = String(field.id);
                const value = values[name] ?? values[field.id] ?? (field.field_type === 'multiselect' ? [] : field.field_type === 'boolean' ? false : '');
                const error = errors[`field_${field.id}`] || errors[`values.${field.id}`];

                return (
                    <label key={field.id} className="grid gap-1 text-sm">
                        <span className="font-medium text-gray-800">
                            {field.label}
                            {field.required ? <span className="text-red-600"> *</span> : null}
                        </span>
                        {renderInput(field, value, (next) => onChange(field.id, next))}
                        {error && <span className="text-xs text-red-600">{Array.isArray(error) ? error[0] : error}</span>}
                    </label>
                );
            })}
        </div>
    );
}

function renderInput(field, value, onChange) {
    const common = 'form-input w-full rounded-md border-gray-300 text-sm';

    if (field.field_type === 'textarea') {
        return <textarea className={common} rows={3} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />;
    }

    if (field.field_type === 'boolean') {
        return (
            <input
                type="checkbox"
                checked={Boolean(value)}
                onChange={(e) => onChange(e.target.checked)}
            />
        );
    }

    if (field.field_type === 'select') {
        return (
            <select className={common} value={value ?? ''} onChange={(e) => onChange(e.target.value)}>
                <option value="">—</option>
                {(field.options || []).map((option) => {
                    const optionValue = option.value ?? option;
                    const optionLabel = option.label ?? optionValue;
                    return (
                        <option key={optionValue} value={optionValue}>
                            {optionLabel}
                        </option>
                    );
                })}
            </select>
        );
    }

    if (field.field_type === 'multiselect') {
        const selected = Array.isArray(value) ? value : [];
        return (
            <select
                className={common}
                multiple
                value={selected}
                onChange={(e) => onChange(Array.from(e.target.selectedOptions).map((option) => option.value))}
            >
                {(field.options || []).map((option) => {
                    const optionValue = option.value ?? option;
                    const optionLabel = option.label ?? optionValue;
                    return (
                        <option key={optionValue} value={optionValue}>
                            {optionLabel}
                        </option>
                    );
                })}
            </select>
        );
    }

    const type = field.field_type === 'number' ? 'number' : field.field_type === 'date' ? 'date' : 'text';

    return <input className={common} type={type} value={value ?? ''} onChange={(e) => onChange(e.target.value)} />;
}
