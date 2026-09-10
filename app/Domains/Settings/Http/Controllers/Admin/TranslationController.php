<?php

namespace App\Domains\Settings\Http\Controllers\Admin;

use App\Domains\Settings\Actions\ListTranslationCatalogAction;
use App\Domains\Settings\Actions\SaveTranslationOverrideAction;
use App\Domains\Settings\Actions\SuggestTranslationAction;
use App\Http\Controllers\Controller;
use App\Support\Contracts\MachineTranslatorInterface;
use App\Support\Translation\NullMachineTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Translation editor for Dhivehi and Arabic — thin: authorize (route
 * middleware) → validate → action → response.
 */
class TranslationController extends Controller
{
    public function index(Request $request, ListTranslationCatalogAction $list): Response
    {
        return Inertia::render('Settings/Translations', [
            ...$list->execute($this->locale($request)),
            'suggest_available' => ! (app(MachineTranslatorInterface::class) instanceof NullMachineTranslator),
        ]);
    }

    public function suggest(Request $request, SuggestTranslationAction $suggest): JsonResponse
    {
        $data = $this->validateKey($request);

        return response()->json($suggest->execute($data['group'], $data['key'], $data['locale']));
    }

    public function save(Request $request, SaveTranslationOverrideAction $save): RedirectResponse
    {
        $data = $this->validateKey($request, ['value' => 'nullable|string|max:2000']);

        $save->execute($data['group'], $data['key'], $data['value'] ?? null, (int) $request->user()->id, $data['locale']);

        // `back()` carries the ?locale= of the page that posted, so saving
        // an Arabic string does not bounce the editor to Dhivehi.
        return back();
    }

    public function export(Request $request, ListTranslationCatalogAction $list): StreamedResponse
    {
        $locale = $this->locale($request);
        $data = $list->execute($locale);
        $names = ['dv' => 'dhivehi', 'ar' => 'arabic'];

        return response()->streamDownload(function () use ($data, $locale) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['group', 'key', 'english', "file_{$locale}", "override_{$locale}", 'suspect']);
            foreach ($data['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    fputcsv($out, [
                        $group['group'],
                        $item['key'],
                        $item['en'],
                        $item['file_value'] ?? '',
                        $item['override'] ?? '',
                        $item['suspect'] ? 'yes' : 'no',
                    ]);
                }
            }
            fclose($out);
        }, ($names[$locale] ?? $locale).'-translations.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * An absent locale means Dhivehi, so every link and bookmark written
     * before Arabic existed still lands where it used to.
     */
    private function locale(Request $request): string
    {
        $locale = $request->query('locale');

        return is_string($locale) && in_array($locale, ListTranslationCatalogAction::locales(), true)
            ? $locale
            : ListTranslationCatalogAction::DEFAULT_LOCALE;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function validateKey(Request $request, array $extra = []): array
    {
        $data = $request->validate([
            'group' => 'required|string|max:40',
            'key' => 'required|string|max:191',
            'locale' => ['nullable', 'string', Rule::in(ListTranslationCatalogAction::locales())],
            ...$extra,
        ]);

        $data['locale'] ??= ListTranslationCatalogAction::DEFAULT_LOCALE;

        return $data;
    }
}
