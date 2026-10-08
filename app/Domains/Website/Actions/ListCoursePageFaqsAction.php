<?php

namespace App\Domains\Website\Actions;

class ListCoursePageFaqsAction
{
    /** The questions, in the order the page asks them; each has a `public.faq_{name}_q` and `_a`. */
    private const QUESTIONS = ['who', 'enroll', 'payment', 'refund', 'certificate', 'mode'];

    /**
     * FAQs rendered on the public course page, in the page's language (BACKLOG
     * C20, LT5a). JSON-LD FAQPage must match this list.
     *
     * @return list<array{q: string, a: string}>
     */
    public function execute(): array
    {
        return array_map(fn (string $name) => [
            'q' => __("public.faq_{$name}_q"),
            'a' => __("public.faq_{$name}_a"),
        ], self::QUESTIONS);
    }
}
