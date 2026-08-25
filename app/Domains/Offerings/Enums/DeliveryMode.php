<?php

namespace App\Domains\Offerings\Enums;

enum DeliveryMode: string
{
    case SelfLearning = 'self_learning';
    case FaceToFace = 'face_to_face';
    case LiveOnline = 'live_online';
    case Blended = 'blended';
    case Hybrid = 'hybrid';
}
