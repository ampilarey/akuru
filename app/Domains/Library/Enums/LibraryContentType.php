<?php

namespace App\Domains\Library\Enums;

enum LibraryContentType: string
{
    case Book = 'book';
    case Article = 'article';
    case Research = 'research';
    case CourseMaterial = 'course_material';
}
