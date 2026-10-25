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

    /**
     * Speech-recognition transcript vs. target word or sentence, 0–100.
     * Recognisers often drop or swap the article and spell numbers as digits, so the score is the best of
     * the whole phrase and the phrase without its article, by character similarity (words) or word overlap (sentences).
     */
    public function spokenScore(?string $heard, string $expected): int
    {
        $norm = fn (string $s): string => preg_replace('/\s+/u', ' ', trim(str_replace(
            ['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'],
            preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower(preg_replace('/\([^)]*\)/u', ' ', $s))),
        )));
        $stripArticle = fn (string $s): string => preg_replace('/^(der|die|das|ein|eine|einen|dem|den)\s+/u', '', $s);

        $h = $norm((string) $heard);
        if ($h === '') {
            return 0;
        }

        $best = 0;
        foreach (preg_split('/\s*\/\s*/', $expected) as $variant) {
            $e = $norm($variant);
            if ($e === '') {
                continue;
            }
            foreach ([[$h, $e], [$stripArticle($h), $stripArticle($e)]] as [$a, $b]) {
                if ($a === $b || preg_match('/(^| )'.preg_quote($b, '/').'( |$)/u', $a)) {
                    return 100;
                }
                $best = max($best, str_contains($b, ' ') ? $this->wordOverlap($a, $b) : $this->charSimilarity($a, $b));
            }
        }

        return $best;
    }

    /** A one-letter slip in a short word is usually another word (Fisch / Tisch), so those stay below the pass mark. */
    private function charSimilarity(string $a, string $b): int
    {
        $max = max(strlen($a), strlen($b));
        if ($max === 0) {
            return 0;
        }
        $score = (int) round((1 - levenshtein($a, $b) / $max) * 100);

        return $max <= 5 && $a !== $b ? min($score, 75) : $score;
    }

    /** Share of expected words that were heard (each word counted once, near-misses of 1 letter allowed). */
    private function wordOverlap(string $heard, string $expected): int
    {
        $pool = explode(' ', $heard);
        $want = explode(' ', $expected);
        $hit = 0;
        foreach ($want as $w) {
            foreach ($pool as $i => $p) {
                if ($p === $w || (strlen($w) > 3 && levenshtein($p, $w) <= 1)) {
                    $hit++;
                    unset($pool[$i]);
                    break;
                }
            }
        }

        return (int) round($hit * 100 / count($want));
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
