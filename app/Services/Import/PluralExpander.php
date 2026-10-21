<?php

namespace App\Services\Import;

use App\Models\Category;

class PluralExpander
{
    /**
     * "Formular" + "-e"  => "Formulare"
     * "Studienfach" + "¨-er" => "Studienfächer"
     * "Lehrer" + "-" => "Lehrer"
     * "Praktikum" + "Praktika" => "Praktika"
     */
    public static function expand(string $singular, string $suffix): ?string
    {
        $suffix = trim($suffix);
        if ($suffix === '' || preg_match('/\(Pl\.\)/i', $suffix)) {
            return null;
        }

        // Strip a parenthetical abbreviation like "Postleitzahl (PLZ)".
        $base = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $singular));
        $base = trim(explode(' / ', $base)[0]);

        // Already a full form (no leading dash / umlaut marker).
        if (! preg_match('/^[¨"-]/u', $suffix)) {
            return $suffix;
        }

        $umlaut = str_starts_with($suffix, '¨') || str_starts_with($suffix, '"');
        $ending = ltrim($suffix, '¨"-');

        if ($umlaut) {
            $base = self::umlautLastVowel($base);
        }

        return $base.$ending;
    }

    private static function umlautLastVowel(string $word): string
    {
        // Umlaut the last a/o/u (or "au" -> "äu") in the word.
        if (preg_match_all('/(au|a|o|u)/iu', $word, $m, PREG_OFFSET_CAPTURE)) {
            $last = end($m[0]);
            [$match, $offset] = $last;
            $map = ['au' => 'äu', 'a' => 'ä', 'o' => 'ö', 'u' => 'ü', 'Au' => 'Äu', 'A' => 'Ä', 'O' => 'Ö', 'U' => 'Ü'];
            $rep = $map[$match] ?? $match;

            return substr($word, 0, $offset).$rep.substr($word, $offset + strlen($match));
        }

        return $word;
    }

    /** Canonical category for a free-form label when a known topic keyword matches, else null. */
    public static function matchCategory(string $label): ?string
    {
        $l = mb_strtolower(trim($label));
        if ($l === '') {
            return null;
        }

        foreach (Category::CANONICAL as $canon) {
            if (mb_strtolower($canon) === $l) {
                return $canon;
            }
        }

        return match (true) {
            str_contains($l, 'formular') => 'Formular',
            str_contains($l, 'sprachen lernen') => 'Sprachen lernen',
            str_contains($l, 'universit') || str_contains($l, 'studium') => 'Universität und Studium',
            str_contains($l, 'arbeit') || str_contains($l, 'beruf') => 'Arbeit und Beruf',
            str_contains($l, 'freizeit') || str_contains($l, 'kontinent') => 'Freizeit/Kontinente',
            str_contains($l, 'länder') || str_contains($l, 'nationalit') || str_contains($l, 'sprachen') => 'Länder-Sprachen-Nationalitäten',
            str_contains($l, 'redemittel') || str_contains($l, 'ausdr') => 'Redemittel',
            str_contains($l, 'fragew') => 'Fragewörter',
            str_contains($l, 'präposition') => 'Präpositionen',
            str_contains($l, 'partikel') => 'Partikeln',
            str_contains($l, 'adverb') => 'Adverbien',
            str_contains($l, 'adjektiv') => 'Adjektive',
            str_contains($l, 'verb') => 'Verben',
            str_contains($l, 'gramm') => 'Grammatik',
            str_contains($l, 'nomen') || str_contains($l, 'substantiv') => 'Nomen',
            default => null,
        };
    }

    /** Map a free-form category label to a canonical name, falling back to the word type. */
    public static function mapCategory(string $label, string $type): string
    {
        return self::matchCategory($label) ?? match ($type) {
            'verb' => 'Verben',
            'adjective' => 'Adjektive',
            'adverb' => 'Adverbien',
            'question_word' => 'Fragewörter',
            'preposition' => 'Präpositionen',
            'particle' => 'Partikeln',
            'expression' => 'Redemittel',
            'country', 'language', 'nationality' => 'Länder-Sprachen-Nationalitäten',
            default => 'Nomen',
        };
    }
}
