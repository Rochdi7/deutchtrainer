<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Conjugation;
use App\Models\DialogLine;
use App\Models\Exercise;
use App\Models\GrammarNote;
use App\Models\Lektion;
use App\Models\Reading;
use App\Models\Word;
use App\Services\Course\ContentLoader;
use App\Services\Course\VocabEntryFactory;
use App\Services\Course\VocabImageService;
use App\Services\Import\PluralExpander;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds the A1 course from resources/content/a1. Safe to rerun: everything is matched by a
 * stable key (level + number, lektion + german + type, exercise key, …) and updated in place.
 * User data (progress, sessions, attempts) is never touched.
 */
class A1CourseSeeder extends Seeder
{
    public function run(): void
    {
        $loader = app(ContentLoader::class);
        $factory = app(VocabEntryFactory::class);
        $images = app(VocabImageService::class);

        $lessons = $loader->lessons('A1');
        $errors = [];
        foreach ($lessons as $lesson) {
            $errors = array_merge($errors, $loader->validate($lesson));
        }
        if ($errors !== []) {
            throw new RuntimeException("Course content invalid:\n- ".implode("\n- ", array_slice($errors, 0, 30)));
        }

        foreach ($lessons as $lesson) {
            DB::transaction(fn () => $this->seedLesson($lesson, $factory));
        }

        $imageReport = $images->sync();
        $this->command?->info(sprintf('Images: %d present, %d copied, %d missing', $imageReport['present'], $imageReport['copied'], count($imageReport['missing'])));
        foreach ($imageReport['missing'] as $m) {
            $this->command?->warn('  missing image: '.$m);
        }
    }

    private function seedLesson(array $lesson, VocabEntryFactory $factory): void
    {
        $meta = $lesson['meta'];
        $lektion = Lektion::updateOrCreate(
            ['level' => $meta['level'], 'number' => (int) $meta['number']],
            [
                'slug' => $meta['slug'],
                'title' => $meta['title'],
                'subtitle' => $meta['subtitle'] ?? null,
                'objectives' => $meta['objectives'],
                'source_pages' => $meta['source_pages'] ?? null,
                'revision_md' => $meta['revision_md'] ?? null,
                'revision_ar_md' => $meta['revision_ar_md'] ?? null,
                'sort' => (int) ($meta['sort'] ?? $meta['number']),
            ]
        );

        $counts = ['words_new' => 0, 'words_updated' => 0, 'conj' => 0, 'dialog' => 0, 'notes' => 0, 'readings' => 0, 'exercises' => 0];

        // ---- words
        foreach ($lesson['words'] as $i => $w) {
            $category = Category::firstOrCreate(
                ['lektion_id' => $lektion->id, 'name' => $w['category']],
                ['sort' => Category::sortFor($w['category'])]
            );
            $existing = $this->findWord($lektion, $w['german'], $w['type']);
            $plural = $w['plural'] ?? null;
            if ($plural === null && ! empty($w['plural_suffix']) && $w['type'] === 'noun') {
                $plural = PluralExpander::expand($w['german'], $w['plural_suffix']);
            }
            $attrs = [
                'category_id' => $existing?->category_id ?? $category->id,
                'type' => $w['type'],
                'article' => $w['article'] ?? $existing?->article,
                'plural' => $plural ?? $existing?->plural,
                'plural_suffix' => $w['plural_suffix'] ?? $existing?->plural_suffix,
                'arabic' => $w['arabic'],
                'english' => $w['english'],
                'example_de' => $w['example_de'] ?? $existing?->example_de,
                'example_en' => $w['example_en'] ?? $existing?->example_en,
                'example_ar' => $w['example_ar'] ?? $existing?->example_ar,
                'note' => $w['note'] ?? $existing?->note,
                'meta' => array_merge($existing?->meta ?? [], $w['meta'] ?? []) ?: null,
                'image' => $w['image'] ?? $existing?->image,
                'source_page' => $w['source_page'] ?? $existing?->source_page,
                'tags' => $w['tags'] ?? $existing?->tags,
            ];
            if ($existing) {
                $existing->update($attrs);
                $counts['words_updated']++;
            } else {
                $created = Word::create(['lektion_id' => $lektion->id, 'german' => $w['german']] + $attrs);
                if (isset($this->wordCache[$lektion->id])) {
                    $this->wordCache[$lektion->id]->push($created);
                }
                $counts['words_new']++;
            }
        }

        // ---- extras for already seeded words (Lektion 1)
        foreach ($lesson['extras'] as $german => $extra) {
            $words = $this->wordsNamed($lektion, $german);
            foreach ($words as $word) {
                $word->update(array_filter([
                    'english' => $extra['en'] ?? null,
                    'image' => isset($extra['img']) ? $factory->imagePath($extra['img']) : null,
                    'example_de' => $extra['ex'][0] ?? null,
                    'example_en' => $extra['ex'][1] ?? null,
                    'source_page' => 14,
                ], fn ($v) => $v !== null));
                $counts['words_updated']++;
            }
        }

        // ---- conjugations
        foreach ($lesson['conjugations'] as $c) {
            $word = $this->findVerb($lektion, $c['german'], $factory);
            if (! $word) {
                $this->command?->warn("  Lektion {$meta['number']}: no verb word for conjugation {$c['german']}");

                continue;
            }
            Conjugation::updateOrCreate(['word_id' => $word->id], collect($c)->only(Conjugation::PERSONS)->all());
            $counts['conj']++;
        }

        // ---- dialog lines
        foreach ($lesson['dialog'] as $d) {
            DialogLine::updateOrCreate(
                ['lektion_id' => $lektion->id, 'topic' => $d['topic'], 'answer' => $d['answer']],
                [
                    'question_formal' => $d['question_formal'] ?? null,
                    'question_informal' => $d['question_informal'] ?? null,
                    'arabic' => $d['arabic'],
                    'english' => $d['english'] ?? null,
                    'sort' => $d['sort'] ?? 0,
                    'source_page' => $d['page'] ?? null,
                ]
            );
            $counts['dialog']++;
        }
        foreach ($lesson['dialog_english'] as $topic => $english) {
            DialogLine::where('lektion_id', $lektion->id)->where('topic', $topic)->whereNull('english')->update(['english' => $english]);
        }

        // ---- grammar notes
        foreach ($lesson['grammar'] as $i => $n) {
            $note = GrammarNote::where('key', $n['key'])->first()
                ?? GrammarNote::where('lektion_id', $lektion->id)->where('title', $n['title'])->first();
            $attrs = ['lektion_id' => $lektion->id, 'key' => $n['key'], 'title' => $n['title'], 'body_md' => $n['body_md'], 'body_ar_md' => $n['body_ar_md'] ?? null, 'source_page' => $n['page'] ?? null, 'sort' => 100 + $i];
            $note ? $note->update($attrs) : GrammarNote::create($attrs);
            $counts['notes']++;
        }

        // ---- readings
        foreach ($lesson['readings'] as $i => $r) {
            Reading::updateOrCreate(['key' => $r['key']], [
                'lektion_id' => $lektion->id,
                'title' => $r['title'],
                'translation_ar_md' => $r['translation_ar_md'] ?? null,
                'text_md' => $r['text_md'],
                'translation_md' => $r['translation_md'] ?? null,
                'questions' => $r['questions'] ?? [],
                'source_page' => $r['page'] ?? null,
                'sort' => $i,
            ]);
            $counts['readings']++;
        }

        // ---- exercises
        foreach ($lesson['exercises'] as $i => $e) {
            Exercise::updateOrCreate(['key' => $e['key']], [
                'lektion_id' => $lektion->id,
                'section' => $e['section'],
                'type' => $e['type'],
                'prompt' => $e['prompt'],
                'data' => $e['data'],
                'difficulty' => (int) ($e['difficulty'] ?? 1),
                'sort' => $i,
                'source_page' => $e['page'] ?? null,
            ]);
            $counts['exercises']++;
        }

        $lektion->update(['imported_at' => $lektion->imported_at ?? now()]);

        $this->command?->info(sprintf(
            'Lektion %d „%s“: %d new words, %d updated, %d conjugations, %d dialog lines, %d notes, %d readings, %d exercises',
            $meta['number'], $meta['title'], $counts['words_new'], $counts['words_updated'], $counts['conj'], $counts['dialog'], $counts['notes'], $counts['readings'], $counts['exercises']
        ));
    }

    /** @var array<int, Collection<int, Word>> words per lektion, loaded once per run */
    private array $wordCache = [];

    /**
     * Case-insensitive lookup done in PHP: SQLite's LOWER() only folds ASCII, so "Öl"/"Übung"
     * would otherwise be re-created on every run.
     */
    private function wordsNamed(Lektion $lektion, string $german): Collection
    {
        $this->wordCache[$lektion->id] ??= Word::where('lektion_id', $lektion->id)->get()->toBase();
        $needle = mb_strtolower($german);

        return $this->wordCache[$lektion->id]->filter(fn (Word $w) => mb_strtolower($w->german) === $needle)->values();
    }

    private function findWord(Lektion $lektion, string $german, string $type): ?Word
    {
        $candidates = $this->wordsNamed($lektion, $german);

        return $candidates->firstWhere('type', $type) ?? ($candidates->count() === 1 ? $candidates->first() : null);
    }

    private function findVerb(Lektion $lektion, string $infinitive, VocabEntryFactory $factory): ?Word
    {
        $base = mb_strtolower($factory->verbBase($infinitive) ?: $infinitive);
        $match = fn ($q) => $q->where('type', 'verb')->get()->first(fn (Word $w) => mb_strtolower($factory->verbBase($w->german) ?: $w->german) === $base || mb_strtolower($w->german) === mb_strtolower($infinitive));

        return $match(Word::where('lektion_id', $lektion->id)) ?? $match(Word::query());
    }
}
