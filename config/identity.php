<?php

return [
    /*
     * COMMERCE_PARITY_PLAN P2: a shop sells, and a writer submits or is paid,
     * only once the office has verified their identity card. On everywhere;
     * phpunit.xml turns it off for the tests written before the rule, the way
     * it allows unsigned BML webhooks for the older payment tests, and the
     * identity tests turn it back on.
     */
    'verification' => [
        'enforce' => filter_var(env('IDENTITY_VERIFICATION_ENFORCE', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
