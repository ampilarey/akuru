<?php

namespace App\Support\Services\Backup;

use RuntimeException;

/**
 * BK1: a backup is encrypted before it is kept anywhere — it holds every
 * family's records and every wallet. libsodium's secretstream
 * (XChaCha20-Poly1305) in 1 MB chunks, so a large file never sits in memory
 * and a cut or altered file is refused rather than half-restored. The key
 * is the passphrase stretched with Argon2id and a fresh salt per file.
 *
 * File: "AKB1" · salt (16) · stream header (24) · chunks of [length (4) · ciphertext].
 */
final class BackupCipher
{
    private const MAGIC = 'AKB1';

    private const CHUNK = 1048576;

    public static function encrypt(string $in, string $out, string $passphrase): void
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = self::key($passphrase, $salt);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $src = fopen($in, 'rb');
        $dst = fopen($out, 'wb');
        if ($src === false || $dst === false) {
            throw new RuntimeException('Could not open the backup to encrypt it.');
        }
        try {
            self::write($dst, self::MAGIC.$salt.$header);
            $chunk = fread($src, self::CHUNK);
            do {
                $next = fread($src, self::CHUNK);
                $last = $next === '' || $next === false;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, (string) $chunk, '', $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                self::write($dst, pack('N', strlen($cipher)).$cipher);
                $chunk = $next;
            } while (! $last);
        } finally {
            fclose($src);
            fclose($dst);
            sodium_memzero($key);
        }
    }

    public static function decrypt(string $in, string $out, string $passphrase): void
    {
        $src = fopen($in, 'rb');
        if ($src === false || fread($src, 4) !== self::MAGIC) {
            throw new RuntimeException('This is not an Akuru backup.');
        }
        $salt = fread($src, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $header = fread($src, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $key = self::key($passphrase, (string) $salt);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull((string) $header, $key);
        $dst = fopen($out, 'wb');
        $finished = false;
        try {
            while (! $finished && ($length = fread($src, 4)) !== '' && $length !== false) {
                $cipher = fread($src, unpack('N', $length)[1]);
                $opened = sodium_crypto_secretstream_xchacha20poly1305_pull($state, (string) $cipher);
                if ($opened === false) {
                    throw new RuntimeException('The backup could not be opened: the passphrase is wrong, or the file was altered.');
                }
                [$plain, $tag] = $opened;
                self::write($dst, $plain);
                $finished = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
            if (! $finished) {
                throw new RuntimeException('The backup ends early: the file is cut short.');
            }
        } finally {
            fclose($src);
            fclose($dst);
            sodium_memzero($key);
        }
    }

    /** A write that does not land whole stops the backup — a full disk must never leave a short file that looks finished. */
    private static function write($handle, string $bytes): void
    {
        $written = @fwrite($handle, $bytes);
        if ($written !== strlen($bytes)) {
            throw new RuntimeException('Could not write the backup: the disk may be full.');
        }
    }

    private static function key(string $passphrase, string $salt): string
    {
        if ($passphrase === '') {
            throw new RuntimeException('No backup passphrase: set BACKUP_PASSPHRASE, or APP_KEY.');
        }

        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $passphrase,
            $salt,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }
}
