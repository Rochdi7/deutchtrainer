<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\Import\ImportPersister;
use App\Services\Import\ImportSchemaValidator;
use App\Services\Import\LektionParser;
use App\Services\Import\PdfTextExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ImportLektion extends Command
{
    protected $signature = 'import:lektion
        {pdf : Path to the Lektion PDF (or a JSON payload with --json)}
        {number : Lektion number}
        {--title= : Lektion title}
        {--json : Treat the first argument as an already-parsed JSON payload instead of a PDF}
        {--dump= : Write the parsed JSON payload to this file before saving}
        {--dry-run : Parse only, do not write to the database}';

    protected $description = 'Import a DaF kompakt Lektion PDF (pdftotext → Claude → DB) without the web UI';

    public function handle(
        PdfTextExtractor $extractor,
        LektionParser $parser,
        ImportSchemaValidator $validator,
        ImportPersister $persister,
    ): int {
        $source = $this->argument('pdf');
        $number = (int) $this->argument('number');

        if (! is_file($source)) {
            $this->error("File not found: {$source}");

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $payload = json_decode(file_get_contents($source), true);
            if (! is_array($payload)) {
                $this->error('Invalid JSON file.');

                return self::FAILURE;
            }
            $payload += ['conjugations' => [], 'dialog_lines' => [], 'grammar_notes' => []];
            $payload = $parser->postProcess($payload);
            $pdfPath = null;
        } else {
            $this->info('Extracting text with pdftotext …');
            $text = $extractor->extract($source);
            $this->line(sprintf('  %d lines of text', substr_count($text, "\n") + 1));

            $this->info('Sending chunks to Claude …');
            $bar = null;
            $payload = $parser->parse($text, $number, function (int $done, int $total) use (&$bar) {
                $bar ??= $this->output->createProgressBar($total);
                $bar->setProgress($done);
            });
            $bar?->finish();
            $this->newLine();

            $pdfPath = 'lektion-'.$number.'-'.basename($source);
            Storage::disk(config('trainer.import.disk'))->put($pdfPath, file_get_contents($source));
        }

        $result = $validator->validate($payload);
        if (! $result['valid']) {
            $this->error('Payload failed schema validation:');
            foreach (array_slice($result['errors'], 0, 20) as $e) {
                $this->line('  - '.$e);
            }

            return self::FAILURE;
        }

        if ($dump = $this->option('dump')) {
            file_put_contents($dump, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info("Payload written to {$dump}");
        }

        $this->line(sprintf('Parsed: %d words, %d conjugations, %d dialog lines, %d grammar notes',
            count($payload['words']), count($payload['conjugations']), count($payload['dialog_lines']), count($payload['grammar_notes'])));

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $saved = $persister->persist($payload, $number, $this->option('title'), $pdfPath);
        $this->info(sprintf('Saved Lektion %d: %d new words (%d skipped as duplicates), %d conjugations, %d dialog lines, %d grammar notes',
            $number, $saved['words'], $saved['skipped'], $saved['conjugations'], $saved['dialog_lines'], $saved['grammar_notes']));

        $rows = Category::where('lektion_id', $saved['lektion']->id)->withCount('words')->orderBy('sort')->get()
            ->map(fn ($c) => [$c->name, $c->words_count])->all();
        $this->table(['Category', 'Words'], $rows);

        return self::SUCCESS;
    }
}
