<?php

return [
    /*
     * BP3 (BACKLOG B11, STATUS §5ts): an office wallet credit at or above
     * this many MVR tells the other commerce managers in the app — money
     * added by hand is the one wallet entry no payment stands behind.
     */
    'wallet' => [
        'alert_credit_at' => (float) env('COMMERCE_WALLET_ALERT_CREDIT_AT', 1000),
    ],
];
