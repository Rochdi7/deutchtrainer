<?php

namespace App\Services\Import\Pdf;

final class Line
{
    /** @param  Cell[]  $cells  sorted by x0 */
    public function __construct(
        public readonly int $page,
        public readonly float $y,
        public array $cells,
    ) {}

    public function text(string $separator = ' | '): string
    {
        return implode($separator, array_map(fn (Cell $c) => $c->text(), $this->cells));
    }

    public function first(): Cell
    {
        return $this->cells[0];
    }

    public function last(): Cell
    {
        return $this->cells[count($this->cells) - 1];
    }

    public function count(): int
    {
        return count($this->cells);
    }

    public function isBold(): bool
    {
        foreach ($this->cells as $c) {
            if (! $c->bold) {
                return false;
            }
        }

        return $this->cells !== [];
    }

    public function size(): float
    {
        return $this->cells === [] ? 0.0 : max(array_map(fn (Cell $c) => $c->size, $this->cells));
    }

    /** Same glyphs at the same x positions as another line (rows repeated across a page break). */
    public function sameAs(Line $other): bool
    {
        if ($this->count() !== $other->count()) {
            return false;
        }
        foreach ($this->cells as $i => $cell) {
            $o = $other->cells[$i];
            if (abs($cell->x0 - $o->x0) > 1.5 || count($cell->glyphs) !== count($o->glyphs)) {
                return false;
            }
            $a = implode('', array_map(fn (Glyph $g) => $g->text, $cell->glyphs));
            $b = implode('', array_map(fn (Glyph $g) => $g->text, $o->glyphs));
            if ($a !== $b) {
                return false;
            }
        }

        return true;
    }
}
