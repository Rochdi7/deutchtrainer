<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\Import\ImportPersister;
use App\Services\Import\ImportPipeline;
use App\Services\Import\ImportSchemaValidator;
use App\Services\Import\LektionParser;
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
        {--text= : Write the extracted layout text to this file}
        {--dry-run : Parse only, do not write to the database}';

    protected $description = 'Import a DaF kompakt Lektion PDF (layout reader → rule-based parser → DB) without the web UI';

    public function handle(
        ImportPipeline $pipeline,
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
            $this->info(sprintf('Reading PDF layout (engine: %s) …', $pipeline->engine()));
            $bar = null;
            $result = $pipeline->run($source, $number, function (int $done, int $total) use (&$bar) {
                if ($total > 1) {
                    $bar ??= $this->output->createProgressBar($total);
                    $bar->setProgress($done);
                }
            });
            $bar?->finish();
            if ($bar) {
                $this->newLine();
            }
            $payload = $result['payload'];

            $this->line(sprintf('  %d lines of layout text', substr_count($result['text'], "\n") + 1));
            foreach ($result['warnings'] as $w) {
                $this->warn('  ! '.$w);
            }
            if ($textFile = $this->option('text')) {
                file_put_contents($textFile, $result['text']);
                $this->info("Layout text written to {$textFile}");
            }

            $pdfPath = 'lektion-'.$number.'-'.basename($source);
            if (! $this->option('dry-run')) {
                Storage::disk(config('trainer.import.disk'))->put($pdfPath, file_get_contents($source));
            }
        }

        $check = $validator->validate($payload);
        if (! $check['valid']) {
            $this->error('Payload failed schema validation:');
            foreach (array_slice($check['errors'], 0, 20) as $e) {
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
            $byCategory = [];
            foreach ($payload['words'] as $w) {
                $byCategory[$w['category']] = ($byCategory[$w['category']] ?? 0) + 1;
            }
            $this->table(['Category', 'Words'], array_map(fn ($c, $n) => [$c, $n], array_keys($byCategory), $byCategory));

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
