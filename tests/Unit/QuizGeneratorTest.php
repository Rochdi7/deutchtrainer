<?php

namespace Tests\Unit;

use App\Models\Conjugation;
use App\Models\DialogLine;
use App\Models\Lektion;
use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use App\Services\Quiz\QuizGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'questions_per_session' => 20,
            'pass_threshold' => 0.8,
            'ratios' => ['ar_to_de' => 40, 'de_to_ar' => 25, 'article' => 15, 'conjugation' => 10, 'typing' => 10],
            'plural_share' => 0,
            'dialog_max' => 0,
            'irregular_weight' => 3,
            'global_mix' => ['due' => 0.5, 'random' => 0.3, 'last_lektion' => 0.2],
        ], $overrides);
    }

    private function seedLektion(int $nouns = 12, int $verbs = 6, int $adjectives = 6): Lektion
    {
        $lektion = Lektion::factory()->create();
        Word::factory()->count($nouns)->noun()->create(['lektion_id' => $lektion->id]);
        Word::factory()->count($verbs)->verb()->create(['lektion_id' => $lektion->id])
            ->each(fn (Word $w) => Conjugation::factory()->create(['word_id' => $w->id]));
        Word::factory()->count($adjectives)->adjective()->create(['lektion_id' => $lektion->id]);

        return $lektion;
    }

    public function test_never_repeats_a_word_within_a_session(): void
    {
        $lektion = $this->seedLektion();
        $questions = (new QuizGenerator($this->config()))->forLektion(User::factory()->create(), $lektion);

        $this->assertCount(20, $questions);
        $ids = array_column($questions, 'word_id');
        $this->assertSame(count($ids), count(array_unique($ids)), 'A word appeared twice in one session');
    }

    public function test_session_is_capped_by_available_words(): void
    {
        $lektion = Lektion::factory()->create();
        Word::factory()->count(5)->noun()->create(['lektion_id' => $lektion->id]);

        $questions = (new QuizGenerator($this->config()))->forLektion(User::factory()->create(), $lektion);

        $this->assertCount(5, $questions);
    }

    public function test_distractors_are_same_type_and_never_equal_the_answer(): void
    {
        $lektion = $this->seedLektion(nouns: 15, verbs: 8, adjectives: 8);
        $words = Word::all()->keyBy('id');
        $generator = new QuizGenerator($this->config());

        for ($i = 0; $i < 5; $i++) {
            foreach ($generator->forLektion(User::factory()->create(), $lektion) as $q) {
                if (in_array($q['type'], ['article', 'conjugation'])) {
                    continue;
                }
                if ($q['type'] === 'typing') {
                    $this->assertSame([], $q['options']);

                    continue;
                }

                $this->assertCount(4, $q['options'], "{$q['type']} must offer 4 options");
                $this->assertContains($q['answer'], $q['options']);
                $this->assertCount(4, array_unique($q['options']), 'Options must be distinct');

                $field = $q['type'] === 'de_to_ar' ? 'arabic' : 'display';
                $answerWord = $words[$q['word_id']];
                foreach ($q['options'] as $opt) {
                    if ($opt === $q['answer']) {
                        continue;
                    }
                    $this->assertNotSame($answerWord->{$field}, $opt, 'Distractor equals the answer');
                    $source = $words->first(fn (Word $w) => $w->{$field} === $opt);
                    $this->assertNotNull($source);
                    $this->assertSame($answerWord->type, $source->type, "Distractor '{$opt}' is not of type {$answerWord->type}");
                }
            }
        }
    }

    public function test_article_questions_only_use_der_die_das(): void
    {
        $lektion = $this->seedLektion();
        $questions = (new QuizGenerator($this->config(['ratios' => ['ar_to_de' => 0, 'de_to_ar' => 0, 'article' => 100, 'conjugation' => 0, 'typing' => 0]])))
            ->forLektion(User::factory()->create(), $lektion);

        $articles = array_filter($questions, fn ($q) => $q['type'] === 'article');
        $this->assertNotEmpty($articles);
        foreach ($articles as $q) {
            $this->assertSame(['der', 'die', 'das'], $q['options']);
            $this->assertContains($q['answer'], ['der', 'die', 'das']);
        }
    }

    public function test_mix_ratios_are_respected_when_pool_supports_them(): void
    {
        // Large, balanced pool so every slot type can be served.
        $lektion = Lektion::factory()->create();
        Word::factory()->count(60)->noun()->create(['lektion_id' => $lektion->id]);
        Word::factory()->count(30)->verb()->create(['lektion_id' => $lektion->id])
            ->each(fn (Word $w) => Conjugation::factory()->create(['word_id' => $w->id]));

        $generator = new QuizGenerator($this->config());
        $counts = array_fill_keys(QuizGenerator::TYPES, 0);
        $runs = 10;
        for ($i = 0; $i < $runs; $i++) {
            foreach ($generator->forLektion(User::factory()->create(), $lektion) as $q) {
                $counts[$q['type']]++;
            }
        }

        $total = $runs * 20;
        $this->assertEqualsWithDelta(0.40, $counts['ar_to_de'] / $total, 0.06);
        $this->assertEqualsWithDelta(0.25, $counts['de_to_ar'] / $total, 0.06);
        $this->assertEqualsWithDelta(0.15, $counts['article'] / $total, 0.06);
        $this->assertEqualsWithDelta(0.10, $counts['conjugation'] / $total, 0.06);
        $this->assertEqualsWithDelta(0.10, $counts['typing'] / $total, 0.06);
    }

    public function test_slots_sum_to_requested_count(): void
    {
        $generator = new QuizGenerator($this->config());
        foreach ([1, 7, 20, 33] as $n) {
            $this->assertCount($n, $generator->slots($n));
        }
        $slots = $generator->slots(20);
        $this->assertSame(8, count(array_keys($slots, 'ar_to_de')));
        $this->assertSame(5, count(array_keys($slots, 'de_to_ar')));
        $this->assertSame(3, count(array_keys($slots, 'article')));
        $this->assertSame(2, count(array_keys($slots, 'conjugation')));
        $this->assertSame(2, count(array_keys($slots, 'typing')));
    }

    public function test_conjugation_question_targets_a_real_form(): void
    {
        $lektion = Lektion::factory()->create();
        $verbs = Word::factory()->count(6)->verb(irregular: true)->create(['lektion_id' => $lektion->id]);
        $verbs->each(fn (Word $w) => Conjugation::factory()->create(['word_id' => $w->id]));

        $questions = (new QuizGenerator($this->config(['ratios' => ['ar_to_de' => 0, 'de_to_ar' => 0, 'article' => 0, 'conjugation' => 100, 'typing' => 0]])))
            ->forLektion(User::factory()->create(), $lektion);

        $this->assertCount(6, $questions);
        foreach ($questions as $q) {
            $this->assertSame('conjugation', $q['type']);
            $conj = Conjugation::where('word_id', $q['word_id'])->first();
            $this->assertSame($conj->{$q['person']}, $q['answer']);
            $this->assertContains($q['answer'], $q['options']);
            $this->assertCount(4, array_unique($q['options']));
        }
    }

    public function test_dialog_questions_replace_de_to_ar_slots_when_lines_exist(): void
    {
        $lektion = $this->seedLektion();
        DialogLine::factory()->count(6)->create(['lektion_id' => $lektion->id]);

        $questions = (new QuizGenerator($this->config(['dialog_max' => 2])))->forLektion(User::factory()->create(), $lektion);
        $dialog = array_filter($questions, fn ($q) => $q['type'] === 'dialog');

        $this->assertCount(2, $dialog);
        foreach ($dialog as $q) {
            $this->assertNull($q['word_id']);
            $this->assertContains($q['answer'], $q['options']);
            $this->assertCount(4, array_unique($q['options']));
        }
    }

    public function test_weak_mode_only_uses_words_with_more_wrong_than_correct(): void
    {
        $user = User::factory()->create();
        $lektion = $this->seedLektion();
        $words = Word::all();

        UserWordProgress::factory()->create(['user_id' => $user->id, 'word_id' => $words[0]->id, 'wrong_count' => 3, 'correct_count' => 1]);
        UserWordProgress::factory()->create(['user_id' => $user->id, 'word_id' => $words[1]->id, 'wrong_count' => 2, 'correct_count' => 0]);
        UserWordProgress::factory()->create(['user_id' => $user->id, 'word_id' => $words[2]->id, 'wrong_count' => 1, 'correct_count' => 5]);

        $questions = (new QuizGenerator($this->config()))->weak($user);

        $this->assertCount(2, $questions);
        $this->assertEqualsCanonicalizing([$words[0]->id, $words[1]->id], array_column($questions, 'word_id'));
    }

    public function test_global_mode_prefers_due_words(): void
    {
        $user = User::factory()->create();
        $lektion = $this->seedLektion(nouns: 30, verbs: 0, adjectives: 0);
        $words = Word::all();

        // Everything mastered and not due, except 10 words that are due today.
        foreach ($words as $i => $w) {
            UserWordProgress::factory()->create([
                'user_id' => $user->id,
                'word_id' => $w->id,
                'level' => 5,
                'next_review_at' => $i < 10 ? now()->subDay() : now()->addDays(20),
            ]);
        }
        $dueIds = $words->take(10)->pluck('id')->all();

        $questions = (new QuizGenerator($this->config()))->global($user);
        $askedDue = count(array_intersect(array_column($questions, 'word_id'), $dueIds));

        $this->assertCount(20, $questions);
        $this->assertGreaterThanOrEqual(10, $askedDue, 'All 10 due words should be in a 20-question global session (50 % due share)');
    }
}
