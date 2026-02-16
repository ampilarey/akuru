# Translation Management Guide

## Overview

Akuru Institute uses Laravel's localization with `mcamara/laravel-localization` for multi-language support (English, Arabic, Dhivehi).

## Translation Files

- `resources/lang/en/public.php` - English
- `resources/lang/ar/public.php` - Arabic  
- `resources/lang/dv/public.php` - Dhivehi

## Adding New Translations

1. Add the key and value to each language file
2. Use in Blade: `{{ __('public.Key Name') }}`
3. Use in PHP: `__('public.Key Name')`

## Missing Translation Detector

To find missing translations, compare keys across language files:

```bash
# Keys in en but not in ar
diff <(grep -oP "'[^']+'\s*=>" resources/lang/en/public.php | sort) <(grep -oP "'[^']+'\s*=>" resources/lang/ar/public.php | sort)
```

## RTL Support

Arabic and Dhivehi use RTL. The layout automatically sets `dir="rtl"` when locale is `ar` or `dv`.

## Cultural Formatting

- Dates: Use Carbon's `locale()` for locale-specific date formatting
- Numbers: Consider `NumberFormatter` for locale-specific number formatting
- Hijri dates: Use `alkoumi/laravel-hijri-date` package (already installed)

## Override Translations at Runtime

To allow admin overrides, you can create a `translation_overrides` table and a custom translation loader. See Laravel docs on [Translation](https://laravel.com/docs/localization).
