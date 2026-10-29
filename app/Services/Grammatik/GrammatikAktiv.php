<?php

namespace App\Services\Grammatik;

use RuntimeException;

/**
 * Reads the "Grammatik aktiv A1–B1" units (storage/app/grammatik-aktiv/unit-NN.json).
 *
 * The files are transcribed from the copyrighted book, so they live outside git and are
 * uploaded to the server separately; the module is admin-only. A unit holds the rule page
 * (info_md) and its exercises with the answer key:
 *   gap   – items with "___" gaps, answers[] per gap ("a|b" = alternatives)
 *   match – items with answer letters, choices[{key, text}] shown once per exercise
 *   write – free answers, items with the book's solution (self-check)
 */
class GrammatikAktiv
{
    public const LEVELS = ['A1', 'A2', 'B1'];

    public const TYPES = ['gap', 'match', 'write'];

    public const GAP = '___';

    /** @var array<int, array<string, mixed>>|null */
    private ?array $units = null;

    public function __construct(private readonly ?string $path = null) {}

    public function basePath(): string
    {
        return $this->path ?? storage_path('app/grammatik-aktiv');
    }

    /** @return array<int, array<string, mixed>> units keyed by number, in book order */
    public function units(): array
    {
        if ($this->units === null) {
            $this->units = [];
            foreach (glob($this->basePath().'/unit-*.json') ?: [] as $file) {
                $unit = $this->read($file);
                $this->units[(int) $unit['number']] = $unit;
            }
            ksort($this->units);
        }

        return $this->units;
    }

    /** @return array<string, mixed>|null */
    public function unit(int $number): ?array
    {
        return $this->units()[$number] ?? null;
    }

    /** @return array<string, mixed> */
    public function read(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            throw new RuntimeException(basename($file).': invalid JSON ('.json_last_error_msg().')');
        }

        return $data;
    }

    /** @return string[] structural problems of one unit */
    public function validate(array $unit): array
    {
        $n = $unit['number'] ?? '?';
        $errors = [];
        $err = function (string $msg) use (&$errors, $n) {
            $errors[] = "Einheit $n: $msg";
        };

        if (! is_int($unit['number'] ?? null)) {
            $err('number missing');
        }
        if (! in_array($unit['level'] ?? null, self::LEVELS, true)) {
            $err('level must be A1, A2 or B1');
        }
        foreach (['chapter', 'title', 'topic', 'info_md'] as $field) {
            if (! is_string($unit[$field] ?? null) || trim($unit[$field]) === '') {
                $err("$field missing");
            }
        }
        if (empty($unit['exercises']) || ! is_array($unit['exercises'])) {
            $err('no exercises');

            return $errors;
        }

        $image = function (?string $name, string $at) use ($err) {
            if ($name === null) {
                return;
            }
            if (! preg_match('/^[a-z0-9-]+\.(png|jpg|webp)$/', $name)) {
                $err("$at: invalid image name '$name'");
            } elseif (! is_file($this->basePath().'/images/'.$name)) {
                $err("$at: image '$name' not found");
            }
        };
        $image($unit['info_image'] ?? null, 'info_image');

        $keys = [];
        foreach ($unit['exercises'] as $i => $ex) {
            $key = $ex['key'] ?? null;
            $at = 'Übung '.($key ?? '#'.($i + 1));
            if (! is_string($key) || $key === '') {
                $err("$at: key missing");
            } elseif (isset($keys[$key])) {
                $err("$at: duplicate key");
            }
            $keys[$key] = true;
            if (isset($ex['level']) && ! in_array($ex['level'], self::LEVELS, true)) {
                $err("$at: invalid level");
            }
            if (! is_string($ex['instruction'] ?? null) || trim($ex['instruction']) === '') {
                $err("$at: instruction missing");
            }
            $type = $ex['type'] ?? null;
            if (! in_array($type, self::TYPES, true)) {
                $err("$at: type must be one of ".implode(', ', self::TYPES));

                continue;
            }
            if (empty($ex['items']) || ! is_array($ex['items'])) {
                $err("$at: no items");

                continue;
            }
            $choiceKeys = array_column($ex['choices'] ?? [], 'key');
            if ($type === 'match' && ! $choiceKeys) {
                $err("$at: match needs choices[{key, text}]");
            }
            $image($ex['image'] ?? null, $at);

            foreach ($ex['items'] as $j => $item) {
                $it = "$at, Item ".($j + 1);
                if (! is_string($item['text'] ?? null)) {
                    $err("$it: text missing");

                    continue;
                }
                $image($item['image'] ?? null, $it);
                if ($type === 'gap') {
                    $gaps = substr_count($item['text'], self::GAP);
                    $answers = $item['answers'] ?? null;
                    if (! is_array($answers) || ! $answers) {
                        $err("$it: answers missing");
                    } elseif ($gaps !== count($answers)) {
                        $err("$it: $gaps gaps but ".count($answers).' answers');
                    } elseif (array_filter($answers, fn ($a) => ! is_string($a) || trim($a) === '')) {
                        $err("$it: empty answer");
                    }
                } elseif ($type === 'match') {
                    foreach (explode('|', (string) ($item['answer'] ?? '')) as $a) {
                        if (! in_array($a, $choiceKeys, true)) {
                            $err("$it: answer '$a' is not one of the choices");
                        }
                    }
                } elseif (! is_string($item['solution'] ?? null) || trim($item['solution']) === '') {
                    $err("$it: solution missing");
                }
            }
        }

        return $errors;
    }

    /** @return string[] missing translations / pictures (not fatal) */
    public function warnings(array $unit): array
    {
        $n = $unit['number'] ?? '?';
        $out = [];
        foreach (['topic_ar', 'info_ar'] as $field) {
            if (trim((string) ($unit[$field] ?? '')) === '') {
                $out[] = "Einheit $n: $field missing";
            }
        }
        foreach ($unit['exercises'] ?? [] as $ex) {
            $at = "Einheit $n, Übung ".($ex['key'] ?? '?');
            if (trim((string) ($ex['instruction_ar'] ?? '')) === '') {
                $out[] = "$at: instruction_ar missing";
            }
            $pictures = 0;
            $untranslated = 0;
            foreach ($ex['items'] ?? [] as $item) {
                if (preg_match('/^Bild/u', (string) ($item['hint'] ?? '')) && empty($item['image'])) {
                    $pictures++;
                }
                if (in_array($ex['type'] ?? '', ['gap', 'match'], true) && trim((string) ($item['text_ar'] ?? '')) === '') {
                    $untranslated++;
                }
            }
            if ($pictures && empty($ex['image'])) {
                $out[] = "$at: $pictures picture item(s) without image";
            }
            if ($untranslated) {
                $out[] = "$at: $untranslated item(s) without text_ar";
            }
        }

        return $out;
    }
}
