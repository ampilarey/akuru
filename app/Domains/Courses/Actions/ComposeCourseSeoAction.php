<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;
use App\Domains\Settings\Actions\GetSettingAction;

class ComposeCourseSeoAction
{
    /**
     * Public course sharing payload: schema.org Course + CourseInstance, plus OG/Twitter fields.
     *
     * @return array{
     *     json_ld: array<string, mixed>,
     *     og: array{
     *         title: string,
     *         description: string,
     *         image: string,
     *         url: string,
     *         type: string,
     *         price_amount: ?string,
     *         price_currency: ?string
     *     }
     * }
     */
    public function execute(int $courseId): array
    {
        $course = Course::query()->find($courseId);
        if ($course === null) {
            return [
                'json_ld' => [],
                'og' => [
                    'title' => (string) config('app.name', 'Akuru Institute'),
                    'description' => '',
                    'image' => asset('images/og-default.jpg'),
                    'url' => url('/'),
                    'type' => 'website',
                    'price_amount' => null,
                    'price_currency' => null,
                ],
            ];
        }

        $signals = app(ComposeCourseConversionSignalsAction::class)->execute($courseId) ?? [];
        $url = route('public.courses.show', $course);
        $title = trim((string) $course->title);
        $description = trim(strip_tags((string) ($course->short_desc ?? '')));
        $image = $this->absoluteCover($course->cover_image);
        $price = $this->offerPrice($course, $signals);
        $currency = $price === null
            ? null
            : (string) ($signals['early_bird']['currency'] ?? $course->registration_fee_currency ?? 'MVR');

        $instance = [
            '@type' => 'CourseInstance',
            'name' => $title !== '' ? $title : 'Course',
        ];
        if ($course->start_date) {
            $instance['startDate'] = $course->start_date->toDateString();
        }
        if ($course->end_date) {
            $instance['endDate'] = $course->end_date->toDateString();
        }

        $address = trim((string) app(GetSettingAction::class)->execute('address', 'Malé, Republic of Maldives'));
        if ($address !== '') {
            $instance['location'] = [
                '@type' => 'Place',
                'name' => (string) config('app.name', 'Akuru Institute'),
                'address' => $address,
            ];
        }

        $offer = $this->offer($price, $currency, $url, $signals);
        if ($offer !== null) {
            $instance['offers'] = $offer;
        }

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $title !== '' ? $title : 'Course',
            'description' => $description,
            'url' => $url,
            'provider' => [
                '@type' => 'Organization',
                'name' => (string) config('app.name', 'Akuru Institute'),
                'url' => rtrim((string) config('app.url'), '/'),
            ],
            'hasCourseInstance' => $instance,
        ];
        if ($image !== '') {
            $jsonLd['image'] = $image;
        }
        if ($offer !== null) {
            $jsonLd['offers'] = $offer;
        }

        return [
            'json_ld' => $jsonLd,
            'og' => [
                'title' => $title !== '' ? $title : (string) config('app.name', 'Akuru Institute'),
                'description' => $description,
                'image' => $image !== '' ? $image : asset('images/og-default.jpg'),
                'url' => $url,
                'type' => 'website',
                'price_amount' => $price,
                'price_currency' => $currency,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $signals
     * @return array<string, mixed>|null
     */
    private function offer(?string $price, ?string $currency, string $url, array $signals): ?array
    {
        if ($price === null || $currency === null || $currency === '') {
            return null;
        }

        $offer = [
            '@type' => 'Offer',
            'price' => $price,
            'priceCurrency' => $currency,
            'url' => $url,
        ];

        $tone = $signals['seats_tone'] ?? null;
        if ($tone === 'full') {
            $offer['availability'] = 'https://schema.org/SoldOut';
        } elseif (($signals['seats_remaining'] ?? null) !== null) {
            $offer['availability'] = 'https://schema.org/InStock';
        }

        return $offer;
    }

    /**
     * @param  array<string, mixed>  $signals
     */
    private function offerPrice(Course $course, array $signals): ?string
    {
        $early = $signals['early_bird']['amount'] ?? null;
        if (is_numeric($early) && (float) $early > 0) {
            return number_format((float) $early, 2, '.', '');
        }

        if ($course->fee === null || $course->fee === '') {
            return null;
        }

        $fee = (float) $course->fee;
        if ($fee < 0) {
            return null;
        }

        return number_format($fee, 2, '.', '');
    }

    private function absoluteCover(mixed $cover): string
    {
        $cover = trim((string) $cover);
        if ($cover === '') {
            return asset('images/og-default.jpg');
        }
        if (str_starts_with($cover, 'http://') || str_starts_with($cover, 'https://')) {
            return $cover;
        }
        if (str_starts_with($cover, '/')) {
            return url($cover);
        }

        return asset('storage/'.$cover);
    }
}
