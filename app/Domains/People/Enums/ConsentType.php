<?php

namespace App\Domains\People\Enums;

enum ConsentType: string
{
    case PhotoMediaUse = 'photo_media_use';
    case AiTrainingSamples = 'ai_training_samples';
    case DataProcessing = 'data_processing';
    case MarketingMessages = 'marketing_messages';
    case PrayerReminders = 'prayer_reminders';
}
