<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\DTOs\PrayerTimesDTO;
use App\Support\Services\IslamicCalendarService;
use Carbon\Carbon;

class BuildPrayerSmsMessageAction
{
    /**
     * @param  array<string, mixed>  $template
     * @return array{en: string, dv: string, ar: string}
     */
    public function execute(PrayerTimesDTO $times, string $language = 'en', array $template = [], ?Carbon $from = null, ?Carbon $to = null): array
    {
        $hijri = IslamicCalendarService::gregorianToHijri($times->date ?: now());
        $range = $from && $to && $from->toDateString() !== $to->toDateString()
            ? $from->format('j M').'–'.$to->format('j M')
            : ($times->date ?: '');

        $replacements = [
            '{island}' => $times->nameEn,
            '{island_dv}' => $times->nameDv,
            '{date}' => $times->date,
            '{range}' => $range,
            '{hijri}' => $hijri['formatted'] ?? '',
            '{fajr}' => $times->fajr ?? '',
            '{sunrise}' => $times->sunrise ?? '',
            '{dhuhr}' => $times->dhuhr ?? '',
            '{asr}' => $times->asr ?? '',
            '{maghrib}' => $times->maghrib ?? '',
            '{isha}' => $times->isha ?? '',
        ];

        $en = $template['en'] ?? $this->defaultEn($range !== '' && $from && $to && $from->toDateString() !== $to->toDateString());
        $dv = $template['dv'] ?? $this->defaultDv();
        $ar = $template['ar'] ?? $this->defaultAr();

        $rendered = [
            'en' => strtr($en, $replacements),
            'dv' => strtr($dv, $replacements),
            'ar' => strtr($ar, $replacements),
        ];

        if (! in_array($language, ['en', 'dv', 'ar'], true)) {
            $language = 'en';
        }

        return $rendered + ['primary' => $rendered[$language]];
    }

    private function defaultEn(bool $range): string
    {
        $head = $range
            ? '{range} {island}: '
            : '{island} {date} ({hijri}): ';

        return $head.'Fajr {fajr}, Sunrise {sunrise}, Dhuhr {dhuhr}, Asr {asr}, Maghrib {maghrib}, Isha {isha}. Reply STOP to opt out.';
    }

    private function defaultDv(): string
    {
        return '{island_dv} {date}: ފަޖްރު {fajr} ސަންރައިޒް {sunrise} ދުހުރު {dhuhr} ޢަސްރު {asr} މަޣްރިބް {maghrib} ޢިޝާ {isha}. STOP';
    }

    private function defaultAr(): string
    {
        return '{island} {date}: الفجر {fajr} الشروق {sunrise} الظهر {dhuhr} العصر {asr} المغرب {maghrib} العشاء {isha}. STOP';
    }
}
