<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A trip page script that calls `moment(...)` throws a ReferenceError — half the page stays empty — unless its Blade
 * page loads the moment vendor script. Found by hand on the driver's details page (7.6); a test stops it coming back
 * on any trip page. The Blade page may load it through an included partial.
 */
class TripPagesScriptsTest extends TestCase
{
    public function test_every_trip_page_script_that_uses_moment_has_it_loaded(): void
    {
        $root = dirname(__DIR__, 2);
        $views = glob($root.'/resources/views/tenant/trips/{,*/}*.blade.php', GLOB_BRACE);
        $checked = 0;

        foreach (glob($root.'/resources/assets/js/*trip*.js') as $script) {
            if (! str_contains(file_get_contents($script), 'moment(')) {
                continue;
            }

            $name = basename($script);
            $pages = array_filter($views, fn ($view) => str_contains(file_get_contents($view), $name));
            $this->assertNotEmpty($pages, "No Blade page loads {$name}");

            foreach ($pages as $page) {
                // the page itself, or the partials it includes
                $source = file_get_contents($page);
                preg_match_all("/@include\('tenant\.trips\.([a-z\-_.]+)'/", $source, $included);
                foreach ($included[1] as $partial) {
                    $source .= file_get_contents($root.'/resources/views/tenant/trips/'.str_replace('.', '/', $partial).'.blade.php');
                }

                $this->assertStringContainsString('libs/moment/moment.js', $source, basename($page).' uses '.$name.', which needs moment');
                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked);
    }
}
