<?php

namespace App\Domains\Courses\Enums;

enum ContentBlockType: string
{
    case Text = 'text';
    case RichText = 'rich_text';
    case Instruction = 'instruction';
}
