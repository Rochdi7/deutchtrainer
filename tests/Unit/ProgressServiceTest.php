<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Word;
use App\Services\Quiz\ProgressService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_answers_step_up_levels_with_growing_intervals(): void
    {
        Carbon::setTestNow('2026-10-18 10:00:00');
        $user = User::factory()->create();
        $word = Word::factory()->create();
        $service = new ProgressService;

        $expected = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30];
        foreach ($expected as $level => $days) {
            $p = $service->record($user, $word, true);
            $this->assertSame($level, $p->level);
            $this->assertSame($level, $p->streak);
            $this->assertSame($level, $p->correct_count);
            $this->assertSame(now()->addDays($days)->startOfDay()->toDateTimeString(), $p->next_review_at->toDateTimeString());
        }

        // Level is capped at 5, interval stays at 30 days.
        $p = $service->record($user, $word, true);
        $this->assertSame(5, $p->level);
        $this->assertSame(6, $p->correct_count);
        $this->assertSame(now()->addDays(30)->startOfDay()->toDateTimeString(), $p->next_review_at->toDateTimeString());
    }

    public function test_wrong_answer_resets_level_and_streak_and_makes_word_due_today(): void
    {
        Carbon::setTestNow('2026-10-18 15:30:00');
        $user = User::factory()->create();
        $word = Word::factory()->create();
        $service = new ProgressService;

        $service->record($user, $word, true);
        $service->record($user, $word, true);
        $service->record($user, $word, true);

        $p = $service->record($user, $word, false);

        $this->assertSame(0, $p->level);
        $this->assertSame(0, $p->streak);
        $this->assertSame(1, $p->wrong_count);
        $this->assertSame(3, $p->correct_count);
        $this->assertTrue($p->next_review_at->isSameDay(now()));
        $this->assertTrue($p->next_review_at->lte(now()));
        $this->assertTrue($p->isWeak() === false, 'wrong 1 vs correct 3 is not weak');
    }

    public function test_progress_rows_are_per_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $word = Word::factory()->create();
        $service = new ProgressService;

        $service->record($a, $word, true);
        $service->record($b, $word, false);

        $this->assertSame(1, $a->wordProgress()->first()->level);
        $this->assertSame(0, $b->wordProgress()->first()->level);
        $this->assertDatabaseCount('user_word_progress', 2);
    }
}
