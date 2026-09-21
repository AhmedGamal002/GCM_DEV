<?php

namespace App\Support;

/**
 * Makes Arabic text renderable by DomPDF.
 *
 * DomPDF paints characters left-to-right exactly as given: it does not join
 * Arabic letters into their connected forms and has no bidirectional layout,
 * so Arabic came out as disconnected, reversed letters (or "?" with the core
 * fonts, which have no Arabic glyphs at all). forPdf() does both steps up
 * front — letters are swapped for their Presentation Forms-B glyphs (which
 * DejaVu Sans, bundled with DomPDF, contains) and the text is reordered into
 * visual order — so a left-to-right renderer paints it correctly.
 *
 * Deliberately small rather than a full UAX #9 implementation: the input is
 * short single-line table cells and titles (names, plates, categories).
 *  - Text without Arabic is returned untouched, so English-only exports pay nothing.
 *  - The base direction of any text containing Arabic is right-to-left; Latin
 *    words and digits inside it keep their own left-to-right order.
 *  - Diacritics (tashkeel) are dropped: DomPDF cannot position combining marks.
 *  - Output is pre-reversed, so it must not be allowed to wrap mid-cell
 *    (the PDF template sets white-space: nowrap).
 *
 * The letter-form tables below were generated from the Unicode character
 * database and filtered to glyphs DejaVu Sans actually contains.
 */
final class ArabicText
{
    private const TATWEEL = 0x0640;

    /** base letter => [isolated, final, initial, medial]; null = the letter has no such form (right-joining). */
    private const FORMS = [
        0x0621 => [0xFE80, null, null, null],  // ء
        0x0622 => [0xFE81, 0xFE82, null, null],  // آ
        0x0623 => [0xFE83, 0xFE84, null, null],  // أ
        0x0624 => [0xFE85, 0xFE86, null, null],  // ؤ
        0x0625 => [0xFE87, 0xFE88, null, null],  // إ
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],  // ئ
        0x0627 => [0xFE8D, 0xFE8E, null, null],  // ا
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],  // ب
        0x0629 => [0xFE93, 0xFE94, null, null],  // ة
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],  // ت
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],  // ث
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],  // ج
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],  // ح
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],  // خ
        0x062F => [0xFEA9, 0xFEAA, null, null],  // د
        0x0630 => [0xFEAB, 0xFEAC, null, null],  // ذ
        0x0631 => [0xFEAD, 0xFEAE, null, null],  // ر
        0x0632 => [0xFEAF, 0xFEB0, null, null],  // ز
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],  // س
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],  // ش
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],  // ص
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],  // ض
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],  // ط
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],  // ظ
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],  // ع
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],  // غ
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],  // ف
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],  // ق
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],  // ك
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],  // ل
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],  // م
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],  // ن
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],  // ه
        0x0648 => [0xFEED, 0xFEEE, null, null],  // و
        0x0649 => [0xFEEF, 0xFEF0, 0xFBE8, 0xFBE9],  // ى
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],  // ي
        0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69],  // ٹ
        0x067A => [0xFB5E, 0xFB5F, 0xFB60, 0xFB61],  // ٺ
        0x067B => [0xFB52, 0xFB53, 0xFB54, 0xFB55],  // ٻ
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],  // پ
        0x067F => [0xFB62, 0xFB63, 0xFB64, 0xFB65],  // ٿ
        0x0680 => [0xFB5A, 0xFB5B, 0xFB5C, 0xFB5D],  // ڀ
        0x0683 => [0xFB76, 0xFB77, 0xFB78, 0xFB79],  // ڃ
        0x0684 => [0xFB72, 0xFB73, 0xFB74, 0xFB75],  // ڄ
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],  // چ
        0x0687 => [0xFB7E, 0xFB7F, 0xFB80, 0xFB81],  // ڇ
        0x0688 => [0xFB88, 0xFB89, null, null],  // ڈ
        0x068C => [0xFB84, 0xFB85, null, null],  // ڌ
        0x068D => [0xFB82, 0xFB83, null, null],  // ڍ
        0x068E => [0xFB86, 0xFB87, null, null],  // ڎ
        0x0691 => [0xFB8C, 0xFB8D, null, null],  // ڑ
        0x0698 => [0xFB8A, 0xFB8B, null, null],  // ژ
        0x06A4 => [0xFB6A, 0xFB6B, 0xFB6C, 0xFB6D],  // ڤ
        0x06A6 => [0xFB6E, 0xFB6F, 0xFB70, 0xFB71],  // ڦ
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],  // ک
        0x06AD => [0xFBD3, 0xFBD4, 0xFBD5, 0xFBD6],  // ڭ
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],  // گ
        0x06B1 => [0xFB9A, 0xFB9B, 0xFB9C, 0xFB9D],  // ڱ
        0x06B3 => [0xFB96, 0xFB97, 0xFB98, 0xFB99],  // ڳ
        0x06BA => [0xFB9E, 0xFB9F, null, null],  // ں
        0x06BB => [0xFBA0, 0xFBA1, 0xFBA2, 0xFBA3],  // ڻ
        0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD],  // ھ
        0x06C6 => [0xFBD9, 0xFBDA, null, null],  // ۆ
        0x06C7 => [0xFBD7, 0xFBD8, null, null],  // ۇ
        0x06C8 => [0xFBDB, 0xFBDC, null, null],  // ۈ
        0x06CB => [0xFBDE, 0xFBDF, null, null],  // ۋ
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],  // ی
        0x06D0 => [0xFBE4, 0xFBE5, 0xFBE6, 0xFBE7],  // ې
    ];

    /** alef variant => [isolated, final] of its LAM+ALEF ligature. */
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],  // آ
        0x0623 => [0xFEF7, 0xFEF8],  // أ
        0x0625 => [0xFEF9, 0xFEFA],  // إ
        0x0627 => [0xFEFB, 0xFEFC],  // ا
    ];

    private const MIRRORED = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<'];

    public static function containsArabic(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    /** Text as it must be handed to DomPDF (visual order, joined letters). */
    public static function forPdf(?string $text): string
    {
        if ($text === null || $text === '' || ! self::containsArabic($text)) {
            return (string) $text;
        }

        $lines = preg_split('/\R/u', $text) ?: [$text];

        return implode("\n", array_map(self::line(...), $lines));
    }

    private static function line(string $line): string
    {
        $line = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $line) ?? $line;
        $codes = array_map(fn (string $c) => mb_ord($c, 'UTF-8'), mb_str_split($line, 1, 'UTF-8'));

        return self::reorder(self::shape($codes));
    }

    /**
     * @param  int[]  $codes
     * @return int[]
     */
    private static function shape(array $codes): array
    {
        $out = [];
        $count = count($codes);

        for ($i = 0; $i < $count; $i++) {
            $c = $codes[$i];
            $prev = $i > 0 ? $codes[$i - 1] : null;
            $next = $codes[$i + 1] ?? null;

            // LAM followed by an alef is one ligature glyph, never two letters.
            if ($c === 0x0644 && $next !== null && isset(self::LAM_ALEF[$next])) {
                $joinsPrev = $prev !== null && self::joinsForward($prev);
                $out[] = self::LAM_ALEF[$next][$joinsPrev ? 1 : 0];
                $i++;

                continue;
            }

            $forms = self::FORMS[$c] ?? null;
            if ($forms === null) {
                $out[] = $c; // not an Arabic letter (space, digit, Latin, tatweel...)

                continue;
            }

            $joinsPrev = $prev !== null && self::joinsForward($prev) && $forms[1] !== null;
            $joinsNext = $forms[2] !== null && $next !== null && self::acceptsJoin($next);

            $out[] = match (true) {
                $joinsPrev && $joinsNext => $forms[3] ?? $forms[1],
                $joinsPrev => $forms[1],
                $joinsNext => $forms[2],
                default => $forms[0],
            };
        }

        return $out;
    }

    /** Can this (original) letter connect to the letter AFTER it? Only dual-joining letters can. */
    private static function joinsForward(int $c): bool
    {
        return $c === self::TATWEEL || (isset(self::FORMS[$c]) && self::FORMS[$c][2] !== null);
    }

    /** Can a letter connect to the letter BEFORE it? Anything with a final form (and tatweel) can. */
    private static function acceptsJoin(int $c): bool
    {
        // The LAM-ALEF pair was handled above, so a bare alef here is just a right-joining letter.
        return $c === self::TATWEEL || (isset(self::FORMS[$c]) && self::FORMS[$c][1] !== null);
    }

    /**
     * Left-to-right visual order for a right-to-left paragraph: the sequence
     * of direction runs is reversed, and inside an RTL run the characters are.
     *
     * @param  int[]  $codes
     */
    private static function reorder(array $codes): string
    {
        // 1) classify: 'R' Arabic, 'L' Latin/digits, 'N' neutral
        $runs = [];
        foreach ($codes as $c) {
            $type = self::classify($c);
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last]['t'] === $type) {
                $runs[$last]['c'][] = $c;
            } else {
                $runs[] = ['t' => $type, 'c' => [$c]];
            }
        }

        // 2) neutrals between two LTR runs stay LTR ("Ali Baba", "12 : 30"); every other neutral follows the RTL base direction
        foreach ($runs as $i => $run) {
            if ($run['t'] === 'N') {
                $before = $runs[$i - 1]['t'] ?? null;
                $after = $runs[$i + 1]['t'] ?? null;
                $runs[$i]['t'] = ($before === 'L' && $after === 'L') ? 'L' : 'R';
            }
        }

        // 3) merge neighbours that ended up in the same direction
        $merged = [];
        foreach ($runs as $run) {
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last]['t'] === $run['t']) {
                $merged[$last]['c'] = array_merge($merged[$last]['c'], $run['c']);
            } else {
                $merged[] = $run;
            }
        }

        // 4) reverse run order; reverse (and mirror brackets) inside RTL runs
        $out = '';
        foreach (array_reverse($merged) as $run) {
            $chars = array_map(fn (int $c) => mb_chr($c, 'UTF-8'), $run['t'] === 'R' ? array_reverse($run['c']) : $run['c']);
            if ($run['t'] === 'R') {
                $chars = array_map(fn (string $ch) => self::MIRRORED[$ch] ?? $ch, $chars);
            }
            $out .= implode('', $chars);
        }

        return $out;
    }

    private static function classify(int $c): string
    {
        // Arabic letters (base block, extended, presentation forms) but NOT Arabic-Indic digits
        if (($c >= 0x0600 && $c <= 0x06FF && ! self::isDigit($c)) || ($c >= 0x0750 && $c <= 0x077F)
            || ($c >= 0xFB50 && $c <= 0xFDFF) || ($c >= 0xFE70 && $c <= 0xFEFF)) {
            // punctuation inside the Arabic block (، ؛ ؟ ٪) is neutral
            return in_array($c, [0x060C, 0x061B, 0x061F, 0x066A, 0x066B, 0x066C], true) ? 'N' : 'R';
        }

        $ch = mb_chr($c, 'UTF-8');

        return preg_match('/[\p{L}\p{N}]/u', $ch) || self::isDigit($c) ? 'L' : 'N';
    }

    private static function isDigit(int $c): bool
    {
        return ($c >= 0x0660 && $c <= 0x0669) || ($c >= 0x06F0 && $c <= 0x06F9);
    }
}
