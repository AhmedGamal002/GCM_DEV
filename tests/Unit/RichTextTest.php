<?php

namespace Tests\Unit;

use App\Support\RichText;
use DOMDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The "Additional Data" rich text is rendered with `innerHTML` on every view page, so RichText is the only thing
 * standing between whatever a data-entry account posts and a script running in the system admin's browser.
 */
class RichTextTest extends TestCase
{
    public function test_what_the_editor_produces_passes_through_unchanged(): void
    {
        $html = '<p>Hello <strong>bold</strong> <em>it</em> <u>u</u> <s>s</s></p><ol><li>one</li></ol><ul><li>two</li></ul>'
            .'<h2>Title</h2><blockquote>quote</blockquote><p class="ql-align-center ql-indent-1">centred</p>';

        $this->assertSame($html, RichText::sanitize($html));
    }

    public function test_arabic_text_is_kept(): void
    {
        $html = '<p>ملاحظات <strong>مهمة</strong> عن المركبة</p>';

        $this->assertSame($html, RichText::sanitize($html));
    }

    public function test_plain_text_is_kept_and_markup_characters_are_escaped(): void
    {
        $this->assertSame('Some notes', RichText::sanitize('Some notes'));
        $this->assertSame('a &lt; b and c &gt; d', RichText::sanitize('a < b and c > d'));
        $this->assertSame('Tom &amp; Jerry', RichText::sanitize('Tom &amp; Jerry'));
        $this->assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', RichText::sanitize('&lt;script&gt;alert(1)&lt;/script&gt;'));
    }

    public function test_nothing_or_an_empty_editor_becomes_null(): void
    {
        $this->assertNull(RichText::sanitize(null));
        $this->assertNull(RichText::sanitize(''));
        $this->assertNull(RichText::sanitize('   '));
        $this->assertNull(RichText::sanitize('<p><br></p>'));
        $this->assertNull(RichText::sanitize('<script>alert(1)</script>'));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function hostileInput(): array
    {
        return [
            'script element' => ['<p>a</p><script>alert(1)</script><p>b</p>', '<p>a</p><p>b</p>'],
            'image with handler' => ['<p>a</p><img src=x onerror=alert(1)>', '<p>a</p>'],
            'svg with script' => ['<svg><script>alert(1)</script></svg><p>ok</p>', '<p>ok</p>'],
            'iframe' => ['<iframe src="https://evil.example"></iframe>text', 'text'],
            'event handler attribute' => ['<p onclick="alert(1)" class="ql-align-center evil">x</p>', '<p class="ql-align-center">x</p>'],
            'javascript link' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
            'javascript link with whitespace in the scheme' => ['<a href="java&#x0A;script:alert(1)">x</a>', '<a>x</a>'],
            'javascript link in capitals with leading spaces' => ['<a href="  JaVaScRiPt:alert(1)">x</a>', '<a>x</a>'],
            'data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', '<a>x</a>'],
            'html comment hiding markup' => ['<p>a<!-- <img src=x onerror=1> -->b</p>', '<p>ab</p>'],
            'style element with a payload inside an attribute' => ['<style><a id="</style><img src=x onerror=alert(1)>">', '"&gt;'],
            'noscript trick' => ['<noscript><p title="</noscript><img src=x onerror=alert(1)>">', ''],
            'textarea trick' => ['<textarea><p title="</textarea><img src=x onerror=alert(1)>">', ''],
            'math / mglyph trick' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>', ''],
            'closing the wrapper early' => ['</div></body><img src=x onerror=1>z', ''],
            'css injection through style' => [
                '<span style="color: rgb(230, 0, 0); background-image:url(javascript:x); position:fixed">c</span>',
                '<span style="color: rgb(230, 0, 0);">c</span>',
            ],
        ];
    }

    #[DataProvider('hostileInput')]
    public function test_hostile_markup_is_removed(string $input, string $expected): void
    {
        $clean = RichText::sanitize($input);

        $this->assertSame($expected === '' ? null : $expected, $clean);
        $this->assertDoesNotMatchRegularExpression('/<(script|img|iframe|svg|style|math|noscript|textarea)\b|onerror|onclick|javascript:/i', (string) $clean);
    }

    public function test_safe_links_are_kept_and_cannot_break_out_of_the_attribute(): void
    {
        $this->assertSame(
            '<a href="https://example.com" target="_blank" rel="noopener noreferrer">site</a>',
            RichText::sanitize('<a href="https://example.com" target="_self" rel="opener">site</a>')
        );
        $this->assertStringContainsString('href="mailto:a@example.com"', RichText::sanitize('<a href="mailto:a@example.com">m</a>'));

        $clean = RichText::sanitize('<a href="https://a.example/&quot; onmouseover=&quot;alert(1)">x</a>');
        // The quotes inside the address stay escaped, so the rest of it is still part of the href value, not a new attribute.
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">'.$clean, LIBXML_NOERROR | LIBXML_NOWARNING);
        $names = [];
        foreach ($document->getElementsByTagName('a')->item(0)->attributes as $attribute) {
            $names[] = $attribute->name;
        }
        $this->assertSame(['href', 'target', 'rel'], $names);
    }

    public function test_sanitizing_twice_changes_nothing(): void
    {
        foreach (self::hostileInput() as [$input]) {
            $once = RichText::sanitize($input);

            $this->assertSame($once, RichText::sanitize($once), "not idempotent for: {$input}");
        }
    }
}
