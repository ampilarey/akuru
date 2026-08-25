<?php

return [
    /*
    | Qur’an A.4 — dual-write of mapped halaqa onto Offerings.
    | Off by default (Rule 9 deploy 1). Switch/cleanup are later deploys.
    */
    'halaqa_dual_write' => (bool) env('QURAN_HALAQA_DUAL_WRITE', false),
];
