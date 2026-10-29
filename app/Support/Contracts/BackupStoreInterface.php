<?php

namespace App\Support\Contracts;

/**
 * Where a backup copy is kept (BK1, rule 4): this server's folder, or a
 * store off it. The backup code never names a storage SDK; it names this.
 */
interface BackupStoreInterface
{
    /** Copy a finished local file into the store under `$name`. */
    public function put(string $localPath, string $name): void;

    /** Copy `$name` out of the store to a local path. */
    public function get(string $name, string $localPath): void;

    /** @return list<string> the backups held, oldest first */
    public function names(): array;

    public function delete(string $name): void;

    public function size(string $name): ?int;

    /** Where this is, in words for the office (no secrets). */
    public function describe(): string;
}
