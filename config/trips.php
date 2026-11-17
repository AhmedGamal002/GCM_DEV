<?php

/*
 * Trips (FRD V01.14 §1.14) — part 7.1.
 *
 * The numbers the trip forms and reports read from one place (never
 * hard-coded in JS): the m³ → tonne factor and the decimals of a waste
 * quantity (decision ج10), and the file limits of the trip's attachments.
 */
return [
    // ج10: 1 m³ = 0.3125 t for every kind of waste (liquid in tanks included) —
    // the FRD's own conversion line. Quantities keep 3 decimals.
    'tonnes_per_cubic_meter' => 0.3125,
    'quantity_decimals' => 3,

    // Paper documents and photos of a trip (delivery note, waste manifest,
    // recycle receipt, live photo, other attachment, the client's attachment):
    // a PDF or an image, on the private disk.
    'attachment_max_kb' => 5120,
    'attachment_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],

    // Signatures and stamps — the same limits as the accounts' own (2 MB, images only).
    'signature_max_kb' => 2048,
    'signature_mimes' => ['png', 'jpg', 'jpeg'],
];
