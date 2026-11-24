<?php

/*
 * The generated trip documents (FRD V01.14 §1.14 "الوثائق المولدة تلقائياً",
 * part 8.1) — the static parts of their header and footer in one place.
 *
 * The company name and contact lines are the FRD's and the paper forms' as
 * they stand; open question أ2 (the official name and contact details) may
 * change them — then only here. The FRD writes the web site as
 * "www.gcm-gulf.co"; the paper forms print "www.gcm-gulf.com", which is used.
 */
return [
    'company_name' => 'Global Clear Mission',

    'footer' => [
        'title' => 'Officially Certified Document',
        'issued_by' => 'This is an officially certified document issued by GCM',
        'issued_by_ar' => 'هذا مستند رسمي معتمد صادر عن شركة الرسالة الواضحة العالمية',
        'binding' => 'Legally & financially binding',
        'binding_ar' => 'ملزم محاسبياً وقانونياً',
        'address' => 'P.O. Box 12831, Riyadh 7221 · Kingdom of Saudi Arabia',
        'contact' => 'Tel: +966114720027 · info@gcm-gulf.com · www.gcm-gulf.com',
    ],

    // The waste manifest's fixed declaration (FRD, "Generator's Certification").
    'generator_certification' => 'I hereby declare that the contents of this consignment are fully and accurately described above and are appropriately classified, packaged, and labelled in accordance with the applicable laws and regulations of the kingdom.',

    // The largest box a recycle receipt photo is scaled into on its page (points; A4 is 595 × 842).
    'receipt_box' => ['width' => 520, 'height' => 560],
];
