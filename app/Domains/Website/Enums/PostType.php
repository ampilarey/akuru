<?php

namespace App\Domains\Website\Enums;

enum PostType: string
{
    case Article = 'article';
    case News = 'news';
    case Research = 'research';
}
