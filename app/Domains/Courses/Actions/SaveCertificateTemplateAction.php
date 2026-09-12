<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Models\CertificateTemplate;
use Illuminate\Validation\ValidationException;

class SaveCertificateTemplateAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?CertificateTemplate $template = null): CertificateTemplate
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Template name is required.']);
        }

        $kind = CertificateKind::tryFrom((string) ($data['kind'] ?? ''));
        if ($kind === null) {
            throw ValidationException::withMessages(['kind' => 'Invalid certificate kind.']);
        }

        $payload = [
            'name' => $name,
            'name_dv' => $this->nullableString($data['name_dv'] ?? null),
            'name_ar' => $this->nullableString($data['name_ar'] ?? null),
            'kind' => $kind,
            'course_id' => $this->nullableId($data['course_id'] ?? null),
            'rules' => app(NormalizeCertificateRulesAction::class)->execute($data['rules'] ?? []),
            'body_html' => $this->sanitizedBody($data['body_html'] ?? null),
            'active' => (bool) ($data['active'] ?? true),
            'created_by' => $data['created_by'] ?? $template?->created_by,
        ];

        if ($template === null) {
            return CertificateTemplate::query()->create($payload);
        }

        $template->fill($payload);
        $template->save();

        return $template->refresh();
    }

    private function sanitizedBody(mixed $html): ?string
    {
        $body = trim((string) ($html ?? ''));
        if ($body === '') {
            return null;
        }

        $clean = strip_tags($body, '<p><br><strong><em><h1><h2><h3><span>');

        return $clean === '' ? null : $clean;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
