<?php

namespace App\Domains\Bookshop\Support;

/**
 * What a domain points at (slice B9f), for the office deciding whether a
 * shop's own domain is ready: its addresses and any alias. A class so a
 * test can stand in for the network.
 */
class HostDns
{
    /**
     * @return array{addresses: list<string>, aliases: list<string>}
     */
    public function lookup(string $host): array
    {
        $addresses = @gethostbynamel($host) ?: [];
        $aliases = [];
        foreach ((array) (@dns_get_record($host, DNS_CNAME) ?: []) as $record) {
            if (! empty($record['target'])) {
                $aliases[] = strtolower((string) $record['target']);
            }
        }

        return ['addresses' => array_values($addresses), 'aliases' => $aliases];
    }
}
