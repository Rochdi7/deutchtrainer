<?php

namespace App\Services\Import\Pdf;

use RuntimeException;
use Smalot\PdfParser\Config;
use Smalot\PdfParser\Font;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Pure-PHP replacement for `pdftotext -layout`: reads every positioned glyph of the PDF,
 * groups glyphs into lines by their y coordinate and into cells by horizontal gaps.
 * Because German and Arabic cells of a table row share the same y, the two columns are
 * paired exactly, which pdftotext could not do for RTL text.
 */
class PdfLayoutReader
{
    public function read(string $absolutePdfPath): Document
    {
        if (! is_file($absolutePdfPath)) {
            throw new RuntimeException("PDF not found: {$absolutePdfPath}");
        }

        $config = new Config;
        $config->setDataTmFontInfoHasToBeIncluded(true);
        $config->setRetainImageContent(false);

        try {
            $pdf = (new Parser([], $config))->parseFile($absolutePdfPath);
        } catch (Throwable $e) {
            throw new RuntimeException('The PDF could not be parsed: '.$e->getMessage(), previous: $e);
        }

        $geo = new Geometry;
        $pages = $pdf->getPages();
        $hasFontInfo = true;
        $pageLines = [];

        foreach ($pages as $pi => $page) {
            $glyphs = $this->glyphs($page, $hasFontInfo);
            if ($glyphs === []) {
                continue;
            }
            $pageLines[] = $this->lines($glyphs, $pi + 1, $geo);
        }

        $all = array_merge(...$pageLines);
        $this->measure($all, $geo);
        $lines = $this->mergePages($pageLines);

        return new Document($lines, $geo, count($pages), $hasFontInfo);
    }

    /** @return Glyph[] */
    private function glyphs(Page $page, bool &$hasFontInfo): array
    {
        $fonts = $page->getFonts();
        $boldByFont = [];
        foreach ($fonts as $id => $font) {
            $boldByFont[$id] = $this->isBoldFont($font);
        }

        $glyphs = [];
        foreach ($page->getDataTm() as $seq => $item) {
            $tm = $item[0];
            $text = $this->normalize((string) $item[1]);
            if ($text === '') {
                continue;
            }
            $scale = abs((float) $tm[0]) ?: abs((float) $tm[1]) ?: 1.0;
            $fontId = $item[2] ?? null;
            $fontSize = isset($item[3]) ? (float) $item[3] : null;
            if ($fontSize === null || $fontSize <= 0) {
                $hasFontInfo = false;
                $size = 8.0 * $scale;
            } else {
                $size = $fontSize * $scale;
            }
            $bold = $fontId !== null && ($boldByFont[$fontId] ?? $boldByFont['/'.$fontId] ?? false);

            $glyphs[] = new Glyph((float) $tm[4], (float) $tm[5], $text, $size, $bold, $seq);
        }

        return $glyphs;
    }

    private function isBoldFont(Font $font): bool
    {
        $name = $font->getName();
        $details = $font->getDetails();
        $base = $details['BaseFont'] ?? '';
        if (preg_match('/bold|black|heavy|semibold|demibold/i', $name.' '.$base)) {
            return true;
        }
        $descriptor = $details['FontDescriptor'] ?? [];
        if (is_array($descriptor)) {
            if (isset($descriptor['StemV']) && (float) $descriptor['StemV'] >= 120) {
                return true;
            }
            if (isset($descriptor['Flags']) && (((int) $descriptor['Flags']) & (1 << 18))) {
                return true; // ForceBold
            }
        }

        return false;
    }

    /** Fold Arabic presentation forms, strip bidi controls, keep everything else as typed. */
    private function normalize(string $text): string
    {
        // Drop NUL bytes and anything that is not valid UTF-8 (a broken ToUnicode entry
        // would otherwise make every regex on the cell fail).
        $text = str_replace("\0", '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $clean = function_exists('iconv') ? @iconv('UTF-8', 'UTF-8//IGNORE', $text) : false;
            $text = $clean !== false ? $clean : preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text);
        }
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}\x{00AD}]/u', '', $text) ?? $text;
        if ($text === '') {
            return '';
        }
        if (class_exists(\Normalizer::class)) {
            if (preg_match('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text)) {
                $text = \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
            } else {
                $text = \Normalizer::normalize($text, \Normalizer::FORM_C) ?: $text;
            }
        }

        return $text;
    }

    /**
     * @param  Glyph[]  $glyphs
     * @return Line[]
     */
    private function lines(array $glyphs, int $page, Geometry $geo): array
    {
        usort($glyphs, fn (Glyph $a, Glyph $b) => $b->y <=> $a->y ?: $a->seq <=> $b->seq);

        $groups = [];
        $current = null;
        foreach ($glyphs as $g) {
            $tolerance = max(3.5, 0.45 * $g->size);
            if ($current === null || abs($current['y'] - $g->y) > $tolerance) {
                if ($current !== null) {
                    $groups[] = $current;
                }
                $current = ['y' => $g->y, 'glyphs' => []];
            }
            $current['glyphs'][] = $g;
        }
        if ($current !== null) {
            $groups[] = $current;
        }

        $lines = [];
        foreach ($groups as $group) {
            $cells = $this->cells($group['glyphs'], $geo);
            if ($cells !== []) {
                $lines[] = new Line($page, $group['y'], $cells);
            }
        }

        return $lines;
    }

    /**
     * @param  Glyph[]  $glyphs
     * @return Cell[]
     */
    private function cells(array $glyphs, Geometry $geo): array
    {
        $byX = $glyphs;
        usort($byX, fn (Glyph $a, Glyph $b) => $a->x <=> $b->x ?: $a->seq <=> $b->seq);

        $clusters = [];
        $cluster = [];
        $prev = null;
        foreach ($byX as $g) {
            if ($prev !== null) {
                $gap = $g->x - $prev->x;
                $threshold = max(12.0, 1.7 * max($g->size, $prev->size));
                if ($gap > $threshold) {
                    $clusters[] = $cluster;
                    $cluster = [];
                }
            }
            $cluster[] = $g;
            $prev = $g;
        }
        if ($cluster !== []) {
            $clusters[] = $cluster;
        }

        $cells = [];
        foreach ($clusters as $c) {
            $nonSpace = array_filter($c, fn (Glyph $g) => ! $g->isSpace());
            if ($nonSpace === []) {
                continue;
            }
            usort($c, fn (Glyph $a, Glyph $b) => $a->seq <=> $b->seq); // back to stream order
            $cells[] = new Cell(array_values($c), $geo);
        }
        usort($cells, fn (Cell $a, Cell $b) => $a->x0 <=> $b->x0);

        return $cells;
    }

    /**
     * Concatenate pages and drop a row that is repeated across a page break
     * (wkhtmltopdf re-prints the last table row of a page at the top of the next one).
     *
     * @param  Line[][]  $pageLines
     * @return Line[]
     */
    private function mergePages(array $pageLines): array
    {
        $lines = [];
        foreach ($pageLines as $pi => $pl) {
            foreach ($pl as $li => $line) {
                $prev = $lines === [] ? null : $lines[count($lines) - 1];
                if ($li === 0 && $prev !== null && $prev->page !== $line->page && $prev->sameAs($line)) {
                    continue;
                }
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /** Geometry from glyph positions only (cell text must not be built before this runs). */
    private function measure(array $lines, Geometry $geo): void
    {
        if ($lines === []) {
            return;
        }
        $left = INF;
        $rightBuckets = [];
        $sizes = [];
        foreach ($lines as $line) {
            $left = min($left, $line->first()->x0);
            $bucket = (int) round($line->last()->x1 / 4) * 4;
            $rightBuckets[$bucket] = ($rightBuckets[$bucket] ?? 0) + 1;
            foreach ($line->cells as $c) {
                $k = (string) round($c->size, 1);
                $sizes[$k] = ($sizes[$k] ?? 0) + count($c->glyphs);
            }
        }
        arsort($rightBuckets);
        arsort($sizes);
        $geo->leftMargin = is_finite($left) ? $left : 0.0;
        $geo->pageRight = (float) array_key_first($rightBuckets);
        $geo->bodySize = (float) array_key_first($sizes);
    }
}
