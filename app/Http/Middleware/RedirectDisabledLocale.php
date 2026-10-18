<?php

namespace App\Http\Middleware;

use App\Domains\Settings\Actions\ListEnabledLocalesAction;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An address in a language the office has switched off goes to the same
 * page in English (STATUS §5su): `/ar/library?q=x` → `/en/library?q=x`. The
 * session is told, so a bare address does not send the visitor back to the
 * language that is off. Only a GET or HEAD is sent on; a form posted from a
 * page opened before the switch still saves.
 */
class RedirectDisabledLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $first = (string) $request->segment(1);

        if ($request->isMethodSafe()
            && in_array($first, ListEnabledLocalesAction::SWITCHABLE, true)
            && ! app(ListEnabledLocalesAction::class)->isEnabled($first)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', ListEnabledLocalesAction::ALWAYS);
            }
            $rest = substr($request->getPathInfo(), strlen('/'.$first));
            $query = $request->getQueryString();

            return redirect($request->getBaseUrl().'/'.ListEnabledLocalesAction::ALWAYS.$rest.($query !== null ? '?'.$query : ''));
        }

        return $next($request);
    }
}
