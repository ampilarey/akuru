<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For the public course enroll route: convert any 401/403 (exception or response)
 * to a redirect with a friendly message (avoids "Unauthorized").
 */
class ConvertEnroll403ToRedirect
{
    private const MESSAGE = 'Your session may have expired or this contact is already registered. Please go back to the course and start enrollment again, or use a different mobile/email.';

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        $routeName = $request->route()?->getName();
        // The public registration's enrol steps and a learner's self-enrol
        // only. Until C16 slice N4 (STATUS §5nz) any POST whose path merely
        // contained "enroll" counted — the admin's enrolment decisions and the
        // Hifz enrolment list among them — so a forbidden office action came
        // back as "your session may have expired or this contact is already
        // registered", a message about a form the office never saw.
        $isEnrollRoute = in_array($routeName, ['courses.register.enroll', 'learn.courses.enroll'], true)
            || ($request->isMethod('POST') && str_contains($path, 'courses/register/enroll'));

        try {
            $response = $next($request);

            if ($isEnrollRoute && in_array($response->getStatusCode(), [401, 403], true)) {
                return redirect()->back()
                    ->withErrors(['_authorization' => self::MESSAGE])
                    ->withInput();
            }

            return $response;
        } catch (\Throwable $e) {
            if (! $isEnrollRoute) {
                throw $e;
            }
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return redirect()->back()
                ->withErrors(['_authorization' => self::MESSAGE])
                ->withInput();
        }
    }
}
