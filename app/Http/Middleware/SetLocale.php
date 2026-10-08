<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mcamara\LaravelLocalization\LaravelLocalization;
use Symfony\Component\HttpFoundation\Response;

/**
 * A form answers in the language of the page it was sent from (STATUS §5os).
 *
 * LaravelLocalization takes the language from the address's first segment
 * (`/dv/…`) and, for an address without one, sends a GET on to the
 * remembered language. It leaves POST, PUT, PATCH and DELETE alone
 * (`httpMethodsIgnored`), so those run in the default language — and the
 * screens send their forms to bare addresses (`/catalog/glossary`,
 * `/quran/mushafs`). Every message a save wrote was English on a Dhivehi or
 * Arabic page, though the page the person was then sent back to was theirs:
 * the translated flashes of the course screens (C19) never reached anyone.
 *
 * So a change sent to a bare address takes the language of the page it came
 * from — this site's own Referer — or, failing that, the one the session
 * remembers. The session is told too, so the redirect after the save comes
 * back to the same language. A GET is left to the package, which redirects
 * it; an address that names its language keeps it.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $localization = app(LaravelLocalization::class);

        if (! $request->isMethodSafe() && ! $localization->checkLocaleInSupportedLocales((string) $request->segment(1))) {
            $locale = $this->refererLocale($request, $localization)
                ?? ($request->hasSession() ? $request->session()->get('locale') : null);

            if (is_string($locale) && $localization->checkLocaleInSupportedLocales($locale)) {
                $localization->setLocale($locale);
                if ($request->hasSession()) {
                    $request->session()->put('locale', $locale);
                }
            }
        }

        return $next($request);
    }

    /** The first segment of this site's own Referer, when it names a language. */
    private function refererLocale(Request $request, LaravelLocalization $localization): ?string
    {
        $referer = parse_url((string) $request->headers->get('referer'));
        if (! is_array($referer) || ($referer['host'] ?? null) !== $request->getHost()) {
            return null;
        }

        $segment = explode('/', trim($referer['path'] ?? '', '/'))[0];

        return $localization->checkLocaleInSupportedLocales($segment) ? $segment : null;
    }
}
