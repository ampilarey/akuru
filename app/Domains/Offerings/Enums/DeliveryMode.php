<?php

namespace App\Domains\Offerings\Enums;

enum DeliveryMode: string
{
    case SelfLearning = 'self_learning';
    case FaceToFace = 'face_to_face';
    case LiveOnline = 'live_online';
    case Blended = 'blended';
    case Hybrid = 'hybrid';

    /**
     * SPEC §24 asks both the dashboard and the course learning page to show the
     * "Offering title/mode". The course page had been humanising the raw value
     * in JSX with `replaceAll('_', ' ')`, which reads "face to face" and cannot
     * ever read "Face-to-face". Two screens doing that separately is two
     * wordings; naming them here makes it one.
     */
    public function label(): string
    {
        return match ($this) {
            self::SelfLearning => 'Self-paced',
            self::FaceToFace => 'Face-to-face',
            self::LiveOnline => 'Live online',
            self::Blended => 'Blended',
            self::Hybrid => 'Hybrid',
        };
    }
}
