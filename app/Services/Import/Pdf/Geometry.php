<?php

namespace App\Services\Import\Pdf;

/** Page-level measurements shared by all cells of a document. */
final class Geometry
{
    public function __construct(
        public float $leftMargin = 0.0,
        public float $pageRight = 0.0,
        public float $bodySize = 8.0,
    ) {}

    /** Cells ending here are right-aligned table cells (or overflowing Arabic). */
    public function isRightAligned(float $x0, float $x1): bool
    {
        return $x1 >= $this->pageRight - 8 && $x0 > $this->leftMargin + 40;
    }

    public function isAtMargin(float $x0): bool
    {
        return $x0 <= $this->leftMargin + 3;
    }
}
