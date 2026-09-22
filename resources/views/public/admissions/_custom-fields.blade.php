{{--
    S1.2 custom fields on the public admission form. `$customFields` comes from
    People\ListCustomFieldsAction::forAdmissionForm(); labels are the
    definition's own localized label, so nothing here needs translating.
    Inputs post as values[{id}]; errors come back keyed field_{id} from
    SaveCustomFieldValuesAction.
--}}
@foreach(($customFields ?? []) as $field)
    @php
        $name = 'values['.$field['id'].']';
        $old = old('values.'.$field['id']);
        $errorKey = 'field_'.$field['id'];
        $inputClass = 'w-full px-3 py-2 border border-brandGray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-brandMaroon-500';
    @endphp
    <div data-custom-field="{{ $field['key'] }}">
        <label for="custom-field-{{ $field['id'] }}" class="block text-sm font-medium text-brandGray-700 mb-2">
            {{ $field['label'] }}@if($field['required']) <span class="text-red-600">*</span>@endif
        </label>

        @if($field['field_type'] === 'textarea')
            <textarea name="{{ $name }}" id="custom-field-{{ $field['id'] }}" rows="3" class="{{ $inputClass }}">{{ $old }}</textarea>
        @elseif($field['field_type'] === 'select')
            <select name="{{ $name }}" id="custom-field-{{ $field['id'] }}" class="{{ $inputClass }}">
                <option value="">—</option>
                @foreach($field['options'] as $option)
                    <option value="{{ $option['value'] }}" @selected((string) $old === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        @elseif($field['field_type'] === 'multiselect')
            @php $chosen = array_map('strval', (array) ($old ?? [])); @endphp
            <div id="custom-field-{{ $field['id'] }}" class="grid gap-1">
                @foreach($field['options'] as $option)
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="{{ $name }}[]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true))>
                        {{ $option['label'] }}
                    </label>
                @endforeach
            </div>
        @elseif($field['field_type'] === 'boolean')
            <input type="hidden" name="{{ $name }}" value="0">
            <label class="inline-flex items-center gap-2 text-sm">
                <input type="checkbox" name="{{ $name }}" id="custom-field-{{ $field['id'] }}" value="1" @checked((string) $old === '1')>
                {{ $field['label'] }}
            </label>
        @else
            <input
                type="{{ $field['field_type'] === 'number' ? 'number' : ($field['field_type'] === 'date' ? 'date' : 'text') }}"
                name="{{ $name }}"
                id="custom-field-{{ $field['id'] }}"
                value="{{ $old }}"
                class="{{ $inputClass }}"
            >
        @endif

        @error($errorKey)
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
@endforeach
