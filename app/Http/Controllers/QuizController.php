<?php

namespace App\Http\Controllers;

use App\Models\Lektion;
use App\Models\QuizAnswer;
use App\Models\QuizSession;
use App\Services\Quiz\AnswerChecker;
use App\Services\Quiz\ProgressService;
use App\Services\Quiz\QuizGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function __construct(
        private readonly QuizGenerator $generator,
        private readonly AnswerChecker $checker,
        private readonly ProgressService $progress,
    ) {}

    public function lektion(Request $request, Lektion $lektion): RedirectResponse
    {
        $questions = $this->generator->forLektion($request->user(), $lektion);

        return $this->start($request, 'lektion', $questions, $lektion->id, __('messages.lektion_no_words'));
    }

    public function global(Request $request): RedirectResponse
    {
        return $this->start($request, 'global', $this->generator->global($request->user()), null, __('messages.no_words_imported'));
    }

    public function weak(Request $request): RedirectResponse
    {
        $questions = $this->generator->weak($request->user());

        return $this->start($request, 'weak', $questions, null, __('messages.no_weak_words'));
    }

    /** Custom word list: "Retry wrong words" and "Practice these". */
    public function words(Request $request): RedirectResponse
    {
        $data = $request->validate(['word_ids' => ['required', 'array', 'min:1'], 'word_ids.*' => ['integer']]);
        $questions = $this->generator->forWords($request->user(), $data['word_ids']);

        return $this->start($request, 'weak', $questions, null, __('messages.no_words_found'));
    }

    public function show(Request $request, QuizSession $session): Response
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $answered = $session->answers()->orderBy('id')->get();

        return Inertia::render('Quiz/Play', [
            'session' => [
                'id' => $session->id,
                'mode' => $session->mode,
                'lektion_id' => $session->lektion_id,
                'lektion_number' => $session->lektion?->number,
                'total' => $session->total,
                'correct' => $session->correct,
                'finished' => $session->finished_at !== null,
            ],
            'questions' => array_map(fn ($q) => collect($q)->except(['answer', 'accept'])->all(), $session->questions),
            'answered' => $answered->map(fn (QuizAnswer $a) => [
                'question_type' => $a->question_type,
                'prompt' => $a->prompt,
                'correct_answer' => $a->correct_answer,
                'given_answer' => $a->given_answer,
                'is_correct' => $a->is_correct,
                'word_id' => $a->word_id,
            ]),
            'passThreshold' => (int) (QuizSession::PASS_THRESHOLD * 100),
        ]);
    }

    /** Ajax: check one answer, store it, update progress. */
    public function answer(Request $request, QuizSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);
        abort_if($session->finished_at !== null, 422, 'Session already finished.');

        $data = $request->validate([
            'question_id' => ['required', 'string'],
            'answer' => ['nullable', 'string', 'max:255'],
        ]);

        $question = collect($session->questions)->firstWhere('id', $data['question_id']);
        abort_if(! $question, 404, 'Unknown question.');

        $alreadyAnswered = $session->answers()->where('prompt', $question['prompt'])
            ->where('question_type', $question['type'])->where('word_id', $question['word_id'])->exists();
        abort_if($alreadyAnswered, 422, 'Question already answered.');

        $given = $data['answer'] ?? null;
        $correct = $question['type'] === 'typing'
            ? collect($question['accept'] ?? [$question['answer']])->contains(fn ($a) => $this->checker->typingMatches($given, $a))
            : $this->checker->optionMatches($given, $question['answer']);

        $session->answers()->create([
            'word_id' => $question['word_id'],
            'question_type' => $question['type'],
            'prompt' => $question['prompt'],
            'correct_answer' => $question['answer'],
            'given_answer' => $given,
            'is_correct' => $correct,
            'answered_at' => now(),
        ]);

        if ($correct) {
            $session->increment('correct');
        }

        $progress = null;
        if ($question['word_id']) {
            $progress = $this->progress->record($request->user(), $question['word_id'], $correct);
        }

        return response()->json([
            'correct' => $correct,
            'answer' => $question['answer'],
            'word' => $question['word'],
            'progress' => $progress ? ['level' => $progress->level, 'next_review_at' => $progress->next_review_at?->toDateString()] : null,
            'session' => ['correct' => $session->correct, 'total' => $session->total],
        ]);
    }

    public function finish(Request $request, QuizSession $session): JsonResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        if ($session->finished_at === null) {
            $session->update(['finished_at' => now()]);
        }

        $wrong = $session->answers()->with('word')->where('is_correct', false)->get()
            ->map(fn (QuizAnswer $a) => [
                'word_id' => $a->word_id,
                'display' => $a->word?->display ?? $a->correct_answer,
                'arabic' => $a->word?->arabic,
                'prompt' => $a->prompt,
                'correct_answer' => $a->correct_answer,
                'given_answer' => $a->given_answer,
                'question_type' => $a->question_type,
            ])->unique(fn ($w) => $w['word_id'].'|'.$w['correct_answer'])->values();

        return response()->json([
            'score' => $session->scorePercent(),
            'correct' => $session->correct,
            'total' => $session->total,
            'passed' => $session->isPassed(),
            'wrong' => $wrong,
        ]);
    }

    public function retryWrong(Request $request, QuizSession $session): RedirectResponse
    {
        abort_unless($session->user_id === $request->user()->id, 403);

        $ids = $session->answers()->where('is_correct', false)->whereNotNull('word_id')->pluck('word_id')->unique();
        $questions = $this->generator->forWords($request->user(), $ids);

        return $this->start($request, 'weak', $questions, null, __('messages.no_wrong_words'));
    }

    private function start(Request $request, string $mode, array $questions, ?int $lektionId, string $emptyMessage): RedirectResponse
    {
        if (count($questions) === 0) {
            return back()->with('error', $emptyMessage);
        }

        $session = QuizSession::create([
            'user_id' => $request->user()->id,
            'mode' => $mode,
            'lektion_id' => $lektionId,
            'total' => count($questions),
            'correct' => 0,
            'questions' => $questions,
            'started_at' => now(),
        ]);

        return redirect()->route('quiz.show', $session);
    }
}
