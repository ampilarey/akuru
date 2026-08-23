<?php

namespace App\Domains\People\Enums;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Boolean = 'boolean';
}
