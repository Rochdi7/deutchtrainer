<?php

namespace Tests\Feature;

use App\Models\Lektion;
use App\Models\QuizSession;
use App\Models\User;
use App\Models\Word;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Quiz lifelines (50/50, reveal a letter), hinted answers and the JSON "mark difficult" toggle. */
class QuizToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Word $word;

    private QuizSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->word = Word::factory()->noun()->create(['lektion_id' => Lektion::factory()->create()->id, 'german' => 'Adresse', 'article' => 'die']);
        $this->session = QuizSession::create([
            'user_id' => $this->user->id, 'mode' => 'global', 'total' => 2, 'correct' => 0, 'started_at' => now(),
            'questions' => [
                ['id' => 'mc', 'type' => 'ar_to_de', 'word_id' => $this->word->id, 'word' => [], 'prompt' => 'x', 'options' => ['die Adresse', 'der Tisch', 'das Haus', 'die Lampe'], 'answer' => 'die Adresse'],
                ['id' => 'ty', 'type' => 'typing', 'word_id' => $this->word->id, 'word' => [], 'prompt' => 'y', 'options' => [], 'answer' => 'die Adresse', 'accept' => ['die Adresse', 'Adresse']],
            ],
        ]);
    }

    public function test_fifty_fifty_removes_two_wrong_options_only(): void
    {
        $remove = $this->actingAs($this->user)->postJson(route('quiz.hint', $this->session), ['question_id' => 'mc', 'kind' => 'fifty'])
            ->assertOk()->json('remove');

        $this->assertCount(2, $remove);
        $this->assertNotContains('die Adresse', $remove);
    }

    public function test_letter_hint_reveals_article_plus_n_letters(): void
    {
        $this->actingAs($this->user)->postJson(route('quiz.hint', $this->session), ['question_id' => 'ty', 'kind' => 'letter', 'n' => 1])
            ->assertOk()->assertJson(['prefix' => 'die A']);
        $this->actingAs($this->user)->postJson(route('quiz.hint', $this->session), ['question_id' => 'ty', 'kind' => 'letter', 'n' => 3])
            ->assertOk()->assertJson(['prefix' => 'die Adr']);
        // Never gives the whole word away.
        $this->actingAs($this->user)->postJson(route('quiz.hint', $this->session), ['question_id' => 'ty', 'kind' => 'letter', 'n' => 40])
            ->assertOk()->assertJson(['prefix' => 'die Adress']);
    }

    public function test_hints_are_private_to_the_session_owner(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('quiz.hint', $this->session), ['question_id' => 'mc', 'kind' => 'fifty'])->assertForbidden();
    }

    public function test_a_hinted_correct_answer_scores_but_does_not_level_the_word_up(): void
    {
        $this->actingAs($this->user)->postJson(route('quiz.answer', $this->session), ['question_id' => 'mc', 'answer' => 'die Adresse', 'hinted' => true])
            ->assertOk()->assertJson(['correct' => true, 'progress' => null]);
        $this->assertSame(1, $this->session->fresh()->correct);
        $this->assertNull($this->user->wordProgress()->where('word_id', $this->word->id)->first());

        $this->actingAs($this->user)->postJson(route('quiz.answer', $this->session), ['question_id' => 'ty', 'answer' => 'Adresse'])
            ->assertOk()->assertJsonPath('progress.level', 1);
    }

    public function test_skipping_counts_as_wrong(): void
    {
        $this->actingAs($this->user)->postJson(route('quiz.answer', $this->session), ['question_id' => 'mc', 'answer' => null])
            ->assertOk()->assertJson(['correct' => false, 'answer' => 'die Adresse']);
    }

    public function test_difficult_toggle_answers_json_for_ajax(): void
    {
        $this->actingAs($this->user)->postJson(route('words.difficult', $this->word))->assertOk()->assertJson(['is_difficult' => true]);
        $this->actingAs($this->user)->get(route('quiz.show', $this->session))
            ->assertInertia(fn ($page) => $page->where('difficult', [$this->word->id]));
        $this->actingAs($this->user)->postJson(route('words.difficult', $this->word))->assertOk()->assertJson(['is_difficult' => false]);
    }
}
