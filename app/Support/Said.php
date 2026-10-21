<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;
use JsonSerializable;
use Stringable;

/**
 * A phrase said later, in the reader's language (HP3, ADR-044).
 *
 * A notice is written once, when it is sent, but read by someone else in
 * their own language — a cart reminder from the nightly job read "Still
 * thinking it over?" to a family reading Dhivehi. `Said::of('shop.x', [...])`
 * keeps the phrase's key and its values; it reads as text wherever a string is
 * wanted (in the sender's language), and `in($locale)` says it again in any
 * other. A value that is itself a phrase — a state's name inside a sentence —
 * is a `Said` too, and is said in the same language as the sentence around it.
 */
final class Said implements JsonSerializable, Stringable
{
    /**
     * @param  array<string, scalar|Said|null>  $params
     * @param  list<Said|string>  $parts  a sentence followed by others, said together
     */
    private function __construct(public readonly string $key, public readonly array $params = [], public readonly array $parts = []) {}

    /**
     * @param  array<string, scalar|Said|null>  $params
     */
    public static function of(string $key, array $params = []): self
    {
        return new self($key, $params);
    }

    /**
     * Phrases said one after the other — a notice's sentence and the line a
     * case adds to it — each in the same language.
     *
     * @param  list<Said|string|null>  $parts
     */
    public static function join(array $parts): self
    {
        return new self('', [], array_values(array_filter($parts, fn ($part) => $part !== null && $part !== '')));
    }

    public function in(?string $locale = null): string
    {
        if ($this->parts !== []) {
            return implode(' ', array_map(fn ($part) => $part instanceof self ? $part->in($locale) : $part, $this->parts));
        }
        $params = array_map(fn ($value) => $value instanceof self ? $value->in($locale) : (string) ($value ?? ''), $this->params);

        return (string) __($this->key, $params, $locale);
    }

    public function __toString(): string
    {
        return $this->in();
    }

    /**
     * @return array{key: string, params: array<string, mixed>, parts?: list<mixed>}
     */
    public function toArray(): array
    {
        if ($this->parts !== []) {
            return ['key' => '', 'params' => [], 'parts' => array_map(fn ($part) => $part instanceof self ? ['said' => $part->toArray()] : $part, $this->parts)];
        }

        return [
            'key' => $this->key,
            'params' => array_map(fn ($value) => $value instanceof self ? ['said' => $value->toArray()] : $value, $this->params),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * A stored phrase back, or null when what was stored is not one, or its
     * key no longer exists — the caller then shows the text it kept.
     */
    public static function fromArray(mixed $stored): ?self
    {
        if (is_array($stored) && is_array($stored['parts'] ?? null) && $stored['parts'] !== []) {
            $parts = [];
            foreach ($stored['parts'] as $part) {
                $part = is_array($part) ? self::fromArray($part['said'] ?? null) : (is_string($part) ? $part : null);
                if ($part === null) {
                    return null;
                }
                $parts[] = $part;
            }

            return new self('', [], $parts);
        }
        if (! is_array($stored) || ! is_string($stored['key'] ?? null) || ! Lang::has($stored['key'])) {
            return null;
        }
        $params = [];
        foreach ((array) ($stored['params'] ?? []) as $name => $value) {
            if (is_array($value) && isset($value['said'])) {
                $value = self::fromArray($value['said']);
                if ($value === null) {
                    return null;
                }
            }
            $params[(string) $name] = is_array($value) ? null : $value;
        }

        return new self($stored['key'], $params);
    }
}
