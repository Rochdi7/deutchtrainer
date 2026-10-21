<?php

namespace App\Services\Import\Pdf;

final class Document
{
    /** @param  Line[]  $lines  in reading order */
    public function __construct(
        public readonly array $lines,
        public readonly Geometry $geometry,
        public readonly int $pages,
        public readonly bool $hasFontInfo,
    ) {}

    public function isEmpty(): bool
    {
        foreach ($this->lines as $line) {
            if (trim($line->text()) !== '') {
                return false;
            }
        }

        return true;
    }

    /** Human-readable dump: one line per PDF line, cells separated by " | ". */
    public function toText(): string
    {
        $out = [];
        $page = 0;
        foreach ($this->lines as $line) {
            if ($line->page !== $page) {
                $page = $line->page;
                $out[] = '';
                $out[] = '----- Seite '.$page.' -----';
            }
            $t = $line->text();
            if ($t === '') {
                continue;
            }
            if ($line->count() === 1 && $line->isBold() && $this->geometry->isAtMargin($line->first()->x0)) {
                $out[] = '';
            }
            $out[] = $t;
        }

        return trim(implode("\n", $out));
    }
}
