<?php

namespace App\Domains\Library\Enums;

enum LibraryContentType: string
{
    case Book = 'book';
    case Article = 'article';
    case Research = 'research';
    case CourseMaterial = 'course_material';
    // BLOG_PLAN BL2a: a blog post, written on *My blog* and checked before it is public.
    case Post = 'post';
}
