<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = [
            'app_name'         => config('app.name'),
            'app_env'          => config('app.env'),
            'app_url'          => config('app.url'),
            'mail_mailer'      => config('mail.default'),
            'mail_from'        => config('mail.from.address'),
            'cache_driver'     => config('cache.default'),
            'session_driver'   => config('session.driver'),
            'queue_connection' => config('queue.default'),
        ];

        // SMS gateway configured?
        $smsConfigured = !empty(config('services.sms_gateway.url'))
                      || !empty(env('SMS_GATEWAY_URL'));

        // BML payment configured?
        $bmlConfigured = !empty(config('services.bml.api_key'))
                      || !empty(env('BML_API_KEY'));

        return view('admin.settings.index', compact('settings', 'smsConfigured', 'bmlConfigured'));
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');

        return back()->with('success', 'All caches cleared successfully.');
    }
}
