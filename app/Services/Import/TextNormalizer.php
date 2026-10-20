<?php

namespace App\Services\Import;

/**
 * Cleans pdftotext output before it is sent to the model.
 *
 * pdftotext emits Arabic as presentation-form glyphs (U+FBxx–U+FEFF) wrapped in
 * bidi control marks. NFKC folds the glyphs back to plain letters; the marks are stripped.
 */
class TextNormalizer
{
    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
        }

        // Bidi / zero-width control characters.
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $text);

        // Harakat that pdftotext detached with a space ("أي ًضا" -> "أيضًا").
        $text = preg_replace('/\s+([\x{064B}-\x{0652}\x{0670}])/u', '$1', $text);

        // Collapse runs of blank lines.
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return trim($text);
    }

    /** Split normalized text into chunks of roughly $lines lines, never splitting inside a section header block. */
    public function chunk(string $text, int $lines = 120): array
    {
        $all = explode("\n", $text);
        $chunks = [];
        $buffer = [];
        $lastHeader = null;

        foreach ($all as $line) {
            if ($this->looksLikeHeader($line)) {
                // Prefer to start a new chunk on a header when the buffer is already big enough.
                if (count($buffer) >= (int) ($lines * 0.75)) {
                    $chunks[] = implode("\n", $buffer);
                    $buffer = $lastHeader ? ['[Fortsetzung von: '.$lastHeader.']'] : [];
                    $buffer = [];
                }
                $lastHeader = trim($line);
            }

            $buffer[] = $line;

            if (count($buffer) >= $lines) {
                $chunks[] = implode("\n", $buffer);
                $buffer = $lastHeader ? ['[Fortsetzung von: '.$lastHeader.']'] : [];
            }
        }

        if (count(array_filter($buffer, fn ($l) => trim($l) !== '')) > 0) {
            $chunks[] = implode("\n", $buffer);
        }

        return $chunks;
    }

    private function looksLikeHeader(string $line): bool
    {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, ' ')) {
            return false;
        }
        // "Formular – الاستمارة", "Grammatik: ...", "Länder – Sprachen – Nationalitäten – ..."
        return (bool) preg_match('/^[A-ZÄÖÜ][\p{L}\s\/\(\)\.,-]{2,60}(–|-|:)\s/u', $line)
            && ! preg_match('/^(der|die|das)\s/u', $line);
    }
}
