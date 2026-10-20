<?php

namespace Database\Seeders;

use App\Services\Import\ImportPersister;
use App\Services\Import\ImportSchemaValidator;
use App\Services\Import\LektionParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Seeds Lektion 1 from database/seeders/data/lektion1.json.
 *
 * The JSON is the exact payload the PDF pipeline (pdftotext → Claude) produces for
 * DaF_kompakt_A1_Lektion1_Resume_DE-AR.pdf, and it goes through the same schema
 * validation and persister as a UI/CLI import.
 */
class LektionOneSeeder extends Seeder
{
    public function run(): void
    {
        $file = database_path('seeders/data/lektion1.json');
        $payload = json_decode(file_get_contents($file), true);
        if (! is_array($payload)) {
            throw new RuntimeException("Could not decode {$file}");
        }

        $payload += ['conjugations' => [], 'dialog_lines' => [], 'grammar_notes' => []];
        $payload = app(LektionParser::class)->postProcess($payload);

        $result = app(ImportSchemaValidator::class)->validate($payload);
        if (! $result['valid']) {
            throw new RuntimeException('lektion1.json failed schema validation: '.implode('; ', array_slice($result['errors'], 0, 5)));
        }

        $pdfPath = null;
        $sourcePdf = database_path('seeders/data/lektion1.pdf');
        if (is_file($sourcePdf)) {
            $pdfPath = 'lektion-1-DaF_kompakt_A1_Lektion1_Resume_DE-AR.pdf';
            Storage::disk(config('trainer.import.disk'))->put($pdfPath, file_get_contents($sourcePdf));
        }

        $saved = app(ImportPersister::class)->persist($payload, 1, 'Lektion 1 – Guten Tag!', $pdfPath);

        $this->command?->info(sprintf(
            'Lektion 1: %d words (%d skipped), %d conjugations, %d dialog lines, %d grammar notes',
            $saved['words'], $saved['skipped'], $saved['conjugations'], $saved['dialog_lines'], $saved['grammar_notes']
        ));
    }
}
