<?php

namespace App\Domains\Website\Actions;

class ComposeFaqPageJsonLdAction
{
    /**
     * @param  list<array{q: string, a: string}>  $faqs
     * @return array<string, mixed>
     */
    public function execute(array $faqs): array
    {
        $entities = [];
        foreach ($faqs as $faq) {
            $question = trim((string) ($faq['q'] ?? ''));
            $answer = trim((string) ($faq['a'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }
            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($entities === []) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }
}
