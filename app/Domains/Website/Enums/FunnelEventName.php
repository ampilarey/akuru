<?php

namespace App\Domains\Website\Enums;

enum FunnelEventName: string
{
    case CourseView = 'course_view';
    case RegisterClick = 'register_click';
    case RegistrationStarted = 'registration_started';
    case PaymentCompleted = 'payment_completed';
    case WhatsappClick = 'whatsapp_click';
    case SyllabusDownload = 'syllabus_download';

    /**
     * Client beacons may only record click intents. Server hooks own the rest.
     *
     * @return list<self>
     */
    public static function clientAllowed(): array
    {
        return [self::RegisterClick, self::WhatsappClick];
    }

    /**
     * @return list<string>
     */
    public static function clientAllowedValues(): array
    {
        return array_map(fn (self $name) => $name->value, self::clientAllowed());
    }
}
