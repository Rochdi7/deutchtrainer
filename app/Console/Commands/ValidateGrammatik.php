<?php

namespace App\Console\Commands;

use App\Services\Grammatik\GrammatikAktiv;
use Illuminate\Console\Command;
use Throwable;

class ValidateGrammatik extends Command
{
    protected $signature = 'grammatik:validate {units?* : unit numbers (default: all files)}';

    protected $description = 'Check the Grammatik-aktiv unit files: structure, gaps vs. answers, and exercise numbers against manifest.json (PDF bookmarks)';

    public function handle(GrammatikAktiv $grammatik): int
    {
        $manifest = [];
        $manifestFile = $grammatik->basePath().'/manifest.json';
        if (is_file($manifestFile)) {
            foreach ($grammatik->read($manifestFile) as $m) {
                $manifest[$m['number']] = $m;
            }
        }

        $wanted = array_map('intval', $this->argument('units'));
        $files = glob($grammatik->basePath().'/unit-*.json') ?: [];
        $errors = $warnings = $exercises = 0;
        $seen = [];

        foreach ($files as $file) {
            try {
                $unit = $grammatik->read($file);
            } catch (Throwable $e) {
                $this->error($e->getMessage());
                $errors++;

                continue;
            }
            $n = $unit['number'] ?? 0;
            if ($wanted && ! in_array($n, $wanted, true)) {
                continue;
            }
            $seen[] = $n;
            if (basename($file) !== sprintf('unit-%02d.json', $n)) {
                $this->error(basename($file).": file name does not match number $n");
                $errors++;
            }
            foreach ($grammatik->validate($unit) as $msg) {
                $this->error($msg);
                $errors++;
            }
            foreach ($grammatik->warnings($unit) as $msg) {
                $this->warn($msg);
                $warnings++;
            }

            $keys = array_column($unit['exercises'] ?? [], 'key');
            $exercises += count($keys);
            if ($m = $manifest[$n] ?? null) {
                if (($unit['level'] ?? null) !== $m['level']) {
                    $this->error("Einheit $n: level {$unit['level']} but the book says {$m['level']}");
                    $errors++;
                }
                $bookmarks = $m['exercises'];
                if ($bookmarks && ($missing = array_diff($bookmarks, $keys))) {
                    $this->warn("Einheit $n: exercises in the PDF bookmarks but not in the file: ".implode(', ', $missing));
                    $warnings++;
                }
                if ($bookmarks && ($extra = array_diff($keys, $bookmarks))) {
                    $this->warn("Einheit $n: exercises not in the PDF bookmarks: ".implode(', ', $extra));
                    $warnings++;
                }
            }
        }

        if (! $wanted && $manifest && ($absent = array_diff(array_keys($manifest), $seen))) {
            $this->warn(count($absent).' units without a file: '.implode(', ', $absent));
        }
        $this->info(sprintf('%d units, %d exercises, %d errors, %d warnings', count($seen), $exercises, $errors, $warnings));

        return $errors ? self::FAILURE : self::SUCCESS;
    }
}
