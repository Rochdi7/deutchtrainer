<?php

namespace App\Services\Quiz;

use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use Carbon\CarbonInterface;

/**
 * SM-2-lite: correct → level+1 (max 5) with intervals 1, 3, 7, 14, 30 days;
 * wrong → level 0, due today.
 */
class ProgressService
{
    public function record(User $user, Word|int $word, bool $correct, ?CarbonInterface $now = null): UserWordProgress
    {
        $now ??= now();
        $wordId = $word instanceof Word ? $word->id : $word;

        $progress = UserWordProgress::firstOrNew(['user_id' => $user->id, 'word_id' => $wordId]);

        if ($correct) {
            $progress->level = min(UserWordProgress::MAX_LEVEL, (int) $progress->level + 1);
            $progress->correct_count = (int) $progress->correct_count + 1;
            $progress->streak = (int) $progress->streak + 1;
            $days = UserWordProgress::INTERVALS[$progress->level] ?? 30;
            $progress->next_review_at = $now->copy()->addDays($days)->startOfDay();
        } else {
            $progress->level = 0;
            $progress->wrong_count = (int) $progress->wrong_count + 1;
            $progress->streak = 0;
            $progress->next_review_at = $now->copy()->startOfDay();
        }

        $progress->last_seen_at = $now;
        $progress->save();

        return $progress;
    }
}
