<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Enums\ContentBlockType;

/**
 * SPEC §15.3 "Text Direction Rule":
 *
 *   > Remove `RTL Text Block` as a separate block type.
 *   >
 *   > Text direction and content language are settings on every text-capable
 *   > block, not separate block types.
 *   >
 *   > Text-capable blocks must support settings such as:
 *   > - Content language
 *   > - Direction: LTR, RTL, or auto
 *   > - Text alignment using start/end
 *   > - Optional font preference
 *   >
 *   > Arabic and Dhivehi/Thaana support must be handled through block
 *   > settings, localization, fonts, and logical CSS, not by creating a
 *   > separate block type.
 *
 * The structural half was already right: there is no `RtlText` case, and the
 * player reads `settings.direction` rather than branching per type. What was
 * missing is four of the five settings — **only `direction` existed**. A
 * lesson could not say an Arabic passage was Arabic, could not right-align a
 * Thaana note, and could not ask for a Thaana face.
 *
 * "Text alignment using **start/end**" is the specific wording, and it is the
 * point: `left`/`right` are physical and silently wrong the moment the same
 * block is read in the other direction. Only logical values are accepted here,
 * so a physical one cannot be stored by any caller.
 *
 * The other defect this fixes is quieter. `CourseOutlineController` assigned
 * `'settings' => ['direction' => ...]` — a **whole new array** — on every
 * save, so any other setting a block carried was destroyed the next time
 * anyone touched it. Adding four settings on top of that would have shipped
 * four fields that silently reset. Settings now merge.
 */
class NormalizeBlockTextSettingsAction
{
    /** §15.3: "Direction: LTR, RTL, or auto". */
    public const DIRECTIONS = ['ltr', 'rtl', 'auto'];

    /**
     * §15.3: "Text alignment using start/end". Physical values are refused,
     * not translated — storing `left` would be wrong for the same block read
     * right-to-left, and logical CSS is what the section asks for.
     */
    public const ALIGNMENTS = ['start', 'end', 'center'];

    /**
     * The languages the platform is trilingual in (§7), plus `auto` to mean
     * "inherit the lesson's". Not hardcoded prose: these are the same codes
     * the app's locales use.
     */
    public const LANGUAGES = ['auto', 'en', 'dv', 'ar'];

    /**
     * §15.3's "Optional font preference". `default` defers to the stylesheet;
     * the other two name the script-specific faces the app already loads.
     */
    public const FONTS = ['default', 'thaana', 'arabic'];

    /**
     * Which blocks carry readable text, and so take these settings.
     *
     * Media blocks are included on purpose: they carry a title and a caption,
     * and a Thaana caption under an image needs the same direction and face a
     * Thaana paragraph does. §15.3's rule is about text, wherever it appears.
     */
    public function appliesTo(ContentBlockType $type): bool
    {
        return $type !== ContentBlockType::QuizEmbed
            && $type !== ContentBlockType::AssignmentEmbed;
    }

    /**
     * Merge incoming text settings over what a block already has.
     *
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $given
     * @return array<string, mixed>
     */
    public function execute(array $existing, array $given): array
    {
        $settings = $existing;

        foreach ([
            'direction' => self::DIRECTIONS,
            'align' => self::ALIGNMENTS,
            'language' => self::LANGUAGES,
            'font' => self::FONTS,
        ] as $key => $allowed) {
            if (! array_key_exists($key, $given)) {
                continue;
            }

            $value = $given[$key];
            if ($value === null || $value === '') {
                unset($settings[$key]);

                continue;
            }

            $value = strtolower(trim((string) $value));
            if (in_array($value, $allowed, true)) {
                $settings[$key] = $value;
            }
            // An unrecognised value is dropped rather than stored. A block
            // whose alignment is a word no stylesheet understands would render
            // unaligned with nothing to say why.
        }

        return $settings;
    }

    /**
     * The settings a block ends up rendering with, defaults filled in.
     *
     * @param  array<string, mixed>|null  $settings
     * @return array{direction: string, align: string, language: string, font: string}
     */
    public function resolved(?array $settings): array
    {
        $settings ??= [];

        return [
            'direction' => in_array($settings['direction'] ?? null, self::DIRECTIONS, true) ? $settings['direction'] : 'auto',
            'align' => in_array($settings['align'] ?? null, self::ALIGNMENTS, true) ? $settings['align'] : 'start',
            'language' => in_array($settings['language'] ?? null, self::LANGUAGES, true) ? $settings['language'] : 'auto',
            'font' => in_array($settings['font'] ?? null, self::FONTS, true) ? $settings['font'] : 'default',
        ];
    }
}
