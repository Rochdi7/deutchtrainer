<?php

namespace App\Jobs;

use App\Models\ImportPreview;
use App\Services\Import\ImportPipeline;
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

    public function handle(ImportPipeline $pipeline): void
    {
        $preview = $this->preview;
        $preview->update(['status' => 'processing', 'error' => null]);

        try {
            $absolute = Storage::disk(config('trainer.import.disk'))->path($preview->pdf_path);

            $result = $pipeline->run($absolute, $preview->lektion_number, function (int $done, int $total) use ($preview) {
                $preview->update(['chunks_done' => $done, 'chunks_total' => $total]);
            });

            $rawText = $result['text'];
            if ($result['warnings'] !== []) {
                $rawText = "## Hinweise\n- ".implode("\n- ", $result['warnings'])."\n\n".$rawText;
            }

            $preview->update(['status' => 'ready', 'payload' => $result['payload'], 'raw_text' => $rawText]);
        } catch (Throwable $e) {
            // The outcome is recorded on the preview; with the sync queue driver a rethrow would
            // turn the upload request into a 500 page instead of the preview with its error.
            $preview->update(['status' => 'failed', 'error' => $e->getMessage()]);
            report($e);
        }
    }

    public function failed(Throwable $e): void
    {
        $this->preview->update(['status' => 'failed', 'error' => $e->getMessage()]);
    }
}
