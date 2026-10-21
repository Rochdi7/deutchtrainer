<?php

namespace App\Services\Import\Pdf;

/**
 * A horizontal cluster of glyphs on one line, i.e. one table cell or one paragraph.
 *
 * wkhtmltopdf/Qt writes glyphs in *visual* order of bidi runs: left-to-right runs have
 * increasing x, right-to-left runs decreasing x. Inside a run the glyph order is already
 * logical, brackets inside RTL runs carry the mirrored character. Right-aligned cells
 * (the Arabic column, the last column of a table) are laid out as RTL paragraphs, so
 * their runs must be read from right to left to recover the logical text.
 */
final class Cell
{
    public readonly float $x0;

    public readonly float $x1;

    public readonly float $size;

    public readonly bool $bold;

    private ?string $text = null;

    /** @var array<int, array{glyphs: Glyph[], dir: int, x0: float, x1: float}>|null */
    private ?array $runs = null;

    private const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '«' => '»', '»' => '«', '‹' => '›', '›' => '‹', '<' => '>', '>' => '<'];

    /** @param  Glyph[]  $glyphs  in content-stream order */
    public function __construct(public readonly array $glyphs, private readonly Geometry $geo)
    {
        $xs = array_map(fn (Glyph $g) => $g->x, $glyphs);
        $this->x0 = min($xs);
        $this->x1 = max(array_map(fn (Glyph $g) => $g->x + $this->advance($g), $glyphs));

        $sizes = array_map(fn (Glyph $g) => $g->size, $glyphs);
        sort($sizes);
        $this->size = $sizes[intdiv(count($sizes), 2)];

        $letters = array_filter($glyphs, fn (Glyph $g) => ! $g->isSpace());
        $boldCount = count(array_filter($letters, fn (Glyph $g) => $g->bold));
        $this->bold = count($letters) > 0 && $boldCount / count($letters) >= 0.6;
    }

    public function text(): string
    {
        return $this->text ??= $this->buildText();
    }

    public function isArabicDominant(): bool
    {
        $ar = preg_match_all('/\p{Arabic}/u', $this->text());
        $la = preg_match_all('/\p{Latin}/u', $this->text());

        return $ar > 0 && $ar >= $la;
    }

    public function hasArabic(): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $this->text());
    }

    public function hasLatin(): bool
    {
        return (bool) preg_match('/\p{Latin}/u', $this->text());
    }

    public function isRightAligned(): bool
    {
        return $this->geo->isRightAligned($this->x0, $this->x1);
    }

    /** Split into two cells at a horizontal position (glyphs left of $x go to the first cell). */
    public function splitAtX(float $x): array
    {
        $left = array_values(array_filter($this->glyphs, fn (Glyph $g) => $g->x < $x));
        $right = array_values(array_filter($this->glyphs, fn (Glyph $g) => $g->x >= $x));
        if ($left === [] || $right === []) {
            return [$this];
        }

        return [new self($left, $this->geo), new self($right, $this->geo)];
    }

    /** X of a glyph that starts a word at (roughly) the given column position, if any. */
    public function wordStartNear(float $x, float $tolerance = 2.5): ?float
    {
        $sorted = $this->sortedByX();
        foreach ($sorted as $i => $g) {
            if ($g->isSpace() || abs($g->x - $x) > $tolerance) {
                continue;
            }
            // Must be the first glyph of the cell or follow a different word.
            if ($i === 0 || $sorted[$i - 1]->isSpace() || $sorted[$i - 1]->x < $g->x - 1.5) {
                return $g->x - 0.05;
            }
        }

        return null;
    }

    /**
     * A Latin cell that collides with the Arabic cell of the same row (no gap between them)
     * is returned as two cells; otherwise null. A " – " / "(" before the Arabic marks a
     * translation inside one cell ("Spanisch – الإسبانية") and is left alone.
     */
    public function splitBeforeArabicTail(): ?array
    {
        $runs = $this->runs();
        $firstArabic = null;
        foreach ($runs as $i => $run) {
            if ($this->runHasArabic($run)) {
                $firstArabic = $i;
                break;
            }
        }
        if ($firstArabic === null || $firstArabic === 0) {
            return null;
        }
        $before = array_slice($runs, 0, $firstArabic);
        $latinLetters = 0;
        foreach ($before as $run) {
            foreach ($run['glyphs'] as $g) {
                $latinLetters += $g->isLatin() ? 1 : 0;
            }
        }
        if ($latinLetters < 2) {
            return null;
        }
        // Last non-space glyph before the Arabic run (visual order).
        $prevGlyphs = $before[$firstArabic - 1]['glyphs'];
        usort($prevGlyphs, fn (Glyph $a, Glyph $b) => $a->x <=> $b->x);
        $junction = null;
        for ($i = count($prevGlyphs) - 1; $i >= 0; $i--) {
            if (! $prevGlyphs[$i]->isSpace()) {
                $junction = $prevGlyphs[$i]->text;
                break;
            }
        }
        if ($junction === null || preg_match('/[–\-(\[:=]$/u', $junction)) {
            return null;
        }

        $split = $this->splitAtX($runs[$firstArabic]['x0'] - 0.05);

        return count($split) === 2 ? $split : null;
    }

    // ---------------------------------------------------------------------

    /** @return array<int, array{glyphs: Glyph[], dir: int, x0: float, x1: float}> visual (left-to-right) order */
    private function runs(): array
    {
        if ($this->runs !== null) {
            return $this->runs;
        }

        $runs = [];
        $cur = null;
        $prev = null;
        foreach ($this->glyphs as $g) {
            if ($cur === null) {
                $cur = ['glyphs' => [$g], 'dir' => 0, 'script' => $this->script($g)];
                $prev = $g;

                continue;
            }
            $dx = $g->x - $prev->x;
            $maxAdv = max(12.0, 1.7 * max($g->size, $prev->size));
            $script = $this->script($g);

            $attach = true;
            if ($g->isCombining() || abs($dx) < 0.6) {
                $attach = true;
            } elseif (abs($dx) > $maxAdv) {
                $attach = false;
            } elseif ($cur['dir'] !== 0 && ($dx > 0) !== ($cur['dir'] > 0)) {
                $attach = false;
            } elseif ($script === 'ar') {
                // An Arabic letter continues a run only if that run is Arabic or neutral-and-RTL;
                // "(" followed by Arabic to its right belongs to the LTR context instead.
                $attach = $cur['script'] === 'ar' || ($cur['script'] === null && $dx < 0);
            } elseif ($script === 'la') {
                $attach = $cur['script'] !== 'ar';
            }

            if ($attach) {
                if ($cur['dir'] === 0 && ! $g->isCombining() && abs($dx) >= 0.6) {
                    $cur['dir'] = $dx > 0 ? 1 : -1;
                }
                $cur['script'] ??= $script;
                $cur['glyphs'][] = $g;
            } else {
                $runs[] = $cur;
                $cur = ['glyphs' => [$g], 'dir' => 0, 'script' => $script];
            }
            $prev = $g;
        }
        if ($cur !== null) {
            $runs[] = $cur;
        }

        foreach ($runs as &$run) {
            $xs = array_map(fn (Glyph $g) => $g->x, $run['glyphs']);
            $run['x0'] = min($xs);
            $run['x1'] = max(array_map(fn (Glyph $g) => $g->x + $this->advance($g), $run['glyphs']));
            // A single glyph has no direction: an Arabic letter is RTL, anything else neutral.
            if ($run['dir'] === 0 && count($run['glyphs']) === 1 && $run['glyphs'][0]->isArabic()) {
                $run['dir'] = -1;
            }
        }
        unset($run);

        usort($runs, fn ($a, $b) => $a['x0'] <=> $b['x0']);

        return $this->runs = $runs;
    }

    private function buildText(): string
    {
        $visual = $this->runs();
        $rtl = $this->isRightAligned();
        $order = $rtl ? array_reverse($visual, true) : $visual;

        // Visual gaps between neighbouring runs (index = left run).
        $spaceAfter = [];
        for ($i = 0; $i < count($visual) - 1; $i++) {
            $gap = $visual[$i + 1]['x0'] - $visual[$i]['x1'];
            $spaceAfter[$i] = $gap > 0.3 * $this->size || $gap < -0.6 * $this->size;
        }

        $out = '';
        $prevIndex = null;
        foreach ($order as $i => $run) {
            $t = implode('', array_map(fn (Glyph $g) => $g->text, $run['glyphs']));
            if ($run['dir'] < 0 || ($rtl && $run['dir'] === 0)) {
                $t = strtr($t, self::MIRROR);
            } elseif ($rtl) {
                // In an RTL paragraph the punctuation that logically ends a Latin run is drawn
                // to its left ("Sie?" -> "?Sie", "Wie heißen Sie?)" -> "(?Wie heißen Sie").
                if (preg_match('/^([?!.,;:…)\]}»]+|\((?=[^)]*$)[?!.,;:…]*)(\p{L}.*)$/u', $t, $m)) {
                    $t = $m[2].strtr($m[1], self::MIRROR);
                }
            }
            if ($prevIndex !== null) {
                $leftIndex = $rtl ? $i : $prevIndex;
                $needSpace = $spaceAfter[$leftIndex] ?? false;
                if ($needSpace && $out !== '' && ! str_ends_with($out, ' ') && ! str_starts_with($t, ' ')) {
                    $out .= ' ';
                }
            }
            $out .= $t;
            $prevIndex = $i;
        }

        $out = preg_replace('/\s+([\p{Mn}])/u', '$1', $out);   // detached harakat
        $out = preg_replace('/\s{2,}/u', ' ', $out);
        $out = trim($out);

        // Right-aligned mixed cells sometimes come out as "– Arabic Latin": rotate to "Latin – Arabic".
        if (preg_match('/^[–\-]\s*(\p{Arabic}[^\p{Latin}]*?)\s+(\p{Latin}.*)$/u', $out, $m)) {
            $out = trim($m[2]).' – '.trim($m[1]);
        }

        return $out;
    }

    private function script(Glyph $g): ?string
    {
        return $g->isArabic() ? 'ar' : ($g->isLatin() ? 'la' : null);
    }

    private function runHasArabic(array $run): bool
    {
        foreach ($run['glyphs'] as $g) {
            if ($g->isArabic()) {
                return true;
            }
        }

        return false;
    }

    /** @return Glyph[] */
    private function sortedByX(): array
    {
        $s = $this->glyphs;
        usort($s, fn (Glyph $a, Glyph $b) => $a->x <=> $b->x);

        return $s;
    }

    /** Rough horizontal advance of a glyph (no font metrics available). */
    private function advance(Glyph $g): float
    {
        if ($g->isSpace()) {
            return 0.3 * $g->size;
        }

        return ($g->isArabic() ? 0.55 : 0.6) * $g->size * max(1, mb_strlen($g->text));
    }
}
