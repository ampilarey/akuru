<?php

namespace App\Domains\Courses\Enums;

enum ActivityPattern: string
{
    case Selection = 'selection';
    case TextInput = 'text_input';
    case Arrange = 'arrange';
    case TeacherMarked = 'teacher_marked';
}
