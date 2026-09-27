<?php

namespace Tests\Unit;

use App\Support\ArabicText;
use PHPUnit\Framework\TestCase;

/**
 * Expected strings are spelled out as Unicode Presentation Forms-B code points
 * (from the Unicode chart), not produced by the class under test, so a wrong
 * joining/ordering rule can't hide behind a matching wrong expectation.
 */
class ArabicTextTest extends TestCase
{
    /** @param int[] $codes */
    private function str(array $codes): string
    {
        return implode('', array_map(fn ($c) => mb_chr($c, 'UTF-8'), $codes));
    }

    public function test_text_without_arabic_is_untouched(): void
    {
        $this->assertSame('ABC 1234', ArabicText::forPdf('ABC 1234'));
        $this->assertSame('2026-09-21 10:30:00', ArabicText::forPdf('2026-09-21 10:30:00'));
        $this->assertSame('', ArabicText::forPdf(null));
        $this->assertSame('', ArabicText::forPdf(''));
    }

    public function test_letters_take_initial_medial_and_final_forms_in_visual_order(): void
    {
        // محمد = meem(initial) hah(medial) meem(medial) dal(final); a right-to-left word is painted reversed
        $this->assertSame(
            $this->str([0xFEAA, 0xFEE4, 0xFEA4, 0xFEE3]),
            ArabicText::forPdf('محمد'),
        );
    }

    public function test_a_lone_letter_is_isolated(): void
    {
        $this->assertSame($this->str([0xFE8F]), ArabicText::forPdf('ب'));
    }

    public function test_right_joining_letters_do_not_connect_to_the_next_letter(): void
    {
        // سلام = seen(initial) lam+alef(final ligature) meem(isolated — alef never joins forward)
        $this->assertSame(
            $this->str([0xFEE1, 0xFEFC, 0xFEB3]),
            ArabicText::forPdf('سلام'),
        );
    }

    public function test_lam_alef_becomes_the_single_ligature_glyph(): void
    {
        $this->assertSame($this->str([0xFEFB]), ArabicText::forPdf('لا'));
        $this->assertSame($this->str([0xFEF7]), ArabicText::forPdf('لأ'));
    }

    public function test_the_ta_marbuta_only_takes_its_final_form(): void
    {
        // شاحنة = sheen(initial) alef(final) hah(initial) noon(medial) teh-marbuta(final)
        $this->assertSame(
            $this->str([0xFE94, 0xFEE8, 0xFEA3, 0xFE8E, 0xFEB7]),
            ArabicText::forPdf('شاحنة'),
        );
    }

    public function test_latin_and_digits_inside_arabic_keep_their_own_order_and_sit_on_the_left(): void
    {
        // Arabic first in reading order = rightmost; the Latin/number run is painted before (left of) it
        $this->assertSame(
            'ABC 123 '.$this->str([0xFE94, 0xFEE8, 0xFEA3, 0xFE8E, 0xFEB7]),
            ArabicText::forPdf('شاحنة ABC 123'),
        );
    }

    public function test_brackets_are_mirrored_with_the_reversal(): void
    {
        // "(ب)" → visual: the logical closing bracket ends up on the left and is mirrored to "("
        $this->assertSame('('.$this->str([0xFE8F]).')', ArabicText::forPdf('(ب)'));
    }

    public function test_diacritics_are_dropped(): void
    {
        $this->assertSame(ArabicText::forPdf('محمد'), ArabicText::forPdf("مُحَمَّد"));
    }

    public function test_each_line_is_handled_on_its_own(): void
    {
        $this->assertSame(
            $this->str([0xFE8F])."\n".$this->str([0xFE8F]),
            ArabicText::forPdf("ب\nب"),
        );
    }
}
