<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Lektion;
use App\Models\UserWordProgress;
use App\Models\Word;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LektionController extends Controller
{
    public function index(Request $request, StatsService $stats): Response
    {
        return Inertia::render('Lektionen/Index', [
            'lektionen' => $stats->perLektion($request->user()),
        ]);
    }

    public function show(Request $request, Lektion $lektion): Response
    {
        $lektion->load([
            'categories' => fn ($q) => $q->orderBy('sort'),
            'words' => fn ($q) => $q->with('conjugation')->orderBy('id'),
            'dialogLines',
            'grammarNotes',
        ]);

        $progress = UserWordProgress::where('user_id', $request->user()->id)
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
            'example_de' => $w->example_de,
            'example_ar' => $w->example_ar,
            'note' => $w->note,
            'meta' => $w->meta,
            'display' => $w->display,
            'conjugation' => $w->conjugation?->only(['ich', 'du', 'er_sie_es', 'wir', 'ihr', 'sie_Sie']),
            'level' => $progress->get($w->id)?->level ?? 0,
        ]);

        $best = $request->user()->quizSessions()
            ->where('mode', 'lektion')->where('lektion_id', $lektion->id)->whereNotNull('finished_at')->where('total', '>', 0)
            ->get()->max(fn ($s) => $s->scorePercent());

        return Inertia::render('Lektionen/Show', [
            'lektion' => $lektion->only(['id', 'number', 'title', 'imported_at']),
            'categories' => $lektion->categories->map->only(['id', 'name', 'sort']),
            'canonicalCategories' => Category::CANONICAL,
            'types' => Word::TYPES,
            'words' => $words,
            'dialogLines' => $lektion->dialogLines,
            'grammarNotes' => $lektion->grammarNotes,
            'bestScore' => $best,
            'completed' => $best !== null && $best >= 80,
        ]);
    }
}
