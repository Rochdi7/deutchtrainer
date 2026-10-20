<?php

namespace App\Jobs;

use App\Models\ImportPreview;
use App\Services\Import\LektionParser;
use App\Services\Import\PdfTextExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportLektionPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public ImportPreview $preview) {}

    public function handle(PdfTextExtractor $extractor, LektionParser $parser): void
    {
        $preview = $this->preview;
        $preview->update(['status' => 'processing', 'error' => null]);

        try {
            $absolute = Storage::disk(config('trainer.import.disk'))->path($preview->pdf_path);
            $text = $extractor->extract($absolute);
            $preview->update(['raw_text' => $text]);

            $payload = $parser->parse($text, $preview->lektion_number, function (int $done, int $total) use ($preview) {
                $preview->update(['chunks_done' => $done, 'chunks_total' => $total]);
            });

            $preview->update(['status' => 'ready', 'payload' => $payload]);
        } catch (Throwable $e) {
            $preview->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->preview->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}
