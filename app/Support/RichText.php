<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allowlist sanitizer for the rich text ("Additional Data", a Quill editor) every module stores.
 *
 * The view pages put that field straight into `innerHTML`, so whatever is stored runs in the viewer's browser: a
 * data-entry account that posted `<img src=x onerror=...>` straight to the API would run script as the system admin
 * who opens the record. Nothing the editor can produce needs script, so the field is rebuilt from a short allowlist
 * of tags / attributes and every other construct is dropped — the output is assembled here (text escaped, attribute
 * values escaped), never copied from the parsed input, so parser differences between libxml and the browser cannot
 * turn a harmless-looking string into markup.
 *
 * Used by the `RichTextCast` (every read and write of `additional_data`) and by NormalizesRichTextInput.
 */
final class RichText
{
    /** Tags kept as they are (Quill's own output plus the usual pasted formatting). */
    private const TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'ol', 'ul', 'li', 'blockquote', 'pre', 'code',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span', 'a', 'sub', 'sup', 'div',
    ];

    /** Tags that never have children or a closing tag. */
    private const VOID_TAGS = ['br'];

    /** Tags dropped together with everything inside them (anything else not in TAGS is unwrapped, keeping its text). */
    private const DROPPED_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'svg', 'math', 'noscript',
        'template', 'textarea', 'select', 'option', 'title', 'head', 'meta', 'link', 'base', 'form', 'input',
        'button', 'audio', 'video', 'source', 'canvas', 'img', 'xmp', 'plaintext', 'noembed', 'noframes',
    ];

    private const LINK_SCHEMES = '/^(https?:|mailto:|tel:)/i';

    private const LIST_TYPES = ['bullet', 'ordered', 'checked', 'unchecked'];

    private const COLOR_VALUE = '/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*(,\s*\d{1,3}\s*){2}(,\s*(0|1|0?\.\d+)\s*)?\)|[a-z]{3,20})$/i';

    /** Text, tags and attributes outside the allowlist are removed; null / blank / tag-only input becomes null. */
    public static function sanitize(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $clean = str_contains($html, '<') ? self::rebuild($html) : self::escapeText(self::decode($html));

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

    private static function rebuild(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            // The XML declaration makes libxml read the string as UTF-8 (loadHTML assumes Latin-1 otherwise).
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $body = $document->getElementsByTagName('body')->item(0);

        return $body ? self::children($body) : '';
    }

    private static function children(DOMNode $node): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            $out .= self::node($child);
        }

        return $out;
    }

    private static function node(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return self::escapeText($node->data);
        }

        if (! $node instanceof DOMElement) {
            // Comments, processing instructions, CDATA — nothing the editor needs.
            return '';
        }

        $tag = strtolower($node->tagName);

        if (in_array($tag, self::DROPPED_WITH_CONTENT, true)) {
            return '';
        }

        $inner = self::children($node);

        if (! in_array($tag, self::TAGS, true)) {
            return $inner;
        }

        if (in_array($tag, self::VOID_TAGS, true)) {
            return "<{$tag}>";
        }

        return "<{$tag}".self::attributes($tag, $node).">{$inner}</{$tag}>";
    }

    private static function attributes(string $tag, DOMElement $node): string
    {
        $attributes = [];

        $classes = array_filter(
            preg_split('/\s+/', trim($node->getAttribute('class'))) ?: [],
            fn (string $class) => preg_match('/^ql-[a-z0-9_-]+$/i', $class) === 1
        );
        if ($classes) {
            $attributes['class'] = implode(' ', $classes);
        }

        if ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            // Control characters / spaces inside the scheme ("java\nscript:") are how filters get bypassed.
            if ($href !== '' && preg_match(self::LINK_SCHEMES, preg_replace('/[\x00-\x20]+/', '', $href)) === 1) {
                $attributes['href'] = $href;
                $attributes['target'] = '_blank';
                $attributes['rel'] = 'noopener noreferrer';
            }
        }

        if ($tag === 'li' && in_array($node->getAttribute('data-list'), self::LIST_TYPES, true)) {
            $attributes['data-list'] = $node->getAttribute('data-list');
        }

        if ($tag === 'span' && ($style = self::style($node->getAttribute('style'))) !== '') {
            $attributes['style'] = $style;
        }

        $out = '';
        foreach ($attributes as $name => $value) {
            $out .= " {$name}=\"".htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        return $out;
    }

    /** Only the two colour declarations the editor's colour pickers write. */
    private static function style(string $style): string
    {
        $kept = [];

        foreach (explode(';', $style) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, '');
            $property = strtolower(trim($property));
            $value = trim($value);

            if (in_array($property, ['color', 'background-color'], true) && preg_match(self::COLOR_VALUE, $value) === 1) {
                $kept[] = "{$property}: {$value};";
            }
        }

        return implode(' ', $kept);
    }

    private static function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function escapeText(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
