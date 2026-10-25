<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Lektion;
use App\Models\UserWordProgress;
use App\Models\Word;
use App\Services\Course\CourseProgressService;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LektionController extends Controller
{
    public function index(Request $request, StatsService $stats, CourseProgressService $progress): Response
    {
        $user = $request->user();
        $completion = Lektion::level('A1')->get()->mapWithKeys(fn (Lektion $l) => [$l->id => $progress->lessonCompletion($user, $l)]);

        return Inertia::render('Lektionen/Index', [
            'lektionen' => $stats->perLektion($user)->map(fn ($l) => $l + ['completion' => $completion[$l['id']] ?? 0]),
        ]);
    }

    public function show(Request $request, Lektion $lektion, CourseProgressService $progress): Response
    {
        $user = $request->user();
        $lektion->load([
            'categories' => fn ($q) => $q->orderBy('sort'),
            'words' => fn ($q) => $q->with('conjugation')->orderBy('id'),
            'dialogLines',
            'grammarNotes',
            'readings',
        ]);

        $wordProgress = UserWordProgress::where('user_id', $user->id)
            ->whereIn('word_id', $lektion->words->pluck('id'))
            ->get()->keyBy('word_id');

        $words = $lektion->words->map(fn (Word $w) => [
            'id' => $w->id,
            'category_id' => $w->category_id,
            'type' => $w->type,
            'german' => $w->german,
            'article' => $w->article,
            'plural' => $w->plural,
            'plural_suffix' => $w->plural_suffix,
            'arabic' => $w->arabic,
            'english' => $w->english,
            'example_de' => $w->example_de,
            'example_en' => $w->example_en,
            'example_ar' => $w->example_ar,
            'note' => $w->note,
            'meta' => $w->meta,
            'image' => $w->image,
            'source_page' => $w->source_page,
            'display' => $w->display,
            'conjugation' => $w->conjugation?->only(['ich', 'du', 'er_sie_es', 'wir', 'ihr', 'sie_Sie']),
            'level' => $wordProgress->get($w->id)?->level ?? 0,
            'is_difficult' => (bool) ($wordProgress->get($w->id)?->is_difficult ?? false),
        ]);

        $best = $user->quizSessions()
            ->where('mode', 'lektion')->where('lektion_id', $lektion->id)->whereNotNull('finished_at')->where('total', '>', 0)
            ->get()->max(fn ($s) => $s->scorePercent());

        $siblings = Lektion::level($lektion->level)->get(['id', 'number', 'title']);
        $index = $siblings->search(fn ($l) => $l->id === $lektion->id);

        return Inertia::render('Lektionen/Show', [
            'lektion' => $lektion->only(['id', 'level', 'number', 'title', 'subtitle', 'objectives', 'source_pages', 'revision_md', 'revision_ar_md', 'imported_at']),
            'prev' => $index !== false && $index > 0 ? $siblings[$index - 1]->only(['id', 'number', 'title']) : null,
            'next' => $index !== false && $index < $siblings->count() - 1 ? $siblings[$index + 1]->only(['id', 'number', 'title']) : null,
            'categories' => $lektion->categories->map->only(['id', 'name', 'sort']),
            'canonicalCategories' => Category::CANONICAL,
            'types' => Word::TYPES,
            'words' => $words,
            'dialogLines' => $lektion->dialogLines,
            'grammarNotes' => $lektion->grammarNotes,
            'readings' => $lektion->readings->map->only(['id', 'key', 'title', 'text_md', 'translation_md', 'translation_ar_md', 'questions', 'source_page']),
            'sections' => $progress->sections($lektion),
            'sets' => $progress->exerciseSets($lektion),
            'progress' => $progress->lessonProgress($user, $lektion),
            'bestScore' => $best,
            'completed' => ($progress->lessonProgress($user, $lektion)['test']['completed'] ?? false) || ($best !== null && $best >= 80),
            'vocabModes' => ['image_word', 'word_image', 'en_de', 'de_en', 'typing', 'article', 'plural', 'sentence', 'spelling', 'match', 'speak', 'mixed'],
        ]);
    }
}
