<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastRecipientStatus;
use App\Domains\PrayerTimes\Enums\PrayerBroadcastStatus;
use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use App\Domains\PrayerTimes\Models\PrayerBroadcastRecipient;

class SendPrayerBroadcastAction
{
    public function execute(PrayerBroadcast $broadcast): PrayerBroadcast
    {
        if (in_array($broadcast->status, [PrayerBroadcastStatus::Completed, PrayerBroadcastStatus::Cancelled], true)) {
            return $broadcast;
        }

        $broadcast->status = PrayerBroadcastStatus::Sending;
        $broadcast->save();

        $sender = app(SmsSenderInterface::class);
        $sent = 0;
        $failed = 0;

        $pending = PrayerBroadcastRecipient::query()
            ->where('prayer_broadcast_id', $broadcast->id)
            ->where('status', PrayerBroadcastRecipientStatus::Pending)
            ->get();

        foreach ($pending as $recipient) {
            try {
                $result = $sender->sendSms((string) $recipient->phone, (string) $recipient->message_body, [
                    'type' => 'prayer_broadcast',
                    'reference' => 'prayer-broadcast-'.$broadcast->id.'-'.$recipient->id,
                ]);
                $ok = (bool) ($result['success'] ?? false);
                $recipient->status = $ok ? PrayerBroadcastRecipientStatus::Sent : PrayerBroadcastRecipientStatus::Failed;
                $recipient->sent_at = $ok ? now() : null;
                $recipient->error = $ok ? null : (string) ($result['error'] ?? 'send_failed');
                $recipient->save();
                $ok ? $sent++ : $failed++;
            } catch (\Throwable $e) {
                $recipient->status = PrayerBroadcastRecipientStatus::Failed;
                $recipient->error = $e->getMessage();
                $recipient->save();
                $failed++;
            }
        }

        $broadcast->sent_count = (int) $broadcast->sent_count + $sent;
        $broadcast->failed_count = (int) $broadcast->failed_count + $failed;
        $broadcast->status = $failed > 0 && $sent === 0
            ? PrayerBroadcastStatus::Failed
            : PrayerBroadcastStatus::Completed;
        $broadcast->save();

        return $broadcast->fresh();
    }
}
