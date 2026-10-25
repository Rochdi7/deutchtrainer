<?php

namespace Tests\Feature;

use App\Models\ExerciseSession;
use App\Models\User;
use App\Services\Quiz\AnswerChecker;
use Database\Seeders\A1CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeakingDrillTest extends TestCase
{
    use RefreshDatabase;

    public function test_spoken_score_tolerates_recogniser_quirks(): void
    {
        $c = new AnswerChecker;
        $this->assertSame(100, $c->spokenScore('Tisch', 'der Tisch'), 'article dropped by the recogniser');
        $this->assertSame(100, $c->spokenScore('Der Tisch.', 'der Tisch'));
        $this->assertSame(100, $c->spokenScore('Ich heiße Anna', 'Ich heiße Anna.'));
        $this->assertGreaterThanOrEqual(80, $c->spokenScore('ich heisse Ana und komme aus Berlin', 'Ich heiße Anna und komme aus Berlin.'));
        $this->assertLessThan(80, $c->spokenScore('Fisch', 'der Tisch'));
        $this->assertLessThan(50, $c->spokenScore('guten Morgen', 'Ich wohne in Berlin.'));
        $this->assertSame(0, $c->spokenScore('', 'der Tisch'));
    }

    public function test_speaking_session_scores_transcripts_and_updates_progress(): void
    {
        $this->seed(A1CourseSeeder::class);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('exercises.start'), ['kind' => 'speak', 'count' => 8])->assertRedirect();
        $session = ExerciseSession::latest('id')->first();
        $this->assertSame('Aussprache', $session->label);
        $this->assertSame(8, $session->total);
        $this->assertTrue(collect($session->items)->every(fn ($i) => $i['type'] === 'speak'));

        [$first, $second, $third] = $session->items;

        $ok = $this->actingAs($user)->postJson(route('exercises.answer', $session), ['item_id' => $first['id'], 'answer' => ['transcripts' => ['blabla', $first['solution']['answer']]]])
            ->assertOk()->json();
        $this->assertTrue($ok['correct']);
        $this->assertSame(100, $ok['score']);
        $this->assertSame($first['solution']['answer'], $ok['heard']);
        $this->assertSame(1, $user->wordProgress()->where('word_id', $first['word_id'])->value('level'));

        $this->actingAs($user)->postJson(route('exercises.answer', $session), ['item_id' => $second['id'], 'answer' => ['transcripts' => ['xyz qrs']]])
            ->assertOk()->assertJson(['correct' => false]);

        // No speech recognition in this browser: self-check.
        $this->actingAs($user)->postJson(route('exercises.answer', $session), ['item_id' => $third['id'], 'answer' => ['transcripts' => [], 'self_ok' => true]])
            ->assertOk()->assertJson(['correct' => true, 'score' => null]);

        $this->actingAs($user)->postJson(route('exercises.finish', $session))->assertOk()
            ->assertJsonPath('daily.today', 2)->assertJsonPath('daily.goal', 20);
    }
}
