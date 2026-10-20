<?php

namespace App\Services\Quiz;

class AnswerChecker
{
    /** Compare a typed German answer with the expected one: case-insensitive, article optional, Umlaut tolerant. */
    public function typingMatches(?string $given, string $expected): bool
    {
        if ($given === null) {
            return false;
        }

        $g = $this->normalizeGerman($given);
        if ($g === '') {
            return false;
        }

        $e = $this->normalizeGerman($expected);
        if ($g === $e) {
            return true;
        }

        // Expected may hold variants: "Jura / die Rechtswissenschaften", "der Türke / die Türkin".
        foreach (preg_split('/\s*\/\s*/', $expected) as $variant) {
            if ($this->normalizeGerman($variant) === $g) {
                return true;
            }
        }

        return false;
    }

    /** Exact match for multiple-choice options (after trimming). */
    public function optionMatches(?string $given, string $expected): bool
    {
        return $given !== null && trim($given) === trim($expected);
    }

    public function normalizeGerman(string $s): string
    {
        $s = mb_strtolower(trim($s));
        // Drop parentheticals such as "(sich)", "(PLZ)", "(Pl.)", "(als …)".
        $s = preg_replace('/\([^)]*\)/u', ' ', $s);
        // Drop punctuation and ellipses.
        $s = preg_replace('/[?!.,;:…"\'„“]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        // Optional article.
        $s = preg_replace('/^(der\/die|die\/der|der|die|das|ein|eine)\s+/u', '', $s);
        // Umlaut tolerance: fold both sides to ASCII digraphs.
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);

        return trim($s);
    }
}
