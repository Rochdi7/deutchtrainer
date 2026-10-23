<?php

namespace Tests\Feature;

use App\Services\Course\ContentLoader;
use Tests\TestCase;

/**
 * Validates the A1 course content files under resources/content/a1 without touching the database.
 */
class A1ContentTest extends TestCase
{
    public function test_all_eight_lessons_exist_and_validate(): void
    {
        $loader = app(ContentLoader::class);
        $lessons = $loader->lessons('A1');

        $this->assertCount(8, $lessons, 'DaF kompakt neu A1 has 8 Lektionen');

        $errors = [];
        foreach ($lessons as $lesson) {
            $errors = array_merge($errors, $loader->validate($lesson));
        }
        $this->assertSame([], $errors, "Content validation errors:\n".implode("\n", $errors));

        $numbers = array_map(fn ($l) => $l['meta']['number'], $lessons);
        $this->assertSame(range(1, 8), array_values($numbers));
        foreach ($lessons as $lesson) {
            $this->assertSame('A1', $lesson['meta']['level']);
        }
    }

    public function test_each_lesson_has_enough_content(): void
    {
        $loader = app(ContentLoader::class);

        foreach ($loader->lessons('A1') as $lesson) {
            $n = $lesson['meta']['number'];
            $words = count($lesson['words']) + count($lesson['extras']);
            $exercises = count($lesson['exercises'] ?? []);
            $readings = count($lesson['readings'] ?? []);

            $this->assertGreaterThanOrEqual(30, $words, "Lektion $n: expected at least 30 vocabulary entries, got $words");
            $this->assertGreaterThanOrEqual(20, $exercises, "Lektion $n: expected at least 20 exercises, got $exercises");
            $this->assertLessThanOrEqual(60, $exercises, "Lektion $n: too many exercises ($exercises)");
            $this->assertGreaterThanOrEqual(2, $readings, "Lektion $n: expected at least 2 reading texts");
            $this->assertNotEmpty($lesson['meta']['objectives'], "Lektion $n: objectives missing");
            $this->assertNotEmpty($lesson['grammar'] ?? [], "Lektion $n: grammar notes missing");
            $this->assertNotEmpty($lesson['dialog'] ?? [], "Lektion $n: dialog lines missing");
            $this->assertNotEmpty($lesson['meta']['revision_md'] ?? '', "Lektion $n: revision summary missing");
            $this->assertNotEmpty($lesson['meta']['revision_ar_md'] ?? '', "Lektion $n: Arabic revision summary missing");
            foreach ($lesson['meta']['objectives'] as $i => $o) {
                $this->assertNotEmpty($o['ar'] ?? '', "Lektion $n: objective #$i has no Arabic");
            }
            foreach ($lesson['readings'] as $r) {
                $this->assertNotEmpty($r['translation_ar_md'] ?? '', "Lektion $n: reading {$r['key']} has no Arabic translation");
            }
            foreach ($lesson['grammar'] as $g) {
                $this->assertNotEmpty($g['body_ar_md'] ?? '', "Lektion $n: grammar note {$g['key']} has no Arabic explanation");
            }

            $types = collect($lesson['exercises'])->pluck('type')->unique();
            $this->assertGreaterThanOrEqual(6, $types->count(), "Lektion $n: exercises should use at least 6 different types, got ".$types->implode(', '));

            foreach ($lesson['exercises'] as $e) {
                $this->assertArrayHasKey('key', $e, "Lektion $n: exercise without key");
                $this->assertArrayHasKey('section', $e, "Lektion $n: exercise {$e['key']} without section");
            }
        }
    }

    public function test_every_vocabulary_entry_has_german_english_and_arabic(): void
    {
        $loader = app(ContentLoader::class);

        foreach ($loader->lessons('A1') as $lesson) {
            $n = $lesson['meta']['number'];
            foreach ($lesson['words'] as $w) {
                $this->assertNotEmpty($w['german'] ?? '', "Lektion $n: entry without German");
                $this->assertNotEmpty($w['english'] ?? '', "Lektion $n: entry {$w['german']} without English");
                $this->assertNotEmpty($w['arabic'] ?? '', "Lektion $n: entry {$w['german']} without Arabic");
                if ($w['type'] === 'noun' && $w['article'] === null && ! str_contains((string) ($w['note'] ?? ''), 'Pl.')) {
                    $this->assertTrue(($w['meta']['plural_only'] ?? false) || str_starts_with($w['german'], 'die '), "Lektion $n: noun {$w['german']} without article");
                }
            }
        }
    }

    public function test_referenced_vocabulary_images_exist_as_public_files(): void
    {
        $loader = app(ContentLoader::class);
        $missing = [];
        $total = 0;

        foreach ($loader->lessons('A1') as $lesson) {
            foreach ($lesson['words'] as $w) {
                if (empty($w['image'])) {
                    continue;
                }
                $total++;
                if (! is_file(public_path($w['image']))) {
                    $missing[] = $w['image'];
                }
            }
        }

        $this->assertGreaterThan(400, $total, 'expected several hundred illustrated words');
        $this->assertSame([], array_values(array_unique($missing)), 'Missing vocabulary image files: '.implode(', ', array_unique($missing)));
        $this->assertFileExists(public_path('images/vocab/ATTRIBUTION.md'));
    }
}
