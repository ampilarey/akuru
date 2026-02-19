<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Base controller for the public course registration flow.
 * Does not use AuthorizesRequests so no policy/gate can cause 403 Unauthorized.
 */
abstract class PublicRegistrationController extends BaseController
{
    use ValidatesRequests;
}
