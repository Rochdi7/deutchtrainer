<?php

namespace App\Services\Course;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\ExerciseSession;
use App\Models\Lektion;
use App\Models\LessonProgress;
use App\Models\QuizAnswer;
use App\Models\QuizSession;
use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use Illuminate\Support\Collection;

/**
 * Lesson sections, completion state and the course dashboard numbers. All figures come from
 * real learner data (lesson_progress, exercise sessions/attempts, word progress, quiz sessions).
 */
class CourseProgressService
{
    /** Sections in learner order with the sets that belong to them. */
    public function sections(Lektion $lektion): array
    {
        $exerciseSections = $lektion->exercises()->select('section')->distinct()->pluck('section')->all();
        $has = fn (string $s) => in_array($s, $exerciseSections, true);

        $sections = [
            ['key' => 'objectives', 'label' => 'Lernziele', 'always' => true],
            ['key' => 'vocab', 'label' => 'Wortschatz', 'always' => $lektion->words()->exists()],
            ['key' => 'grammar', 'label' => 'Grammatik', 'always' => $lektion->grammarNotes()->exists()],
            ['key' => 'dialog', 'label' => 'Sätze und Dialoge', 'always' => $lektion->dialogLines()->exists()],
            ['key' => 'reading', 'label' => 'Lesen', 'always' => $lektion->readings()->exists()],
            ['key' => 'exercises', 'label' => 'Übungen', 'always' => $exerciseSections !== []],
            ['key' => 'revision', 'label' => 'Wiederholung', 'always' => (bool) $lektion->revision_md],
            ['key' => 'test', 'label' => 'Lektionstest', 'always' => $exerciseSections !== []],
        ];

        return array_values(array_filter($sections, fn ($s) => $s['always']));
    }

    /** Exercise sets of a lesson (by section) with counts. */
    public function exerciseSets(Lektion $lektion): array
    {
        $counts = $lektion->exercises()->selectRaw('section, COUNT(*) as c, SUM(CASE WHEN type = \'listen\' THEN 1 ELSE 0 END) as listen')->groupBy('section')->get()->keyBy('section');
        $order = ['vocab', 'grammar', 'dialog', 'reading', 'listening', 'writing'];
        $labels = ['vocab' => 'Wortschatz', 'grammar' => 'Grammatik', 'dialog' => 'Dialoge', 'reading' => 'Lesen', 'listening' => 'Hören', 'writing' => 'Schreiben'];
        $sets = [];
        foreach ($order as $section) {
            $c = $counts->get($section);
            $readingQuestions = $section === 'reading' ? $lektion->readings->sum(fn ($r) => count($r->questions ?? [])) : 0;
            $n = ($c?->c ?? 0) + $readingQuestions + ($section === 'vocab' ? 8 : 0);
            if ($n === 0) {
                continue;
            }
            $sets[] = ['section' => $section, 'label' => $labels[$section], 'count' => $n];
        }

        return $sets;
    }

    /** @return array<string, array{completed: bool, data: array|null, completed_at: ?string}> */
    public function lessonProgress(User $user, Lektion $lektion): array
    {
        $rows = LessonProgress::where('user_id', $user->id)->where('lektion_id', $lektion->id)->get()->keyBy('section');
        $out = [];
        foreach ($this->sections($lektion) as $s) {
            $row = $rows->get($s['key']);
            $out[$s['key']] = [
                'completed' => $row?->completed_at !== null,
                'data' => $row?->data,
                'completed_at' => $row?->completed_at?->toIso8601String(),
            ];
        }

        return $out;
    }

    public function markSection(User $user, Lektion $lektion, string $section, array $data = [], bool $complete = true): LessonProgress
    {
        $row = LessonProgress::firstOrNew(['user_id' => $user->id, 'lektion_id' => $lektion->id, 'section' => $section]);
        $row->data = array_merge($row->data ?? [], $data);
        if ($complete && $row->completed_at === null) {
            $row->completed_at = now();
        }
        $row->save();

        return $row;
    }

    /** Called when an exercise session finishes: updates set / test progress. */
    public function recordSession(User $user, ExerciseSession $session): void
    {
        if (! $session->lektion_id) {
            return;
        }
        $lektion = $session->lektion;
        $score = $session->scorePercent();

        if ($session->mode === 'set' && $session->section) {
            $row = $this->markSection($user, $lektion, 'exercises', [], false);
            $sets = $row->data['sets'] ?? [];
            $sets[$session->section] = max($sets[$session->section] ?? 0, $score);
            $row->data = array_merge($row->data ?? [], ['sets' => $sets]);
            $required = array_column($this->exerciseSets($lektion), 'section');
            if (array_diff($required, array_keys($sets)) === [] && $row->completed_at === null) {
                $row->completed_at = now();
            }
            $row->save();
            if ($session->section === 'reading') {
                $this->markSection($user, $lektion, 'reading', ['score' => $score]);
            }
        }
        if ($session->mode === 'practice' && $session->section === 'vocab') {
            $row = $this->markSection($user, $lektion, 'vocab', [], false);
            $modes = $row->data['modes'] ?? [];
            $modes[$session->label] = $score;
            $row->data = array_merge($row->data ?? [], ['modes' => $modes]);
            if (count($modes) >= 2 && $row->completed_at === null) {
                $row->completed_at = now();
            }
            $row->save();
        }
        if ($session->mode === 'test') {
            $row = $this->markSection($user, $lektion, 'test', [], false);
            $best = max($row->data['best'] ?? 0, $score);
            $row->data = array_merge($row->data ?? [], ['best' => $best, 'attempts' => ($row->data['attempts'] ?? 0) + 1]);
            if ($session->isPassed() && $row->completed_at === null) {
                $row->completed_at = now();
            }
            $row->save();
        }
    }

    /** Percentage of sections done in a lesson. */
    public function lessonCompletion(User $user, Lektion $lektion): int
    {
        $progress = $this->lessonProgress($user, $lektion);
        if ($progress === []) {
            return 0;
        }
        $done = count(array_filter($progress, fn ($p) => $p['completed']));

        return (int) round($done * 100 / count($progress));
    }

    /** Course overview for the A1 dashboard. */
    public function overview(User $user, string $level = 'A1'): array
    {
        $lektionen = Lektion::level($level)->withCount('words')->get();
        $wordIds = Word::whereIn('lektion_id', $lektionen->pluck('id'))->pluck('id');
        $progressRows = LessonProgress::where('user_id', $user->id)->whereIn('lektion_id', $lektionen->pluck('id'))->get()->groupBy('lektion_id');
        $wordProgress = UserWordProgress::where('user_id', $user->id)->whereIn('word_id', $wordIds)->get();
        $byLektion = $wordProgress->groupBy(fn ($p) => Word::find($p->word_id)?->lektion_id); // small enough; keyed later
        $wordLektion = Word::whereIn('id', $wordProgress->pluck('word_id'))->pluck('lektion_id', 'id');

        $lessons = $lektionen->map(function (Lektion $l) use ($progressRows, $wordProgress, $wordLektion) {
            $sections = $this->sections($l);
            $rows = ($progressRows->get($l->id) ?? collect())->keyBy('section');
            $done = collect($sections)->filter(fn ($s) => $rows->get($s['key'])?->completed_at !== null)->count();
            $test = $rows->get('test');
            $learned = $wordProgress->filter(fn ($p) => ($wordLektion[$p->word_id] ?? null) === $l->id && $p->level >= 1)->count();

            return [
                'id' => $l->id,
                'number' => $l->number,
                'title' => $l->title,
                'subtitle' => $l->subtitle,
                'words_count' => $l->words_count,
                'words_learned' => $learned,
                'sections_total' => count($sections),
                'sections_done' => $done,
                'completion' => count($sections) ? (int) round($done * 100 / count($sections)) : 0,
                'test_best' => $test?->data['best'] ?? null,
                'completed' => $test?->completed_at !== null,
                'next_section' => collect($sections)->first(fn ($s) => $rows->get($s['key'])?->completed_at === null)['key'] ?? null,
                'sections' => collect($sections)->map(fn ($s) => ['key' => $s['key'], 'completed' => $rows->get($s['key'])?->completed_at !== null])->values()->all(),
            ];
        })->values();

        $current = $lessons->first(fn ($l) => ! $l['completed']) ?? $lessons->last();
        $attempts = ExerciseAttempt::where('user_id', $user->id)->where('answered_at', '>=', now()->subDays(30));
        $quizAnswers = QuizAnswer::whereHas('session', fn ($q) => $q->where('user_id', $user->id))->where('answered_at', '>=', now()->subDays(30));
        $totalAnswers = (clone $attempts)->count() + (clone $quizAnswers)->count();
        $correctAnswers = (clone $attempts)->where('is_correct', true)->count() + (clone $quizAnswers)->where('is_correct', true)->count();

        return [
            'level' => $level,
            'lessons' => $lessons,
            'completion' => $lessons->count() ? (int) round($lessons->avg('completion')) : 0,
            'lessons_completed' => $lessons->where('completed', true)->count(),
            'lessons_total' => $lessons->count(),
            'current' => $current,
            'words_total' => $wordIds->count(),
            'words_learned' => $wordProgress->where('level', '>=', 1)->count(),
            'words_mastered' => $wordProgress->where('level', '>=', UserWordProgress::MASTERED_LEVEL)->count(),
            'words_due' => $wordProgress->filter(fn ($p) => $p->next_review_at && $p->next_review_at->lte(now()->endOfDay()))->count(),
            'words_difficult' => $wordProgress->filter(fn ($p) => $p->is_difficult || $p->wrong_count > $p->correct_count)->count(),
            'accuracy' => $totalAnswers ? (int) round($correctAnswers * 100 / $totalAnswers) : null,
            'answers_30d' => $totalAnswers,
            'recent' => $this->recentActivity($user),
            'final_best' => ExerciseSession::where('user_id', $user->id)->where('mode', 'final')->whereNotNull('finished_at')->get()->max(fn ($s) => $s->scorePercent()),
        ];
    }

    public function recentActivity(User $user, int $limit = 8): Collection
    {
        $exercise = ExerciseSession::with('lektion')->where('user_id', $user->id)->whereNotNull('finished_at')->latest('finished_at')->limit($limit)->get()->toBase()
            ->map(fn (ExerciseSession $s) => ['kind' => 'exercise', 'id' => $s->id, 'mode' => $s->mode, 'label' => $s->label, 'lektion' => $s->lektion?->number, 'score' => $s->scorePercent(), 'total' => $s->total, 'finished_at' => $s->finished_at]);
        $quiz = QuizSession::with('lektion')->where('user_id', $user->id)->whereNotNull('finished_at')->latest('finished_at')->limit($limit)->get()->toBase()
            ->map(fn (QuizSession $s) => ['kind' => 'quiz', 'id' => $s->id, 'mode' => $s->mode, 'label' => 'Quiz', 'lektion' => $s->lektion?->number, 'score' => $s->scorePercent(), 'total' => $s->total, 'finished_at' => $s->finished_at]);

        return $exercise->merge($quiz)->sortByDesc('finished_at')->take($limit)->values()
            ->map(fn ($r) => $r + ['finished_at_iso' => $r['finished_at']->toIso8601String()])->map(fn ($r) => collect($r)->except('finished_at')->all());
    }

    /** Revision page data. */
    public function revisionOverview(User $user, string $level = 'A1'): array
    {
        $lektionen = Lektion::level($level)->get();
        $wordIds = Word::whereIn('lektion_id', $lektionen->pluck('id'))->pluck('id');
        $progress = UserWordProgress::with('word.lektion')->where('user_id', $user->id)->whereIn('word_id', $wordIds)->get();
        $map = fn (UserWordProgress $p) => [
            'word_id' => $p->word_id, 'display' => $p->word->display, 'english' => $p->word->english, 'arabic' => $p->word->arabic,
            'image' => $p->word->image, 'level' => $p->level, 'lektion' => $p->word->lektion?->number, 'is_difficult' => $p->is_difficult,
            'correct' => $p->correct_count, 'wrong' => $p->wrong_count, 'next_review_at' => $p->next_review_at?->toDateString(),
        ];

        $wrongExercises = ExerciseAttempt::with('exercise.lektion')->where('user_id', $user->id)->where('is_correct', false)->whereNotNull('exercise_id')
            ->latest('answered_at')->limit(300)->get()->unique('exercise_id')->take(30)
            ->map(fn (ExerciseAttempt $a) => ['exercise_id' => $a->exercise_id, 'prompt' => $a->exercise?->prompt, 'type' => $a->exercise?->type, 'lektion' => $a->exercise?->lektion?->number, 'given' => $a->given])
            ->filter(fn ($r) => $r['prompt'])->values();

        return [
            'due' => $progress->filter(fn ($p) => $p->word && $p->next_review_at && $p->next_review_at->lte(now()->endOfDay()))->sortBy('next_review_at')->take(40)->map($map)->values(),
            'difficult' => $progress->filter(fn ($p) => $p->word && ($p->is_difficult || $p->wrong_count > $p->correct_count))->sortByDesc('wrong_count')->take(40)->map($map)->values(),
            'wrong_exercises' => $wrongExercises,
            'lessons' => $lektionen->map(fn (Lektion $l) => ['id' => $l->id, 'number' => $l->number, 'title' => $l->title, 'has_revision' => (bool) $l->revision_md, 'completion' => $this->lessonCompletion($user, $l)])->values(),
            'counts' => [
                'learned' => $progress->where('level', '>=', 1)->count(),
                'mastered' => $progress->where('level', '>=', UserWordProgress::MASTERED_LEVEL)->count(),
                'total' => $wordIds->count(),
            ],
        ];
    }
}
