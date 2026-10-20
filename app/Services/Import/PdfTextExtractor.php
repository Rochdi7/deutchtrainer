<?php

namespace App\Services\Import;

use Spatie\PdfToText\Pdf;

class PdfTextExtractor
{
    public function __construct(private readonly TextNormalizer $normalizer) {}

    /** Returns normalized UTF-8 text with layout preserved. */
    public function extract(string $absolutePdfPath): string
    {
        $binary = config('trainer.import.pdftotext_path') ?: null;

        $pdf = new Pdf($binary);
        $pdf->setPdf($absolutePdfPath);
        // -layout keeps the two-column list structure; -enc UTF-8 is required or Arabic is lost on Windows builds.
        $pdf->setOptions(['layout', 'enc UTF-8']);

        return $this->normalizer->normalize($pdf->text());
    }
}
