<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\Identity\Actions\ReadVerifiedUserContactsAction;
use App\Domains\People\Actions\HasActiveConsentAction;
use App\Domains\People\Actions\ResolveConsentPersonForUserAction;
use App\Domains\PrayerTimes\Models\PrayerRecipientGroup;

class FilterConsentedRecipientsAction
{
    /**
     * @param  list<array<string, mixed>>|null  $refs
     * @return array{included: list<array{type: string, id: int, phone: string}>, excluded: list<array{type: string, id: int, reason: string, phone?: string}>}
     */
    public function execute(?int $groupId, ?array $refs): array
    {
        $merged = $refs ?? [];
        if ($groupId) {
            $group = PrayerRecipientGroup::query()->find($groupId);
            if ($group && is_array($group->member_refs)) {
                $merged = array_merge($group->member_refs, $merged);
            }
        }

        $included = [];
        $excluded = [];
        $seen = [];

        foreach ($merged as $ref) {
            if (! is_array($ref)) {
                continue;
            }
            $type = (string) ($ref['type'] ?? 'user');
            $id = (int) ($ref['id'] ?? 0);
            if ($id < 1 || $type !== 'user') {
                $excluded[] = ['type' => $type, 'id' => $id, 'reason' => 'unsupported_ref'];

                continue;
            }
            if (isset($seen[$id])) {
                $excluded[] = ['type' => $type, 'id' => $id, 'reason' => 'duplicate'];

                continue;
            }
            $seen[$id] = true;

            $contacts = app(ReadVerifiedUserContactsAction::class)->execute($id);
            $phone = $ref['phone'] ?? $contacts['phone'];
            if (! is_string($phone) || $phone === '') {
                $excluded[] = ['type' => $type, 'id' => $id, 'reason' => 'no_phone'];

                continue;
            }

            $person = app(ResolveConsentPersonForUserAction::class)->execute($id);
            if ($person === null) {
                $excluded[] = ['type' => $type, 'id' => $id, 'reason' => 'no_consent_person', 'phone' => $phone];

                continue;
            }

            $ok = app(HasActiveConsentAction::class)->execute(
                $person['person_type'],
                $person['person_id'],
                'prayer_reminders',
            );
            if (! $ok) {
                $excluded[] = ['type' => $type, 'id' => $id, 'reason' => 'no_consent', 'phone' => $phone];

                continue;
            }

            $included[] = [
                'type' => 'user',
                'id' => $id,
                'phone' => $phone,
            ];
        }

        return ['included' => $included, 'excluded' => $excluded];
    }
}
