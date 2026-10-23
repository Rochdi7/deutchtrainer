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

    /** Whole-sentence comparison: case, punctuation, spacing and Umlaut spelling are ignored, articles are not. */
    public function sentenceMatches(?string $given, string $expected): bool
    {
        if ($given === null) {
            return false;
        }
        $norm = function (string $s): string {
            $s = mb_strtolower(trim($s));
            $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
            $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);

            return preg_replace('/\s+/u', ' ', trim($s));
        };
        $g = $norm($given);

        return $g !== '' && $g === $norm($expected);
    }

    /** English free-text answers: case, punctuation and leading "the/a/an" are ignored. */
    public function englishMatches(?string $given, string $expected): bool
    {
        if ($given === null) {
            return false;
        }
        $norm = function (string $s): string {
            $s = mb_strtolower(trim($s));
            $s = str_replace(["i'm", "it's", "don't", "doesn't", "i've"], ['i am', 'it is', 'do not', 'does not', 'i have'], $s);
            $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
            $s = preg_replace('/\b(the|a|an)\s+/u', '', $s);

            return preg_replace('/\s+/u', ' ', trim($s));
        };
        $g = $norm($given);

        return $g !== '' && $g === $norm($expected);
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
        $s = str_replace(["i'm", "it's", "don't", "doesn't", "i've"], ['i am', 'it is', 'do not', 'does not', 'i have'], $s);
        $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
        $s = preg_replace('/\s+/u', ' ', trim($s));
        // Optional article.
        $s = preg_replace('/^(der\/die|die\/der|der|die|das|ein|eine)\s+/u', '', $s);
        // Umlaut tolerance: fold both sides to ASCII digraphs.
        $s = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $s);

        return trim($s);
    }
}
