<?php

namespace App\Domains\Bookshop\Actions\Shop;

/**
 * "Aishath M." — a customer's first name and the initial of the last, as
 * reviews and questions show them (never a full name, island or phone).
 * Through the auth model from config, so Bookshop never imports Identity's
 * (rule 3).
 */
final class CustomerNames
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public static function short(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $userModel = config('auth.providers.users.model');

        return $userModel::query()->whereIn('id', array_unique($userIds))->pluck('name', 'id')
            ->map(function ($name) {
                $parts = preg_split('/\s+/', trim((string) $name)) ?: [];
                $first = $parts[0] ?? '';
                $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1).'.' : '';

                return trim($first.' '.$last);
            })->all();
    }
}
