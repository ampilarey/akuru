<?php

namespace App\Support\Pdf;

/**
 * A PDF file as a map of objects, read without trusting its cross-reference
 * table.
 *
 * Instead of following `startxref` — which is wrong in a meaningful share of
 * real files (edited by hand, appended to, truncated by a bad upload) — the
 * whole file is scanned for `N G obj` headers, and object streams
 * (`/Type /ObjStm`, the compressed containers PDF 1.5 producers put most
 * objects into) are opened and their contents registered too. Where an object
 * number is defined more than once, the definition latest in the file wins,
 * which is what an incremental update means. Objects are parsed lazily and
 * cached.
 */
final class PdfDocument
{
    /**
     * @var array<int, array{order: int, at: int, stream: int|null, hint: string}>
     *                                                                             objnum → where it lives: `at` is an offset into the file (top-level)
     *                                                                             or into the decoded object stream `stream`.
     */
    private array $definitions = [];

    /** @var array<int, mixed> */
    private array $cache = [];

    /** @var array<int, string|null> decoded object-stream contents */
    private array $objectStreams = [];

    /** @var array<int, true> */
    private array $resolving = [];

    private function __construct(private readonly string $bytes) {}

    public static function parse(string $bytes): self
    {
        $document = new self($bytes);
        $document->scan();

        return $document;
    }

    private function scan(): void
    {
        preg_match_all('/(?<![0-9])(\d+)[ \t\r\n\f\0]+(\d+)[ \t\r\n\f\0]+obj\b/', $this->bytes, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $objectStreamNumbers = [];
        foreach ($matches as $match) {
            $number = (int) $match[1][0];
            $order = $match[0][1];
            $at = $match[0][1] + strlen($match[0][0]);
            $hint = substr($this->bytes, $at, 400);
            $this->define($number, $order, $at, null, $hint);
            if (preg_match('#/Type\s*/ObjStm\b#', $hint) === 1) {
                $objectStreamNumbers[] = $number;
            }
        }

        foreach ($objectStreamNumbers as $number) {
            $this->registerObjectStream($number);
        }
    }

    private function define(int $number, int $order, int $at, ?int $stream, string $hint): void
    {
        if (isset($this->definitions[$number]) && $this->definitions[$number]['order'] > $order) {
            return;
        }
        $this->definitions[$number] = ['order' => $order, 'at' => $at, 'stream' => $stream, 'hint' => $hint];
        unset($this->cache[$number]);
    }

    private function registerObjectStream(int $number): void
    {
        $stream = $this->get($number);
        if (! $stream instanceof PdfStream) {
            return;
        }
        $data = $stream->decoded();
        if ($data === null) {
            return;
        }
        $this->objectStreams[$number] = $data;
        $count = (int) $this->resolve($stream->dict->get('N'));
        $first = (int) $this->resolve($stream->dict->get('First'));
        $order = $this->definitions[$number]['order'] ?? 0;

        $lexer = new PdfLexer($data, 0, $first > 0 ? $first : strlen($data));
        for ($i = 0; $i < $count; $i++) {
            [$t1, $objectNumber] = $lexer->next();
            [$t2, $offset] = $lexer->next();
            if ($t1 !== 'num' || $t2 !== 'num') {
                break;
            }
            $at = $first + (int) $offset;
            $this->define((int) $objectNumber, $order, $at, $number, substr($data, $at, 400));
        }
    }

    public function get(int $number): mixed
    {
        if (array_key_exists($number, $this->cache)) {
            return $this->cache[$number];
        }
        $definition = $this->definitions[$number] ?? null;
        if ($definition === null || isset($this->resolving[$number])) {
            return null;
        }
        $this->resolving[$number] = true;
        try {
            $value = $definition['stream'] === null
                ? $this->parseTopLevel($definition['at'])
                : $this->parseInStream($definition['stream'], $definition['at']);
        } catch (\Throwable) {
            $value = null;
        } finally {
            unset($this->resolving[$number]);
        }

        return $this->cache[$number] = $value;
    }

    /** Follow references until a direct object comes back. */
    public function resolve(mixed $value): mixed
    {
        $hops = 0;
        while ($value instanceof PdfRef && $hops++ < 64) {
            $value = $this->get($value->number);
        }

        return $value instanceof PdfRef ? null : $value;
    }

    /** A resolved dictionary entry, or the stream's dictionary entry. */
    public function entry(mixed $container, string $key): mixed
    {
        $container = $this->resolve($container);
        if ($container instanceof PdfStream) {
            $container = $container->dict;
        }

        return $container instanceof PdfDict ? $this->resolve($container->get($key)) : null;
    }

    private function parseTopLevel(int $at): mixed
    {
        $lexer = new PdfLexer($this->bytes, $at);
        $value = PdfParser::parse($lexer);
        if (! $value instanceof PdfDict) {
            return $value;
        }

        $save = $lexer->pos;
        [$type, $keyword] = $lexer->next();
        if ($type !== 'kw' || $keyword !== 'stream') {
            $lexer->pos = $save;

            return $value;
        }

        // Stream data begins after `stream` and exactly one EOL.
        $start = $lexer->pos;
        if (($this->bytes[$start] ?? '') === "\r") {
            $start++;
        }
        if (($this->bytes[$start] ?? '') === "\n") {
            $start++;
        }

        $length = $this->resolve($value->get('Length'));
        $length = is_int($length) || is_float($length) ? (int) $length : -1;
        if ($length >= 0 && $this->endstreamFollows($start + $length)) {
            return new PdfStream($value, substr($this->bytes, $start, $length), $this);
        }

        // /Length is wrong or indirect through something broken: fall back to
        // the first `endstream` after the data, trimming its EOL.
        $end = strpos($this->bytes, 'endstream', $start);
        if ($end === false) {
            $end = strlen($this->bytes);
        }
        $raw = substr($this->bytes, $start, $end - $start);
        if (str_ends_with($raw, "\r\n")) {
            $raw = substr($raw, 0, -2);
        } elseif (str_ends_with($raw, "\n") || str_ends_with($raw, "\r")) {
            $raw = substr($raw, 0, -1);
        }

        return new PdfStream($value, $raw, $this);
    }

    private function endstreamFollows(int $offset): bool
    {
        if ($offset > strlen($this->bytes)) {
            return false;
        }
        $window = substr($this->bytes, $offset, 16);

        return preg_match('/^[ \t\r\n\f\0]*endstream/', $window) === 1;
    }

    private function parseInStream(int $streamNumber, int $at): mixed
    {
        $data = $this->objectStreams[$streamNumber] ?? null;
        if ($data === null) {
            return null;
        }

        return PdfParser::parse(new PdfLexer($data, $at));
    }

    /** The document catalog, found through the trailer or by its type. */
    public function catalog(): ?PdfDict
    {
        $rootRef = null;

        // Classic trailers, last one wins.
        if (preg_match_all('/trailer\s*<</', $this->bytes, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as $match) {
                $lexer = new PdfLexer($this->bytes, $match[1] + strlen('trailer'));
                $dict = PdfParser::parse($lexer);
                if ($dict instanceof PdfDict && $dict->get('Root') instanceof PdfRef) {
                    $rootRef = $dict->get('Root');
                }
            }
        }

        // Cross-reference streams carry the trailer entries in their dictionary.
        $latest = -1;
        foreach ($this->definitions as $number => $definition) {
            if ($definition['stream'] === null && preg_match('#/Type\s*/XRef\b#', $definition['hint']) === 1 && $definition['order'] > $latest) {
                $stream = $this->get($number);
                if ($stream instanceof PdfStream && $stream->dict->get('Root') instanceof PdfRef) {
                    $rootRef = $stream->dict->get('Root');
                    $latest = $definition['order'];
                }
            }
        }

        $catalog = $rootRef === null ? null : $this->resolve($rootRef);
        if ($catalog instanceof PdfDict && $catalog->has('Pages')) {
            return $catalog;
        }

        foreach ($this->definitions as $number => $definition) {
            if (preg_match('#/Type\s*/Catalog\b#', $definition['hint']) === 1) {
                $candidate = $this->get($number);
                if ($candidate instanceof PdfDict && $candidate->has('Pages')) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * The pages in reading order, each with the resources it inherits.
     *
     * @return list<array{dict: PdfDict, resources: PdfDict|null}>
     */
    public function pages(): array
    {
        $pages = [];
        $catalog = $this->catalog();
        if ($catalog !== null) {
            $visited = [];
            $this->walkPageTree($catalog->get('Pages'), null, $pages, $visited, 0);
        }
        if ($pages !== []) {
            return $pages;
        }

        // No usable tree: every page object, in object-number order.
        $numbers = [];
        foreach ($this->definitions as $number => $definition) {
            if (preg_match('#/Type\s*/Page\b#', $definition['hint']) === 1) {
                $numbers[] = $number;
            }
        }
        sort($numbers);
        foreach ($numbers as $number) {
            $dict = $this->get($number);
            if ($dict instanceof PdfDict && $dict->name('Type') === 'Page') {
                $pages[] = ['dict' => $dict, 'resources' => $this->inheritedResources($dict)];
            }
        }

        return $pages;
    }

    /**
     * @param  list<array{dict: PdfDict, resources: PdfDict|null}>  $pages
     * @param  array<int, true>  $visited
     */
    private function walkPageTree(mixed $node, ?PdfDict $resources, array &$pages, array &$visited, int $depth): void
    {
        if ($depth > 64 || count($pages) > 20000) {
            return;
        }
        if ($node instanceof PdfRef) {
            if (isset($visited[$node->number])) {
                return;
            }
            $visited[$node->number] = true;
        }
        $dict = $this->resolve($node);
        if (! $dict instanceof PdfDict) {
            return;
        }
        $own = $this->resolve($dict->get('Resources'));
        if ($own instanceof PdfDict) {
            $resources = $own;
        }
        $kids = $this->resolve($dict->get('Kids'));
        $type = $dict->name('Type');
        if ($type === 'Page' || ($type !== 'Pages' && ! is_array($kids))) {
            if ($type === 'Page' || $dict->has('Contents')) {
                $pages[] = ['dict' => $dict, 'resources' => $resources];
            }

            return;
        }
        foreach (is_array($kids) ? $kids : [] as $kid) {
            $this->walkPageTree($kid, $resources, $pages, $visited, $depth + 1);
        }
    }

    private function inheritedResources(PdfDict $page): ?PdfDict
    {
        $node = $page;
        $hops = 0;
        while ($node instanceof PdfDict && $hops++ < 64) {
            $resources = $this->resolve($node->get('Resources'));
            if ($resources instanceof PdfDict) {
                return $resources;
            }
            $node = $this->resolve($node->get('Parent'));
        }

        return null;
    }
}
