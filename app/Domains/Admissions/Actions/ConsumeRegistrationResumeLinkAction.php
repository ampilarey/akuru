<?php

namespace App\Domains\Admissions\Actions;

use App\Domains\Admissions\Models\RegistrationFlow;

/**
 * Spend a resume link (BACKLOG C16 slice N5). The link names a flow by its
 * uuid and carries a token; the token is accepted once — against its hash,
 * in constant time — while the flow has not expired and has not been
 * resumed before. Anything else is `null`, which the controller reports as
 * a link that is used or expired; it never says which.
 */
class ConsumeRegistrationResumeLinkAction
{
    public function execute(?string $uuid, ?string $token): ?RegistrationFlow
    {
        if (! is_string($uuid) || $uuid === '' || ! is_string($token) || $token === '') {
            return null;
        }

        $flow = RegistrationFlow::query()
            ->where('uuid', $uuid)
            ->whereNotNull('resume_token_hash')
            ->whereNull('resumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($flow === null || ! hash_equals((string) $flow->resume_token_hash, hash('sha256', $token))) {
            return null;
        }

        $flow->forceFill(['resumed_at' => now()])->save();

        return $flow->refresh();
    }
}
