<?php

namespace App\Domains\Website\Actions;

use App\Domains\Media\Actions\StoreGeneratedPublicImageAction;
use App\Domains\Media\Contracts\ImageProcessorInterface;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;

class GenerateShareCardAction
{
    /**
     * Render a 1080×1080 PNG. Hadith collection, number, grading, and grading source
     * are always included in the line list (and therefore on the image spec).
     *
     * @return array{path: string, url: string, lines: list<string>}
     */
    public function execute(DailyContent $row): array
    {
        $presented = app(ListDailyContentsAction::class)->present($row);
        $built = $this->build($presented);
        $png = app(ImageProcessorInterface::class)->renderSquarePng(1080, $built['spec']);
        $path = 'daily-cards/'.$row->id.'.png';
        $stored = app(StoreGeneratedPublicImageAction::class)->execute(
            $png,
            $path,
            'daily-'.$row->id.'.png',
            'image/png',
            null,
            ['kind' => 'daily_share_card', 'daily_content_id' => $row->id],
        );

        $row->share_card_path = $stored['path'];
        $row->save();

        return [
            'path' => $stored['path'],
            'url' => $stored['url'],
            'lines' => $built['lines'],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{spec: array{background: string, lines: list<array<string, mixed>>}, lines: list<string>}
     */
    public function build(array $item): array
    {
        $type = DailyContentType::tryFrom((string) ($item['content_type'] ?? '')) ?? DailyContentType::Reminder;
        $lines = [];
        $specLines = [];
        $y = 88;
        $this->push($specLines, $lines, 'Akuru Institute', 'latin', 28, '#C9A227', $y);
        $y += 56;
        $this->push($specLines, $lines, $this->typeLabel($type), 'latin', 22, '#E8BC3C', $y);
        $y += 72;

        if ($type === DailyContentType::Ayah) {
            $arabic = trim((string) ($item['ayah']['text_uthmani'] ?? $item['ayah']['text_simple'] ?? ''));
            $en = trim((string) ($item['ayah']['meanings']['en'] ?? ''));
            $dv = trim((string) ($item['ayah']['meanings']['dv'] ?? ''));
            $this->push($specLines, $lines, $arabic, 'arabic', 42, '#F5E6C8', $y, 920);
            $y += 220;
            $this->push($specLines, $lines, $en, 'latin', 30, '#F5E6C8', $y, 920);
            $y += 200;
            $this->push($specLines, $lines, $dv, 'thaana', 30, '#F5E6C8', $y, 920);
            $y += 180;
            $surah = is_array($item['ayah']['surah'] ?? null) ? $item['ayah']['surah'] : [];
            $ref = trim((string) ($surah['english_name'] ?? ''));
            $sn = (int) ($item['ayah']['surah_number'] ?? 0);
            $an = (int) ($item['ayah']['ayah_number'] ?? 0);
            if ($sn > 0 && $an > 0) {
                $ref = trim($ref.' '.$sn.':'.$an);
            }
            $this->push($specLines, $lines, $ref, 'latin', 24, '#C9A227', 980);
        } elseif ($type === DailyContentType::Hadith) {
            $this->push($specLines, $lines, trim((string) ($item['hadith_text_ar'] ?? '')), 'arabic', 36, '#F5E6C8', $y, 920);
            $y += 180;
            $this->push($specLines, $lines, trim((string) ($item['hadith_text_en'] ?? '')), 'latin', 28, '#F5E6C8', $y, 920);
            $y += 160;
            $this->push($specLines, $lines, trim((string) ($item['hadith_text_dv'] ?? '')), 'thaana', 28, '#F5E6C8', $y, 920);
            $source = trim(implode(' ', array_filter([
                (string) ($item['hadith_collection'] ?? ''),
                (string) ($item['hadith_number'] ?? ''),
                (string) ($item['hadith_grading'] ?? ''),
                (string) ($item['grading_source'] ?? ''),
            ], fn (string $part): bool => trim($part) !== '')));
            $this->push($specLines, $lines, $source, 'latin', 24, '#C9A227', 980, 920);
        } else {
            $this->push($specLines, $lines, trim((string) ($item['text_ar'] ?? '')), 'arabic', 36, '#F5E6C8', $y, 920);
            $y += 160;
            $this->push($specLines, $lines, trim((string) ($item['text_en'] ?? '')), 'latin', 30, '#F5E6C8', $y, 920);
            $y += 160;
            $this->push($specLines, $lines, trim((string) ($item['text_dv'] ?? '')), 'thaana', 30, '#F5E6C8', $y, 920);
            $this->push($specLines, $lines, trim((string) ($item['attribution'] ?? '')), 'latin', 24, '#C9A227', 980, 920);
        }

        return [
            'spec' => [
                'background' => '#3D1219',
                'lines' => $specLines,
            ],
            'lines' => $lines,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $specLines
     * @param  list<string>  $lines
     */
    private function push(array &$specLines, array &$lines, string $text, string $font, int $size, string $color, int $y, int $wrap = 0): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }
        $lines[] = $text;
        $line = [
            'text' => $text,
            'font' => $font,
            'size' => $size,
            'color' => $color,
            'x' => 540,
            'y' => $y,
            'align' => 'center',
            'valign' => 'top',
        ];
        if ($wrap > 0) {
            $line['wrap'] = $wrap;
        }
        $specLines[] = $line;
    }

    private function typeLabel(DailyContentType $type): string
    {
        return match ($type) {
            DailyContentType::Ayah => 'Daily ayah',
            DailyContentType::Hadith => 'Daily hadith',
            DailyContentType::Saying => 'Daily saying',
            DailyContentType::Reminder => 'Daily reminder',
        };
    }
}
