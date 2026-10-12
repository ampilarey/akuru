<?php

namespace App\Domains\Bookshop\Actions\Print;

use App\Domains\Bookshop\Models\PrintLayout;
use App\Domains\Bookshop\Models\PrintTemplate;
use App\Domains\Bookshop\Support\PrintDesign;
use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §4.7 (NT2): a template saved by the office — its design
 * validated and normalised — and its state moved: draft → active → retired,
 * never deleted. An active template needs at least one field, or nothing
 * on it is the customer's.
 */
class SavePrintTemplateAction
{
    /** @param  array<string, mixed>  $data */
    public function execute(array $data, ?PrintTemplate $template, int $userId): PrintTemplate
    {
        $kind = (string) ($data['kind'] ?? 'name_tag');
        if (! in_array($kind, PrintTemplate::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('print.error_template_kind')]);
        }
        $layout = isset($data['print_layout_id']) && $data['print_layout_id'] !== '' ? PrintLayout::query()->find((int) $data['print_layout_id']) : null;
        $raw = $data['design'] ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['design' => __('print.error_design_missing')]);
        }
        $design = PrintDesign::validate($raw);

        $template ??= new PrintTemplate(['created_by' => $userId, 'status' => 'draft']);
        $template->fill([
            'title' => trim((string) $data['title']),
            'title_dv' => trim((string) ($data['title_dv'] ?? '')) ?: null,
            'title_ar' => trim((string) ($data['title_ar'] ?? '')) ?: null,
            'kind' => $kind,
            'print_layout_id' => $layout?->id,
            'design' => $design,
            'sort_order' => (int) ($data['sort_order'] ?? $template->sort_order ?? 0),
        ]);
        $template->save();

        return $template;
    }

    public function setStatus(PrintTemplate $template, string $status): PrintTemplate
    {
        if (! in_array($status, PrintTemplate::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('print.error_template_status')]);
        }
        if ($status === 'active' && PrintDesign::fieldKeys((array) $template->design) === []) {
            throw ValidationException::withMessages(['status' => __('print.error_template_no_fields')]);
        }
        $template->status = $status;
        $template->save();

        return $template;
    }
}
