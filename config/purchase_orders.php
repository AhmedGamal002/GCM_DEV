<?php

/*
 * Purchase orders (FRD V01.14 §1.13).
 *
 * Every PO is in Saudi riyals, two decimals, prices as entered with no VAT
 * added (decision أ4) — so there is no currency column; the label shown
 * next to amounts comes from here only.
 */
return [
    'currency' => env('PO_CURRENCY', 'SAR'),

    // One attachment per PO: a PDF or an image, up to 5 MB (decision ب11).
    'attachment_max_kb' => 5120,
    'attachment_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
];
