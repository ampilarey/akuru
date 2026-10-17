<?php

namespace App\Domains\People\Enums;

enum ConsentType: string
{
    case PhotoMediaUse = 'photo_media_use';
    case AiTrainingSamples = 'ai_training_samples';
    case DataProcessing = 'data_processing';
    case MarketingMessages = 'marketing_messages';
    case PrayerReminders = 'prayer_reminders';
    // BLOG_PLAN BL1: a parent lets a child under 18 publish a blog.
    case BlogPublishing = 'blog_publishing';
}
