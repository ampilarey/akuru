<?php

namespace App\Domains\Media\Actions;

use App\Domains\Media\Models\Document;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ListExpiringDocumentsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $withinDays = 90): Collection
    {
        $until = Carbon::now('Indian/Maldives')->addDays($withinDays ?? 90)->toDateString();

        return Document::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $until)
            ->orderBy('expires_at')
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'documentable_type' => $document->documentable_type,
                'documentable_id' => $document->documentable_id,
                'document_type' => $document->document_type instanceof \BackedEnum
                    ? $document->document_type->value
                    : (string) $document->document_type,
                'title' => $document->title,
                'expires_at' => $document->expires_at?->toDateString(),
                'days_until' => (int) Carbon::now('Indian/Maldives')->startOfDay()
                    ->diffInDays($document->expires_at?->copy()->startOfDay(), false),
            ])
            ->values();
    }
}
