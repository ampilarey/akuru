<?php

// SPEC §41 "The architecture test must fail CI if … External SDK classes are
// used directly inside domain business logic instead of wrapped interfaces"
// — see tests/Architecture/SdksStayBehindInterfacesTest.php.
//
// Files that reach a third-party integration (an SDK namespace, or the `Http`
// facade) from outside a `Services/` wrapper layer. Each entry says why.
//
// Baseline may only shrink. Count: 2.

return [
    // ---------------------------------------------------------------------
    // Diagnostic tooling. A command whose entire purpose is to probe the
    // provider directly cannot go through the wrapper without testing the
    // wrapper instead of the provider, which is the opposite of what it is
    // for. It is not business logic and nothing in the app calls it.
    // ---------------------------------------------------------------------
    'app/Console/Commands/TestBmlCommand.php' => 'Http:: — diagnostic command; probing BML directly is its purpose.',

    // ---------------------------------------------------------------------
    // A real violation, recorded rather than fixed, and the reason is worth
    // reading before anyone "tidies" it.
    //
    // `SmsApiController::send()` is an **inbound** API endpoint that forwards
    // to the upstream SMS provider. It builds its own `Http::` client with its
    // own timeout, its own X-API-Key/Bearer headers and its own error mapping
    // — all of which `SmsGatewayService::sendViaHttpGateway()`, in the same
    // domain, already does. Two places now know the provider's wire format.
    //
    // It is NOT a safety hole: `LiveSms::allowed()` gates both methods, and on
    // the blocked path `send()` goes through `SmsSenderInterface` like
    // everything else. The live-SMS kill-switch holds.
    //
    // It is not fixed here because the endpoint returns the provider's raw
    // JSON to its API callers, and the wrapper returns a normalised
    // `{success, message_id, status, cost}`. Routing it through the wrapper
    // changes the response shape of a published API — a deliberate contract
    // change with its own consumers to check, not something a §41
    // enforcement slice gets to do on the way past.
    // ---------------------------------------------------------------------
    'app/Domains/Notifications/Http/Controllers/SmsApiController.php' => 'Http:: — duplicates SmsGatewayService::sendViaHttpGateway; fixing it changes a published API response shape.',
];
