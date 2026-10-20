<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\Conjugation;
use App\Models\DialogLine;
use App\Models\GrammarNote;
use App\Models\Lektion;
use App\Models\Word;
use Illuminate\Support\Facades\DB;

/**
 * Writes a validated import payload into the database.
 * Words already present in the same Lektion (same german, case-insensitive) are skipped.
 */
class ImportPersister
{
    /**
     * @return array{lektion: Lektion, words: int, skipped: int, conjugations: int, dialog_lines: int, grammar_notes: int}
     */
    public function persist(array $payload, int $lektionNumber, ?string $title = null, ?string $pdfPath = null): array
    {
        return DB::transaction(function () use ($payload, $lektionNumber, $title, $pdfPath) {
            $lektion = Lektion::firstOrCreate(
                ['number' => $lektionNumber],
                ['title' => $title ?: "Lektion {$lektionNumber}"]
            );
            $lektion->fill([
                'title' => $title ?: $lektion->title,
                'source_pdf_path' => $pdfPath ?: $lektion->source_pdf_path,
                'imported_at' => now(),
            ])->save();

            $existing = $lektion->words()->pluck('id', 'german')
                ->mapWithKeys(fn ($id, $german) => [mb_strtolower($german) => $id])
                ->all();

            $wordsByGerman = [];
            $inserted = 0;
            $skipped = 0;

            foreach ($payload['words'] ?? [] as $row) {
                $key = mb_strtolower(trim($row['german']));
                if (isset($existing[$key])) {
                    $skipped++;
                    $wordsByGerman[$key] = $existing[$key];

                    continue;
                }

                $category = Category::firstOrCreate(
                    ['lektion_id' => $lektion->id, 'name' => $row['category']],
                    ['sort' => Category::sortFor($row['category'])]
                );

                $word = Word::create([
                    'lektion_id' => $lektion->id,
                    'category_id' => $category->id,
                    'type' => $row['type'],
                    'german' => trim($row['german']),
                    'article' => $row['article'] ?? null,
                    'plural' => $row['plural'] ?? null,
                    'plural_suffix' => $row['plural_suffix'] ?? null,
                    'arabic' => trim($row['arabic']),
                    'example_de' => $row['example_de'] ?? null,
                    'example_ar' => $row['example_ar'] ?? null,
                    'note' => $row['note'] ?? null,
                    'meta' => $row['meta'] ?? null,
                ]);

                $existing[$key] = $word->id;
                $wordsByGerman[$key] = $word->id;
                $inserted++;
            }

            $conj = 0;
            foreach ($payload['conjugations'] ?? [] as $row) {
                $key = mb_strtolower(trim($row['german']));
                $wordId = $wordsByGerman[$key] ?? $existing[$key] ?? null;
                if (! $wordId) {
                    // Try matching "(sich) vorstellen" style entries.
                    $alt = $lektion->words()->where('type', 'verb')
                        ->whereRaw('LOWER(german) LIKE ?', ['%'.$key.'%'])->value('id');
                    $wordId = $alt;
                }
                if (! $wordId) {
                    continue;
                }
                Conjugation::updateOrCreate(['word_id' => $wordId], collect($row)->only(Conjugation::PERSONS)->all());
                $conj++;
            }

            $dialog = 0;
            $existingDialog = $lektion->dialogLines()->get()->map(fn ($d) => mb_strtolower($d->topic.'|'.$d->answer))->flip();
            $sort = (int) $lektion->dialogLines()->max('sort');
            foreach ($payload['dialog_lines'] ?? [] as $row) {
                $k = mb_strtolower($row['topic'].'|'.$row['answer']);
                if (isset($existingDialog[$k])) {
                    continue;
                }
                DialogLine::create([
                    'lektion_id' => $lektion->id,
                    'topic' => $row['topic'],
                    'question_formal' => $row['question_formal'] ?? null,
                    'question_informal' => $row['question_informal'] ?? null,
                    'answer' => $row['answer'],
                    'arabic' => $row['arabic'],
                    'sort' => ++$sort,
                ]);
                $dialog++;
            }

            $notes = 0;
            $existingNotes = $lektion->grammarNotes()->pluck('id', 'title')->keys()->map(fn ($t) => mb_strtolower($t))->flip();
            $nsort = (int) $lektion->grammarNotes()->max('sort');
            foreach ($payload['grammar_notes'] ?? [] as $row) {
                if (isset($existingNotes[mb_strtolower($row['title'])])) {
                    continue;
                }
                GrammarNote::create([
                    'lektion_id' => $lektion->id,
                    'title' => $row['title'],
                    'body_md' => $row['body_md'],
                    'sort' => ++$nsort,
                ]);
                $notes++;
            }

            return [
                'lektion' => $lektion,
                'words' => $inserted,
                'skipped' => $skipped,
                'conjugations' => $conj,
                'dialog_lines' => $dialog,
                'grammar_notes' => $notes,
            ];
        });
    }
}
