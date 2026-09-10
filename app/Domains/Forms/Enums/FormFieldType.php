<?php

namespace App\Domains\Forms\Enums;

/**
 * Field types for v1.
 *
 * `file` is deliberately absent: uploads need Media plumbing and a retention
 * answer, and a half-built upload on a trip permission slip is worse than a
 * text box. It arrives with the same slice that gives homework attachments a
 * home.
 */
enum FormFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case YesNo = 'yes_no';
    case Date = 'date';

    /** Types whose answer must come from a supplied option list. */
    public function needsOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], true);
    }
}
