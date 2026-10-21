<?php

namespace App\Services\Import\Pdf;

/** One positioned text fragment from the PDF content stream (normally a single character). */
final class Glyph
{
    public function __construct(
        public readonly float $x,
        public readonly float $y,
        public readonly string $text,
        public readonly float $size,
        public readonly bool $bold,
        public readonly int $seq,
    ) {}

    public function isSpace(): bool
    {
        return trim($this->text) === '';
    }

    public function isArabic(): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $this->text);
    }

    public function isLatin(): bool
    {
        return (bool) preg_match('/\p{Latin}/u', $this->text);
    }

    /** Letters or digits of any script (bidi "strong" or number). */
    public function isWordChar(): bool
    {
        return (bool) preg_match('/[\p{L}\p{N}]/u', $this->text);
    }

    public function isCombining(): bool
    {
        return (bool) preg_match('/^\p{Mn}+$/u', $this->text);
    }
}
