<?php

namespace App\Services;

use App\Models\Lektion;
use App\Models\QuizAnswer;
use App\Models\QuizSession;
use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class StatsService
{
    public function perLektion(User $user): Collection
    {
        $lektionen = Lektion::withCount('words')->orderBy('number')->get();
        if ($lektionen->isEmpty()) {
            return collect();
        }

        $mastered = UserWordProgress::query()
            ->join('words', 'words.id', '=', 'user_word_progress.word_id')
            ->where('user_word_progress.user_id', $user->id)
            ->where('user_word_progress.level', '>=', UserWordProgress::MASTERED_LEVEL)
            ->selectRaw('words.lektion_id, COUNT(*) as c')
            ->groupBy('words.lektion_id')
            ->pluck('c', 'lektion_id');

        $best = QuizSession::where('user_id', $user->id)
            ->where('mode', 'lektion')
            ->whereNotNull('finished_at')
            ->where('total', '>', 0)
            ->get()
            ->groupBy('lektion_id')
            ->map(fn (Collection $sessions) => $sessions->max(fn (QuizSession $s) => $s->scorePercent()));

        return $lektionen->map(function (Lektion $l) use ($mastered, $best) {
            $m = (int) ($mastered[$l->id] ?? 0);
            $b = $best->get($l->id);

            return [
                'id' => $l->id,
                'number' => $l->number,
                'title' => $l->title,
                'words_count' => $l->words_count,
                'mastered_count' => $m,
                'mastered_percent' => $l->words_count ? (int) round($m * 100 / $l->words_count) : 0,
                'best_score' => $b,
                'completed' => $b !== null && $b >= (int) (QuizSession::PASS_THRESHOLD * 100),
            ];
        });
    }

    public function global(User $user): array
    {
        $total = Word::count();
        $mastered = UserWordProgress::where('user_id', $user->id)->where('level', '>=', UserWordProgress::MASTERED_LEVEL)->count();
        $due = UserWordProgress::where('user_id', $user->id)->where('next_review_at', '<=', now()->endOfDay())->count();
        $xp = QuizAnswer::whereHas('session', fn ($q) => $q->where('user_id', $user->id))->where('is_correct', true)->count();

        return [
            'total_words' => $total,
            'mastered' => $mastered,
            'due_today' => $due,
            'streak' => $this->dailyStreak($user),
            'xp' => $xp,
            'sessions' => QuizSession::where('user_id', $user->id)->whereNotNull('finished_at')->count(),
        ];
    }

    /** Consecutive days (ending today or yesterday) with at least one session. */
    public function dailyStreak(User $user): int
    {
        $days = QuizSession::where('user_id', $user->id)
            ->where('started_at', '>=', now()->subDays(400))
            ->get(['started_at'])
            ->map(fn ($s) => $s->started_at->toDateString())
            ->unique()->flip();

        if ($days->isEmpty()) {
            return 0;
        }

        $cursor = today();
        if (! $days->has($cursor->toDateString())) {
            $cursor = $cursor->subDay();
            if (! $days->has($cursor->toDateString())) {
                return 0;
            }
        }

        $streak = 0;
        while ($days->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /** Last 30 days: sessions per day and accuracy per day. */
    public function last30Days(User $user): array
    {
        $from = today()->subDays(29);
        $sessions = QuizSession::where('user_id', $user->id)->where('started_at', '>=', $from)->get(['id', 'started_at']);
        $answers = QuizAnswer::whereIn('quiz_session_id', $sessions->pluck('id'))->get(['answered_at', 'is_correct'])
            ->groupBy(fn ($a) => $a->answered_at->toDateString());
        $byDay = $sessions->groupBy(fn ($s) => $s->started_at->toDateString());

        $labels = $counts = $accuracy = [];
        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $key = $d->toDateString();
            $labels[] = $d->format('d.m');
            $counts[] = $byDay->has($key) ? $byDay[$key]->count() : 0;
            $a = $answers->get($key);
            $accuracy[] = $a && $a->count() ? (int) round($a->where('is_correct', true)->count() * 100 / $a->count()) : null;
        }

        return ['labels' => $labels, 'sessions' => $counts, 'accuracy' => $accuracy];
    }

    public function weakest(User $user, int $limit = 10): Collection
    {
        return UserWordProgress::with('word.lektion')
            ->where('user_id', $user->id)
            ->where('wrong_count', '>', 0)
            ->orderByRaw('(wrong_count - correct_count) DESC, wrong_count DESC')
            ->limit($limit)
            ->get()
            ->filter(fn ($p) => $p->word)
            ->map(fn (UserWordProgress $p) => [
                'word_id' => $p->word_id,
                'display' => $p->word->display,
                'arabic' => $p->word->arabic,
                'type' => $p->word->type,
                'lektion' => $p->word->lektion?->number,
                'level' => $p->level,
                'correct' => $p->correct_count,
                'wrong' => $p->wrong_count,
            ])->values();
    }

    public function recentSessions(User $user, int $limit = 8): Collection
    {
        return QuizSession::with('lektion')->where('user_id', $user->id)->whereNotNull('finished_at')
            ->latest('finished_at')->limit($limit)->get()
            ->map(fn (QuizSession $s) => [
                'id' => $s->id,
                'mode' => $s->mode,
                'lektion' => $s->lektion?->number,
                'score' => $s->scorePercent(),
                'correct' => $s->correct,
                'total' => $s->total,
                'finished_at' => $s->finished_at->toIso8601String(),
            ]);
    }
}
