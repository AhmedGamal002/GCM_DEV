<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every form that wires the shared affiliation field (resources/js/gcm-affiliation.js,
 * `window.gcmAffiliation(form)`) must actually send the chosen contractor.
 *
 * A form that posts `new FormData(form)` gets it for free (the enabled
 * `#contractor_id` select is part of the form). A form that builds its own
 * payload must read it from the helper's `get()` — the asset form used to send
 * only the radio, so "Belongs to a Contractor" always failed with "The
 * contractor field is required" even with a contractor picked. The API tests
 * could not see that: they post the payload a correct form would send.
 *
 * A source scan, like BusyOverlayConsistencyTest — not a browser test.
 */
class AffiliationSubmitConsistencyTest extends TestCase
{
    public function test_every_form_with_the_affiliation_field_sends_the_chosen_contractor(): void
    {
        $root = dirname(__DIR__, 2).'/resources/assets/js';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $missing = [];
        $checked = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'js') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if (! str_contains($source, 'gcmAffiliation(')) {
                continue;
            }

            $checked++;
            $sendsWholeForm = (bool) preg_match('/new\s+FormData\s*\(\s*form\s*\)/', $source);
            $readsHelper = (bool) preg_match('/\baffiliation\s*\.\s*get\s*\(\s*\)/', $source)
                && str_contains($source, 'contractor_id');

            if (! $sendsWholeForm && ! $readsHelper) {
                $missing[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        $this->assertGreaterThanOrEqual(4, $checked, 'Expected the driver add/edit, vehicle and asset forms to be scanned.');
        $this->assertSame([], $missing, 'These forms show the affiliation field but never send contractor_id: '.implode(', ', $missing));
    }
}
