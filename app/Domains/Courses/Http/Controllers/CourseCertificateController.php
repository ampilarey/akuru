<?php

namespace App\Domains\Courses\Http\Controllers;

use App\Domains\Courses\Actions\IssueCertificateAction;
use App\Domains\Courses\Actions\ListCertificateIssueOptionsAction;
use App\Domains\Courses\Actions\ListCertificateTemplatesAction;
use App\Domains\Courses\Actions\ListIssuedCertificatesAction;
use App\Domains\Courses\Actions\RevokeIssuedCertificateAction;
use App\Domains\Courses\Actions\SaveCertificateTemplateAction;
use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\IssuedCertificate;
use App\Domains\Media\Actions\ReadGeneratedDocumentAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseCertificateController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        $yearId = $request->integer('academic_year_id') ?: null;

        return Inertia::render('Courses/Catalog/Certificates', [
            'templates' => app(ListCertificateTemplatesAction::class)->execute()->values(),
            'issued' => app(ListIssuedCertificatesAction::class)->execute([
                'academic_year_id' => $yearId,
            ])->values(),
            'kinds' => collect(CertificateKind::cases())->map(fn (CertificateKind $kind) => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ])->all(),
            ...app(ListCertificateIssueOptionsAction::class)->execute(),
            'filters' => [
                'academic_year_id' => $yearId,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        app(SaveCertificateTemplateAction::class)->execute(
            $this->validated($request) + ['created_by' => $request->user()?->id],
        );

        return redirect()->route('catalog.certificates.index')->with('success', 'Certificate template saved.');
    }

    public function update(Request $request, CertificateTemplate $template): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        app(SaveCertificateTemplateAction::class)->execute($this->validated($request), $template);

        return redirect()->route('catalog.certificates.index')->with('success', 'Certificate template updated.');
    }

    public function issue(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        $certificate = app(IssueCertificateAction::class)->execute(
            $request->validate([
                'certificate_template_id' => ['required', 'integer', 'exists:certificate_templates,id'],
                'student_id' => ['required', 'integer', 'exists:students,id'],
                'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
                'term_id' => ['nullable', 'integer', 'exists:terms,id'],
                'course_id' => ['nullable', 'integer', 'exists:courses,id'],
                'course_offering_id' => ['nullable', 'integer', 'exists:course_offerings,id'],
                'grade' => ['nullable', 'string', 'max:32'],
                'completion_date' => ['nullable', 'date'],
                'teacher_approved' => ['sometimes', 'boolean'],
                'assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            ]),
            (int) $request->user()->id,
        );

        return redirect()->route('catalog.certificates.index')
            ->with('success', 'Certificate issued: '.$certificate->certificate_number);
    }

    public function revoke(Request $request, IssuedCertificate $certificate): RedirectResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        app(RevokeIssuedCertificateAction::class)->execute($certificate->id);

        return redirect()->route('catalog.certificates.index')->with('success', 'Certificate revoked.');
    }

    public function download(Request $request, IssuedCertificate $certificate): HttpResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);
        abort_unless($certificate->document_id, 404);

        $file = app(ReadGeneratedDocumentAction::class)->execute((int) $certificate->document_id);

        return response($file['contents'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => 'inline; filename="'.$certificate->certificate_number.'.html"',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('courses.manage'), 403);

        $rows = app(ListIssuedCertificatesAction::class)->execute([
            'academic_year_id' => $request->integer('academic_year_id') ?: null,
        ]);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'certificate_number',
                'status',
                'student',
                'course',
                'offering',
                'issued_at',
                'public_id',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['certificate_number'],
                    $row['status'],
                    $row['student_name'],
                    $row['course_name'],
                    $row['offering_name'],
                    $row['issued_at'],
                    $row['public_id'],
                ]);
            }
            fclose($out);
        }, 'issued-certificates.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'name_dv' => ['nullable', 'string', 'max:191'],
            'name_ar' => ['nullable', 'string', 'max:191'],
            'kind' => ['required', Rule::enum(CertificateKind::class)],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'body_html' => ['nullable', 'string', 'max:20000'],
            'rules' => ['nullable', 'array'],
            'rules.min_progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rules.min_attendance_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rules.min_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rules.assessment_id' => ['nullable', 'integer', 'exists:assessments,id'],
            'rules.require_final_assessment' => ['sometimes', 'boolean'],
            'rules.require_teacher_approval' => ['sometimes', 'boolean'],
            'rules.require_payment' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
