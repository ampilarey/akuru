<?php

namespace App\Support\Http\Controllers;

use App\Domains\Settings\Actions\ListEnabledLocalesAction;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleController extends Controller
{
    public function setLocale($locale)
    {
        // Only a language the office has switched on (STATUS §5su).
        if (in_array($locale, app(ListEnabledLocalesAction::class)->execute(), true)) {
            App::setLocale($locale);
            Session::put('locale', $locale);
        }

        return redirect()->back();
    }
}
