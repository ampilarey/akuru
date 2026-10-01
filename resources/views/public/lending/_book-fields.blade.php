{{-- L1: the fields of a lent book, for the add form and each book's edit form on My lending. --}}
@php($new = $book === null)
<label class="text-sm sm:col-span-2">{{ __('lending.book_title') }}
    <input type="text" name="title" value="{{ $new ? old('title') : $book['title'] }}" maxlength="255" required class="form-input w-full" dir="auto" @if($new) data-testid="book-title-input" @endif>
</label>
<label class="text-sm">{{ __('lending.book_author') }}
    <input type="text" name="author" value="{{ $new ? old('author') : $book['author'] }}" maxlength="255" class="form-input w-full" dir="auto" @if($new) data-testid="book-author" @endif>
</label>
<label class="text-sm">{{ __('lending.book_condition') }}
    <select name="condition" class="form-input w-full" @if($new) data-testid="book-condition-select" @endif>
        @foreach($conditions as $c)<option value="{{ $c }}" @selected(($new ? old('condition', 'good') : $book['condition']) === $c)>{{ __('lending.condition_'.$c) }}</option>@endforeach
    </select>
</label>
<label class="text-sm">{{ __('lending.book_grade') }}
    <input type="text" name="grade" value="{{ $new ? old('grade') : $book['grade'] }}" maxlength="40" class="form-input w-full" dir="auto" @if($new) data-testid="book-grade" @endif>
</label>
<label class="text-sm">{{ __('lending.book_subject') }}
    <input type="text" name="subject" value="{{ $new ? old('subject') : $book['subject'] }}" maxlength="80" class="form-input w-full" dir="auto" @if($new) data-testid="book-subject" @endif>
</label>
<label class="text-sm">{{ __('lending.book_language') }}
    <input type="text" name="language" value="{{ $new ? old('language') : $book['language'] }}" maxlength="40" class="form-input w-full" dir="auto">
</label>
<label class="text-sm">{{ __('lending.book_max_days') }}
    <input type="number" name="max_days" value="{{ $new ? old('max_days', $limits['default_days']) : $book['max_days'] }}" min="1" max="{{ $limits['max_days'] }}" class="form-input w-full" @if($new) data-testid="book-max-days" @endif>
</label>
<label class="text-sm sm:col-span-2">{{ __('lending.book_deposit') }}
    <input type="text" name="deposit" value="{{ $new ? old('deposit') : $book['deposit'] }}" maxlength="120" class="form-input w-full" dir="auto" placeholder="{{ __('lending.book_deposit_hint') }}" @if($new) data-testid="book-deposit-input" @endif>
</label>
<label class="text-sm sm:col-span-2">{{ __('lending.book_description') }}
    <textarea name="description" rows="2" maxlength="2000" class="form-input w-full" dir="auto">{{ $new ? old('description') : $book['description'] }}</textarea>
</label>
<label class="text-sm sm:col-span-2">{{ __('lending.book_photo') }} <span class="text-xs text-gray-500">{{ __('lending.book_photo_hint', ['kb' => $limits['photo_kb']]) }}</span>
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="form-input w-full text-sm" @if($new) data-testid="book-photo" @endif>
</label>
