<?php

namespace App\Http\Middleware;

use App\Support\Navigation\ResolveWorkspacesAction;
use App\Support\Navigation\WorkspaceMap;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opening a workspace's home makes it the active workspace, so a bookmark
 * to `/school` or `/admin` shows that workspace's menus, not the last one
 * switched to. Only a home that belongs to exactly one of the person's
 * workspaces counts (the family and learning workspaces share a home and
 * are told apart by the switcher, which posts the choice).
 */
class RememberWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && $request->isMethod('GET') && $request->hasSession()) {
            $path = self::unlocalised($request->path());
            // A workspace's home for this person (a teacher's day) or for
            // anyone (the School office itself): opening either counts.
            $homes = array_values(array_filter(
                app(ResolveWorkspacesAction::class)->execute($user)['list'],
                fn (array $workspace): bool => in_array($path, [
                    self::unlocalised($workspace['href']),
                    self::unlocalised(route(WorkspaceMap::definition($workspace['key'])['home'], [], false)),
                ], true),
            ));
            if (count($homes) === 1) {
                $request->session()->put('workspace', $homes[0]['key']);
            }
        }

        return $next($request);
    }

    /** `/en/school`, `en/school` and `/school` are the same place. */
    public static function unlocalised(string $path): string
    {
        $path = '/'.ltrim($path, '/');
        $path = preg_replace('#^/(en|dv|ar)(?=/|$)#', '', $path) ?? $path;

        return '/'.ltrim($path, '/');
    }
}
