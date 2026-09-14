<?php

use App\Domains\Notifications\Contracts\SmsSenderInterface;

/**
 * Every method called on an `SmsSenderInterface` is declared on it.
 *
 * ## What this is for
 *
 * `OtpService` is constructor-typed against `SmsSenderInterface` and called
 * `$this->smsGateway->sendOtp(...)`. The interface declared only `sendSms`.
 * `SmsGatewayService` happened to have `sendOtp` too, so the code worked with
 * the live driver — and `LogSmsSender`, which implemented the interface
 * faithfully and nothing more, did not have it.
 *
 * `LogSmsSender` is the binding whenever live SMS is off: local, staging, and
 * any production without `SMS_LIVE`. So every mobile OTP threw, `OtpService`
 * caught it and rendered *"Unable to send verification code. Please try
 * again."*, and **OTP login could not work anywhere except live-SMS
 * production**.
 *
 * The failure belongs to the contract, not the implementation. The one class
 * that obeyed the interface was the one that broke, and the class that worked
 * did so by accident of having a method nobody had written down. Rule 4 says
 * SMS lives behind a domain-owned interface; an interface that does not
 * declare what its consumers call is not one.
 *
 * ## How it checks
 *
 * Finds properties and parameters typed `SmsSenderInterface` across `app/`,
 * then every `->method(` called on those variables, and requires the interface
 * to declare each. Deliberately simple: it reads names, not types, so it would
 * rather be obvious than clever.
 */
it('declares every method its callers use', function () {
    $declared = array_map(
        fn (ReflectionMethod $m) => $m->getName(),
        (new ReflectionClass(SmsSenderInterface::class))->getMethods()
    );

    $missing = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = stripPhpComments(file_get_contents($file->getPathname()));

        if (! str_contains($source, 'SmsSenderInterface')) {
            continue;
        }

        $path = str_replace(base_path().'/', '', $file->getPathname());

        // `protected SmsSenderInterface $smsGateway` / `SmsSenderInterface $sms`
        preg_match_all('/SmsSenderInterface\s+\$([a-zA-Z_][a-zA-Z0-9_]*)/', $source, $names);

        foreach (array_unique($names[1]) as $variable) {
            foreach (['\$this->'.$variable, '\$'.$variable] as $holder) {
                if (! preg_match_all('/'.$holder.'->([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $source, $calls)) {
                    continue;
                }

                foreach (array_unique($calls[1]) as $method) {
                    if (! in_array($method, $declared, true)) {
                        $missing[] = $path.'  calls  ->'.$method.'()  on $'.$variable;
                    }
                }
            }
        }
    }

    $missing = array_values(array_unique($missing));
    sort($missing);

    expect($missing)->toBeEmpty(
        "These call a method SmsSenderInterface does not declare:\n  "
        .implode("\n  ", $missing)
        ."\n\nAdd it to the interface and to every implementation. A contract that "
        .'does not describe what its consumers call is not a contract: the driver that '
        .'obeys it is the one that breaks, and only in the environments that use it.'
    );
});

it('is satisfied by every implementation, log driver included', function () {
    $implementations = [
        \App\Domains\Notifications\Services\LogSmsSender::class,
        \App\Domains\Notifications\Services\SmsGatewayService::class,
    ];

    foreach ($implementations as $class) {
        foreach ((new ReflectionClass(SmsSenderInterface::class))->getMethods() as $method) {
            expect(method_exists($class, $method->getName()))->toBeTrue(
                $class.' is missing '.$method->getName().'(), which the interface declares. '
                .'This is how OTP login broke everywhere live SMS is off.'
            );
        }
    }
});
