<?php

namespace App\Domains\Website\Enums;

enum LeadSource: string
{
    case Syllabus = 'syllabus';
    case WaitingList = 'waiting_list';
    case Callback = 'callback';
}
