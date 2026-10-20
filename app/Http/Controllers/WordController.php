<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Conjugation;
use App\Models\Lektion;
use App\Models\QuizAnswer;
use App\Models\UserWordProgress;
use App\Models\Word;
use App\Services\Import\PluralExpander;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WordController extends Controller
{
    public function show(Request $request, Word $word): Response
    {
        $word->load(['lektion', 'category', 'conjugation']);

        $history = QuizAnswer::with('session')
            ->where('word_id', $word->id)
            ->whereHas('session', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest('answered_at')->limit(100)->get()
            ->map(fn (QuizAnswer $a) => [
                'id' => $a->id,
                'question_type' => $a->question_type,
                'prompt' => $a->prompt,
                'correct_answer' => $a->correct_answer,
                'given_answer' => $a->given_answer,
                'is_correct' => $a->is_correct,
                'answered_at' => $a->answered_at->toIso8601String(),
                'session_id' => $a->quiz_session_id,
                'mode' => $a->session?->mode,
            ]);

        $progress = UserWordProgress::firstWhere(['user_id' => $request->user()->id, 'word_id' => $word->id]);

        return Inertia::render('Words/Show', [
            'word' => $word,
            'progress' => $progress,
            'history' => $history,
        ]);
    }

    public function store(Request $request, Lektion $lektion): RedirectResponse
    {
        $data = $this->validated($request);

        $category = Category::firstOrCreate(
            ['lektion_id' => $lektion->id, 'name' => $data['category']],
            ['sort' => Category::sortFor($data['category'])]
        );

        $word = $lektion->words()->create($this->attributes($data) + ['category_id' => $category->id]);
        $this->syncConjugation($word, $data);

        return back()->with('success', __('messages.word_added'));
    }

    public function update(Request $request, Word $word): RedirectResponse
    {
        $data = $this->validated($request);

        $category = Category::firstOrCreate(
            ['lektion_id' => $word->lektion_id, 'name' => $data['category']],
            ['sort' => Category::sortFor($data['category'])]
        );

        $word->update($this->attributes($data) + ['category_id' => $category->id]);
        $this->syncConjugation($word, $data);

        return back()->with('success', __('messages.word_saved'));
    }

    public function destroy(Word $word): RedirectResponse
    {
        $word->delete();

        return back()->with('success', __('messages.word_deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'german' => ['required', 'string', 'max:255'],
            'arabic' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Word::TYPES)],
            'category' => ['required', 'string', 'max:100'],
            'article' => ['nullable', 'string', 'max:16'],
            'plural' => ['nullable', 'string', 'max:255'],
            'plural_suffix' => ['nullable', 'string', 'max:32'],
            'example_de' => ['nullable', 'string', 'max:255'],
            'example_ar' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
            'meta' => ['nullable', 'array'],
            'conjugation' => ['nullable', 'array'],
            'conjugation.ich' => ['nullable', 'string', 'max:64'],
            'conjugation.du' => ['nullable', 'string', 'max:64'],
            'conjugation.er_sie_es' => ['nullable', 'string', 'max:64'],
            'conjugation.wir' => ['nullable', 'string', 'max:64'],
            'conjugation.ihr' => ['nullable', 'string', 'max:64'],
            'conjugation.sie_Sie' => ['nullable', 'string', 'max:64'],
        ]);
    }

    private function attributes(array $data): array
    {
        $plural = $data['plural'] ?? null;
        if (! $plural && ! empty($data['plural_suffix']) && $data['type'] === 'noun') {
            $plural = PluralExpander::expand($data['german'], $data['plural_suffix']);
        }

        return [
            'german' => trim($data['german']),
            'arabic' => trim($data['arabic']),
            'type' => $data['type'],
            'article' => $data['article'] ?: null,
            'plural' => $plural ?: null,
            'plural_suffix' => $data['plural_suffix'] ?? null,
            'example_de' => $data['example_de'] ?? null,
            'example_ar' => $data['example_ar'] ?? null,
            'note' => $data['note'] ?? null,
            'meta' => $data['meta'] ?? null,
        ];
    }

    private function syncConjugation(Word $word, array $data): void
    {
        $c = $data['conjugation'] ?? null;
        if (! $c || $word->type !== 'verb') {
            return;
        }
        $forms = collect($c)->only(Conjugation::PERSONS)->filter(fn ($v) => filled($v));
        if ($forms->count() === count(Conjugation::PERSONS)) {
            Conjugation::updateOrCreate(['word_id' => $word->id], $forms->all());
        }
    }
}
