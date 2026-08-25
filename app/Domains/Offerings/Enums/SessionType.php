<?php

namespace App\Domains\Offerings\Enums;

enum SessionType: string
{
    case FaceToFace = 'face_to_face';
    case LiveOnline = 'live_online';
    case Hybrid = 'hybrid';
    case Workshop = 'workshop';
    case Exam = 'exam';
    case ReviewClass = 'review_class';
    case Orientation = 'orientation';
}
