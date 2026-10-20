<?php

namespace Tests\Feature;

use App\Models\Conjugation;
use App\Models\Lektion;
use App\Models\QuizSession;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lektion_quiz_updates_progress_and_marks_completion(): void
    {
        $user = User::factory()->create();
        $lektion = Lektion::factory()->create();
        Word::factory()->count(10)->noun()->create(['lektion_id' => $lektion->id]);
        Word::factory()->count(4)->verb()->create(['lektion_id' => $lektion->id])
            ->each(fn (Word $w) => Conjugation::factory()->create(['word_id' => $w->id]));

        $response = $this->actingAs($user)->get(route('quiz.lektion', $lektion));
        $session = QuizSession::first();
        $response->assertRedirect(route('quiz.show', $session));
        $this->assertSame(14, $session->total);

        $this->actingAs($user)->get(route('quiz.show', $session))->assertOk();

        foreach ($session->questions as $q) {
            $res = $this->actingAs($user)->postJson(route('quiz.answer', $session), [
                'question_id' => $q['id'],
                'answer' => $q['answer'],
            ]);
            $res->assertOk()->assertJsonPath('correct', true);
        }

        // Answering the same question twice is rejected.
        $this->actingAs($user)->postJson(route('quiz.answer', $session), [
            'question_id' => $session->questions[0]['id'],
            'answer' => 'x',
        ])->assertStatus(422);

        $finish = $this->actingAs($user)->postJson(route('quiz.finish', $session));
        $finish->assertOk()->assertJsonPath('score', 100)->assertJsonPath('passed', true)->assertJsonPath('wrong', []);

        $this->assertDatabaseCount('quiz_answers', 14);
        $this->assertSame(14, $user->wordProgress()->where('level', 1)->count());

        $dashboard = $this->actingAs($user)->get(route('dashboard'));
        $dashboard->assertOk();
        $dashboard->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('lektionen.0.completed', true)
            ->where('lektionen.0.best_score', 100)
            ->where('global.xp', 14)
            ->where('global.streak', 1));
    }

    public function test_wrong_answers_are_listed_and_retry_creates_a_session_with_only_those_words(): void
    {
        $user = User::factory()->create();
        $lektion = Lektion::factory()->create();
        Word::factory()->count(8)->noun()->create(['lektion_id' => $lektion->id]);

        $this->actingAs($user)->get(route('quiz.lektion', $lektion));
        $session = QuizSession::first();

        $wrongIds = [];
        foreach ($session->questions as $i => $q) {
            $wrong = $i < 3;
            if ($wrong) {
                $wrongIds[] = $q['word_id'];
            }
            $this->actingAs($user)->postJson(route('quiz.answer', $session), [
                'question_id' => $q['id'],
                'answer' => $wrong ? '___nope___' : $q['answer'],
            ])->assertOk()->assertJsonPath('correct', ! $wrong);
        }

        $finish = $this->actingAs($user)->postJson(route('quiz.finish', $session))->assertOk();
        $this->assertCount(3, $finish->json('wrong'));
        $this->assertFalse($finish->json('passed'));

        $this->actingAs($user)->post(route('quiz.retry', $session))->assertRedirect();
        $retry = QuizSession::latest('id')->first();
        $this->assertSame('weak', $retry->mode);
        $this->assertEqualsCanonicalizing($wrongIds, array_column($retry->questions, 'word_id'));

        // Weak mode picks exactly those words too.
        $this->actingAs($user)->get(route('quiz.weak'))->assertRedirect();
        $weak = QuizSession::latest('id')->first();
        $this->assertEqualsCanonicalizing($wrongIds, array_column($weak->questions, 'word_id'));
    }

    public function test_sessions_are_private_to_their_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $session = QuizSession::factory()->create(['user_id' => $owner->id, 'questions' => []]);

        $this->actingAs($other)->get(route('quiz.show', $session))->assertForbidden();
        $this->actingAs($other)->postJson(route('quiz.finish', $session))->assertForbidden();
    }

    public function test_import_pages_require_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($user)->get(route('import.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('import.index'))->assertOk();
    }

    public function test_words_can_be_added_edited_and_deleted_inline(): void
    {
        $user = User::factory()->create();
        $lektion = Lektion::factory()->create();

        $this->actingAs($user)->post(route('words.store', $lektion), [
            'german' => 'Studienfach',
            'arabic' => 'التخصص الدراسي',
            'type' => 'noun',
            'category' => 'Universität und Studium',
            'article' => 'das',
            'plural_suffix' => '¨-er',
        ])->assertRedirect();

        $word = Word::first();
        $this->assertSame('Studienfächer', $word->plural);
        $this->assertSame('Universität und Studium', $word->category->name);

        $this->actingAs($user)->put(route('words.update', $word), [
            'german' => 'Studienfach',
            'arabic' => 'التخصص',
            'type' => 'noun',
            'category' => 'Nomen',
            'article' => 'das',
            'plural' => 'Studienfächer',
        ])->assertRedirect();
        $this->assertSame('التخصص', $word->fresh()->arabic);
        $this->assertSame('Nomen', $word->fresh()->category->name);

        $this->actingAs($user)->delete(route('words.destroy', $word))->assertRedirect();
        $this->assertDatabaseCount('words', 0);
    }
}
