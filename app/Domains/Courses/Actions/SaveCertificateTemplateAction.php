<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Support\Html\HtmlSanitizer;
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

    /**
     * The certificate body, sanitised through the shared allowlist.
     *
     * This was `strip_tags($body, '<p><br><strong><em><h1><h2><h3><span>')`,
     * which is the exact mistake `ValidateContentBlockDataAction` documents in
     * a comment written when it was fixed there: **`strip_tags` removes
     * disallowed tags and keeps every attribute on the ones it allows.** So
     * `<p onmouseover="…">` and `<h1 onclick="…">` came through untouched, and
     * `documents/course-certificate.blade.php` renders the stored value with
     * `{!! $body_html !!}` into an HTML document (ADR-012 — HTML is the
     * production output, not PDF).
     *
     * Certificate templates are writable by `course_creator`, the lowest
     * content-authoring role, and a certificate is opened by admins, students
     * and families. That is a privilege escalation, not a formatting bug.
     *
     * `<span>` is no longer in the allowlist and is unwrapped rather than
     * dropped, so its text survives. Nothing is lost: a `<span>` is only ever
     * useful with `style` or `class`, and the sanitiser strips both.
     */
    private function sanitizedBody(mixed $html): ?string
    {
        $body = trim((string) ($html ?? ''));
        if ($body === '') {
            return null;
        }

        $clean = app(HtmlSanitizer::class)->clean($body, HtmlSanitizer::PROFILE_CMS);

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
