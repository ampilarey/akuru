<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\CertificateTemplate;
use Illuminate\Support\Collection;

class ListCertificateTemplatesAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        return CertificateTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (CertificateTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'name_dv' => $template->name_dv,
                'name_ar' => $template->name_ar,
                'kind' => $template->kind->value,
                'course_id' => $template->course_id,
                'rules' => $template->rules ?? [],
                'body_html' => $template->body_html,
                'active' => (bool) $template->active,
            ])
            ->values();
    }
}
