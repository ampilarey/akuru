<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label for="whatsapp_number" class="block text-sm font-medium text-gray-700 mb-1">WhatsApp number</label>
        <input type="text" name="whatsapp_number" id="whatsapp_number" value="{{ old('whatsapp_number', isset($course) ? $course->whatsapp_number : '') }}" maxlength="32" placeholder="Leave blank to use the Settings default" class="form-input w-full rounded-md">
        <p class="text-xs text-gray-500 mt-1">Digits with country code, e.g. 9607972434. Blank uses conversion.whatsapp_number, then the Viber contact number.</p>
    </div>
    <div>
        <label for="syllabus_media_file_id" class="block text-sm font-medium text-gray-700 mb-1">Syllabus media file id</label>
        <input type="number" name="syllabus_media_file_id" id="syllabus_media_file_id" value="{{ old('syllabus_media_file_id', isset($course) ? $course->syllabus_media_file_id : '') }}" min="1" class="form-input w-full rounded-md">
        <p class="text-xs text-gray-500 mt-1">Public <code>media_files</code> id. Empty hides “Get full syllabus”.</p>
    </div>
</div>
