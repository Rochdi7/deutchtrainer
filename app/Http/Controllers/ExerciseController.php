<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseSession;
use App\Models\Lektion;
use App\Services\Course\CourseProgressService;
use App\Services\Course\ExerciseEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExerciseController extends Controller
{
    public function __construct(
        private readonly ExerciseEngine $engine,
        private readonly CourseProgressService $progress,
    ) {}

    /** Start a session: set | vocab | test | practice | revision | final. */
    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:set,vocab,test,practice,revision,final'],
            'lektion_id' => ['nullable', 'integer', 'exists:lektionen,id'],
            'section' => ['nullable', 'string', 'max:32'],
            'mode' => ['nullable', 'string', 'max:32'],
            'count' => ['nullable', 'integer', 'min:5', 'max:40'],
            'lektion_ids' => ['nullable', 'array'],
            'lektion_ids.*' => ['integer'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:'.implode(',', Exercise::TYPES)],
            'sections' => ['nullable', 'array'],
            'sections.*' => ['string', 'in:'.implode(',', Exercise::SECTIONS)],
            'difficult' => ['nullable', 'boolean'],
            'wrong' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $lektion = isset($data['lektion_id']) ? Lektion::findOrFail($data['lektion_id']) : null;

        $session = match ($data['kind']) {
            'set' => $this->engine->lessonSet($user, $lektion ?? abort(422), $data['section'] ?? 'grammar'),
            'vocab' => $this->engine->vocabSession($user, $lektion ?? abort(422), $data['mode'] ?? 'mixed', $data['count'] ?? 12),
            'test' => $this->engine->lessonTest($user, $lektion ?? abort(422)),
            'practice' => $this->engine->practice($user, $data),
            'revision' => $this->engine->revision($user, $data['count'] ?? 20),
            'final' => $this->engine->finalAssessment($user),
        };

        if ($session->total === 0) {
            $session->delete();

            return back()->with('error', __('messages.no_exercises'));
        }

        return redirect()->route('exercises.show', $session);
    }

    public function show(Request $request, ExerciseSession $session): Response
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $answered = $session->attempts()->orderBy('id')->get()->map(fn (ExerciseAttempt $a) => [
            'item_id' => $a->item_id,
            'is_correct' => $a->is_correct,
            'given' => $a->given,
        ]);

        return Inertia::render('Exercises/Play', [
            'session' => [
                'id' => $session->id,
                'mode' => $session->mode,
                'label' => $session->label,
                'section' => $session->section,
                'lektion_id' => $session->lektion_id,
                'lektion_number' => $session->lektion?->number,
                'total' => $session->total,
                'correct' => $session->correct,
                'finished' => $session->finished_at !== null,
            ],
            'items' => $this->engine->publicItems($session),
            'answered' => $answered,
            'passThreshold' => (int) (ExerciseSession::PASS_THRESHOLD * 100),
        ]);
    }

    /** Ajax: check one item. */
    public function answer(Request $request, ExerciseSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_if($session->finished_at !== null, 422, 'Session already finished.');

        $data = $request->validate([
            'item_id' => ['required', 'string'],
            'answer' => ['nullable'],
        ]);
        $item = collect($session->items)->firstWhere('id', $data['item_id']);
        abort_if(! $item, 404, 'Unknown item.');
        abort_if($session->attempts()->where('item_id', $item['id'])->exists(), 422, 'Item already answered.');

        $given = $data['answer'] ?? null;
        $result = $this->engine->check($item, $given);
        $this->engine->record($request->user(), $session, $item, $given, $result['correct']);
        $session->refresh();

        $progress = null;
        if ($item['word_id']) {
            $p = $request->user()->wordProgress()->where('word_id', $item['word_id'])->first();
            $progress = $p ? ['level' => $p->level, 'next_review_at' => $p->next_review_at?->toDateString()] : null;
        }

        return response()->json($result + [
            'progress' => $progress,
            'session' => ['correct' => $session->correct, 'total' => $session->total],
        ]);
    }

    public function finish(Request $request, ExerciseSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        if ($session->finished_at === null) {
            $session->update(['finished_at' => now()]);
            $this->progress->recordSession($request->user(), $session);
        }

        $wrong = $session->attempts()->where('is_correct', false)->get()
            ->map(function (ExerciseAttempt $a) use ($session) {
                $item = collect($session->items)->firstWhere('id', $a->item_id);

                return $item ? [
                    'item_id' => $a->item_id,
                    'prompt' => $item['prompt'],
                    'type' => $item['type'],
                    'answer' => $item['solution']['answer'] ?? ($item['solution']['model_answer'] ?? null),
                    'given' => $a->given,
                    'word_id' => $item['word_id'],
                    'exercise_id' => $item['exercise_id'],
                ] : null;
            })->filter()->values();

        return response()->json([
            'score' => $session->scorePercent(),
            'correct' => $session->correct,
            'total' => $session->total,
            'passed' => $session->isPassed(),
            'mode' => $session->mode,
            'wrong' => $wrong,
            'lektion_id' => $session->lektion_id,
        ]);
    }

    /** Retry the items answered wrong in this session. */
    public function retryWrong(Request $request, ExerciseSession $session): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        $wrongIds = $session->attempts()->where('is_correct', false)->pluck('item_id')->all();
        $items = collect($session->items)->whereIn('id', $wrongIds)->map(function ($item) {
            $item['id'] = (string) Str::uuid();

            return $item;
        })->values()->all();
        if ($items === []) {
            return back()->with('error', __('messages.no_wrong_words'));
        }
        $new = ExerciseSession::create([
            'user_id' => $request->user()->id,
            'lektion_id' => $session->lektion_id,
            'mode' => 'practice',
            'section' => $session->section,
            'label' => ($session->label ?? 'Üben').' – Fehler',
            'items' => $items,
            'total' => count($items),
            'correct' => 0,
            'started_at' => now(),
        ]);

        return redirect()->route('exercises.show', $new);
    }
}
