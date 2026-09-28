<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every page script that writes data (create / edit / status change /
 * delete / profile) must show the shared busy overlay (resources/js/busy.js,
 * `window.gcmBusy`) while the request runs — the same "preloader" on every
 * form, so a slow save never looks frozen and a second click can't submit
 * twice. Pages used to differ: only the file-upload forms had it.
 *
 * This is a source scan, not a browser test: it fails the moment someone
 * adds a page script that calls a non-GET axios method without going
 * through gcmBusy. The sign-in / password-reset screens are exempt — they
 * are not create/edit pages and keep their own inline feedback.
 */
class BusyOverlayConsistencyTest extends TestCase
{
    private const EXEMPT = [
        'pages-auth-login.js',
        'pages-auth-forgot-password.js',
        'pages-auth-reset-password.js',
    ];

    /** @return array<string, string> file => source */
    private function pageScripts(): array
    {
        $root = dirname(__DIR__, 2).'/resources/assets/js';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        $out = [];
        foreach ($files as $file) {
            if ($file->getExtension() === 'js') {
                $out[str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1))] = file_get_contents($file->getPathname());
            }
        }

        return $out;
    }

    public function test_every_script_that_writes_data_shows_the_shared_busy_overlay(): void
    {
        // axios.post(...) / axios.patch(...) / axios[method](...) / a chained .post( — anything but GET
        $writes = '/axios\s*(?:\.\s*(?:post|patch|put|delete)\s*\(|\[)|\.\s*(?:post|patch|put|delete)\s*\(\s*[\'"`]\/api/';

        $missing = [];
        $checked = 0;
        foreach ($this->pageScripts() as $file => $source) {
            if (in_array(basename($file), self::EXEMPT, true) || ! preg_match($writes, $source)) {
                continue;
            }

            $checked++;
            if (! str_contains($source, 'gcmBusy.start(')) {
                $missing[] = $file;
            }
        }

        $this->assertGreaterThan(10, $checked, 'the scan found suspiciously few write scripts — the pattern is probably broken');
        $this->assertSame([], $missing, "These scripts write data without the shared busy overlay (window.gcmBusy.start):\n".implode("\n", $missing));
    }

    public function test_every_overlay_that_starts_can_also_be_stopped(): void
    {
        // start() without a matching stop() would leave the page covered forever after an error.
        $stuck = [];
        foreach ($this->pageScripts() as $file => $source) {
            if (str_contains($source, 'gcmBusy.start(') && ! str_contains($source, 'gcmBusy.stop(') && ! str_contains($source, 'gcmBusy.fail(')) {
                $stuck[] = $file;
            }
        }

        $this->assertSame([], $stuck, "These scripts start the overlay but never stop it:\n".implode("\n", $stuck));
    }
}
