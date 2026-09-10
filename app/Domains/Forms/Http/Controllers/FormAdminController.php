<?php

namespace App\Domains\Forms\Http\Controllers;

use App\Domains\Forms\Actions\ListFormResponsesAction;
use App\Domains\Forms\Actions\SaveFormAction;
use App\Domains\Forms\Enums\FormFieldType;
use App\Domains\Forms\Models\Form;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Forms/Index', [
            'forms' => Form::query()->orderByDesc('id')->get()
                ->map(fn (Form $form): array => [
                    'id' => (int) $form->id,
                    'title' => (string) $form->title,
                    'is_published' => (bool) $form->is_published,
                    'is_open' => $form->isOpen(),
                    'is_anonymous' => (bool) $form->is_anonymous,
                    'requires_parent_confirmation' => (bool) $form->requires_parent_confirmation,
                    'fee_amount' => $form->hasFee() ? (float) $form->fee_amount : null,
                    'responses' => $form->responses()->count(),
                ]),
            'fieldTypes' => array_map(fn (FormFieldType $t): string => $t->value, FormFieldType::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        app(SaveFormAction::class)->execute($this->validated($request), (int) $request->user()->id);

        return redirect()->route('forms.index')->with('success', 'Form saved.');
    }

    public function update(Request $request, Form $form): RedirectResponse
    {
        $this->authorizeManage($request);

        app(SaveFormAction::class)->execute($this->validated($request), (int) $request->user()->id, $form);

        return redirect()->route('forms.index')->with('success', 'Form updated.');
    }

    public function results(Request $request, Form $form): Response
    {
        $this->authorizeManage($request);

        return Inertia::render('Forms/Results', app(ListFormResponsesAction::class)->execute((int) $form->id));
    }

    public function export(Request $request, Form $form): StreamedResponse
    {
        $this->authorizeManage($request);

        $payload = app(ListFormResponsesAction::class)->execute((int) $form->id);
        $fields = $payload['form']['fields'];
        $anonymous = $payload['form']['is_anonymous'];

        return response()->streamDownload(function () use ($payload, $fields, $anonymous): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(
                $anonymous ? ['submitted_at'] : ['respondent', 'submitted_at'],
                array_map(fn (array $f): string => $f['label'], $fields),
            ));
            foreach ($payload['rows'] as $row) {
                $answers = array_map(function (array $f) use ($row) {
                    $value = $row['answers'][$f['key']] ?? '';

                    return is_array($value) ? implode('; ', $value) : $value;
                }, $fields);
                fputcsv($out, array_merge(
                    $anonymous ? [$row['submitted_at']] : [$row['respondent'] ?? '', $row['submitted_at']],
                    $answers,
                ));
            }
            fclose($out);
        }, 'form-'.$form->id.'-responses.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'fields' => ['required', 'array', 'min:1', 'max:30'],
            'fields.*.key' => ['nullable', 'string', 'max:40'],
            'fields.*.label' => ['required', 'string', 'max:200'],
            'fields.*.type' => ['required', 'string'],
            'fields.*.options' => ['nullable', 'array', 'max:20'],
            'fields.*.options.*' => ['nullable', 'string', 'max:100'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'target_audience' => ['nullable', 'array'],
            'target_classes' => ['nullable', 'array'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'requires_parent_confirmation' => ['sometimes', 'boolean'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->can('forms.manage'), 403);
    }
}
