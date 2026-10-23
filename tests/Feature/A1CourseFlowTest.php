<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseSession;
use App\Models\Lektion;
use App\Models\LessonProgress;
use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use App\Services\Course\ExerciseEngine;
use Database\Seeders\A1CourseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end learner journey through the seeded A1 course:
 * dashboard → lesson → exercise set → lesson test → practice / revision → final assessment.
 */
class A1CourseFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(A1CourseSeeder::class);
        $this->user = User::factory()->create();
    }

    public function test_seeder_is_idempotent_and_creates_the_full_a1_course(): void
    {
        $this->assertSame(8, Lektion::level('A1')->count());
        $this->assertGreaterThan(1000, Word::count());
        $this->assertGreaterThan(300, Exercise::count());
        $this->assertSame(0, Word::whereNull('english')->orWhere('english', '')->count(), 'every word has an English translation');

        $before = [Lektion::count(), Word::count(), Exercise::count()];
        $this->seed(A1CourseSeeder::class);
        $this->assertSame($before, [Lektion::count(), Word::count(), Exercise::count()], 'second seed run must not duplicate rows');

        foreach (Lektion::level('A1')->get() as $l) {
            $this->assertGreaterThanOrEqual(20, $l->exercises()->count(), "Lektion {$l->number} should have at least 20 exercises");
            $this->assertGreaterThanOrEqual(2, $l->readings()->count());
        }
    }

    public function test_course_dashboard_lesson_page_practice_and_revision_render(): void
    {
        $first = Lektion::level('A1')->where('number', 1)->firstOrFail();

        $this->actingAs($this->user)->get(route('course.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Course/Dashboard')
                ->where('course.level', 'A1')
                ->where('course.lessons_total', 8)
                ->where('course.lessons_completed', 0)
                ->where('course.current.id', $first->id));

        $this->actingAs($this->user)->get(route('lektionen.show', $first))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lektionen/Show')
                ->where('lektion.level', 'A1')
                ->where('lektion.number', 1)
                ->has('lektion.objectives')
                ->has('words')
                ->has('grammarNotes')
                ->has('dialogLines')
                ->has('readings')
                ->has('sections')
                ->has('sets')
                ->has('vocabModes'));

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->where('course.level', 'A1'));

        $this->actingAs($this->user)->get(route('lektionen.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lektionen/Index')->where('lektionen.0.completion', 0));

        $this->actingAs($this->user)->get(route('practice.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Practice/Index')->has('lektionen', 8)->has('types'));

        $this->actingAs($this->user)->get(route('revision.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Revision/Index')->has('revision.lessons', 8));
    }

    public function test_exercise_set_can_be_completed_with_feedback_and_progress(): void
    {
        $lektion = Lektion::level('A1')->where('number', 1)->firstOrFail();

        $this->actingAs($this->user)->post(route('exercises.start'), ['kind' => 'set', 'lektion_id' => $lektion->id, 'section' => 'grammar'])
            ->assertRedirect();
        $session = ExerciseSession::firstOrFail();
        $this->assertSame('set', $session->mode);
        $this->assertGreaterThan(0, $session->total);

        $this->actingAs($this->user)->get(route('exercises.show', $session))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Exercises/Play')
                ->where('session.id', $session->id)
                ->has('items', $session->total)
                ->missing('items.0.solution'));

        // One wrong answer first: feedback must contain the correct answer.
        $firstItem = $session->items[0];
        $wrong = $this->actingAs($this->user)->postJson(route('exercises.answer', $session), ['item_id' => $firstItem['id'], 'answer' => 'xxxx-not-an-answer']);
        $wrong->assertOk()->assertJsonPath('correct', false);
        $this->assertNotNull($wrong->json('answer'));

        // Same item twice is rejected.
        $this->actingAs($this->user)->postJson(route('exercises.answer', $session), ['item_id' => $firstItem['id'], 'answer' => 'x'])->assertStatus(422);

        foreach (array_slice($session->items, 1) as $item) {
            $res = $this->actingAs($this->user)->postJson(route('exercises.answer', $session), ['item_id' => $item['id'], 'answer' => $this->correctAnswer($item)]);
            $res->assertOk()->assertJsonPath('correct', true);
        }

        $finish = $this->actingAs($this->user)->postJson(route('exercises.finish', $session));
        $finish->assertOk()->assertJsonPath('total', $session->total)->assertJsonPath('correct', $session->total - 1);
        $this->assertCount(1, $finish->json('wrong'));
        $this->assertDatabaseCount('exercise_attempts', $session->total);

        // Set result stored in the "exercises" section progress.
        $progress = LessonProgress::where('user_id', $this->user->id)->where('lektion_id', $lektion->id)->where('section', 'exercises')->first();
        $this->assertNotNull($progress);
        $this->assertSame($finish->json('score'), $progress->data['sets']['grammar']);

        // Retry only the wrong item.
        $this->actingAs($this->user)->post(route('exercises.retry', $session))->assertRedirect();
        $retry = ExerciseSession::orderByDesc('id')->first();
        $this->assertSame(1, $retry->total);
    }

    public function test_vocab_session_updates_spaced_repetition_and_difficult_flag(): void
    {
        $lektion = Lektion::level('A1')->where('number', 1)->firstOrFail();

        $this->actingAs($this->user)->post(route('exercises.start'), ['kind' => 'vocab', 'lektion_id' => $lektion->id, 'mode' => 'mixed', 'count' => 12])->assertRedirect();
        $session = ExerciseSession::firstOrFail();
        $this->assertSame(12, $session->total);

        foreach ($session->items as $item) {
            $this->actingAs($this->user)->postJson(route('exercises.answer', $session), ['item_id' => $item['id'], 'answer' => $this->correctAnswer($item)])
                ->assertOk()->assertJsonPath('correct', true);
        }
        $this->actingAs($this->user)->postJson(route('exercises.finish', $session))->assertOk()->assertJsonPath('score', 100);

        $this->assertGreaterThan(0, UserWordProgress::where('user_id', $this->user->id)->where('level', '>=', 1)->count(), 'word progress (SM-2) updated from vocab exercises');

        $word = $lektion->words()->first();
        $this->actingAs($this->user)->post(route('words.difficult', $word))->assertRedirect();
        $this->assertTrue((bool) UserWordProgress::where('user_id', $this->user->id)->where('word_id', $word->id)->value('is_difficult'));
        $this->actingAs($this->user)->post(route('words.difficult', $word))->assertRedirect();
        $this->assertFalse((bool) UserWordProgress::where('user_id', $this->user->id)->where('word_id', $word->id)->value('is_difficult'));
    }

    public function test_lesson_test_passing_marks_lesson_completed_and_sections_can_be_marked(): void
    {
        $lektion = Lektion::level('A1')->where('number', 1)->firstOrFail();

        foreach (['objectives', 'vocab', 'grammar', 'dialog', 'reading', 'exercises', 'revision'] as $section) {
            $this->actingAs($this->user)->post(route('course.section', [$lektion, $section]))->assertRedirect();
        }
        $this->actingAs($this->user)->post(route('course.section', [$lektion, 'nonsense']))->assertNotFound();

        $this->actingAs($this->user)->post(route('exercises.start'), ['kind' => 'test', 'lektion_id' => $lektion->id])->assertRedirect();
        $session = ExerciseSession::firstOrFail();
        $this->assertSame('test', $session->mode);
        $this->assertGreaterThanOrEqual(15, $session->total);

        foreach ($session->items as $item) {
            $this->actingAs($this->user)->postJson(route('exercises.answer', $session), ['item_id' => $item['id'], 'answer' => $this->correctAnswer($item)])
                ->assertOk()->assertJsonPath('correct', true);
        }
        $this->actingAs($this->user)->postJson(route('exercises.finish', $session))->assertOk()->assertJsonPath('passed', true);

        $test = LessonProgress::where('user_id', $this->user->id)->where('lektion_id', $lektion->id)->where('section', 'test')->first();
        $this->assertNotNull($test?->completed_at);
        $this->assertSame(100, $test->data['best']);

        $this->actingAs($this->user)->get(route('course.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('course.lessons_completed', 1)
                ->where('course.lessons.0.completed', true)
                ->where('course.lessons.0.completion', 100)
                ->where('course.current.number', 2));
    }

    public function test_practice_revision_and_final_assessment_sessions_start(): void
    {
        $this->actingAs($this->user)->post(route('exercises.start'), ['kind' => 'practice', 'types' => ['mc', 'gap'], 'count' => 10])->assertRedirect();
        $practice = ExerciseSession::orderByDesc('id')->first();
        $this->assertSame('practice', $practice->mode);
        $this->assertSame(10, $practice->total);
        $this->assertTrue(collect($practice->items)->every(fn ($i) => in_array($i['type'], ['mc', 'gap'], true)));

        $lektion2 = Lektion::level('A1')->where('number', 2)->firstOrFail();
        $this->actingAs($this->user)->post(route('exercises.start'), ['kind' => 'practice', 'lektion_ids' => [$lektion2->id], 'sections' => ['vocab'], 'count' => 10])->assertRedirect();
        $byLesson = ExerciseSession::orderByDesc('id')->first();
        $this->assertGreaterThan(0, $byLesson->total);

        $this->actingAs($this->user)->post(route('exercises.start'), ['kind' => 'revision', 'count' => 20])->assertRedirect();
        $revision = ExerciseSession::orderByDesc('id')->first();
        $this->assertSame('revision', $revision->mode);
        $this->assertGreaterThan(0, $revision->total, 'revision falls back to unseen words when nothing is due');

        $this->actingAs($this->user)->post(route('exercises.start'), ['kind' => 'final'])->assertRedirect();
        $final = ExerciseSession::orderByDesc('id')->first();
        $this->assertSame('final', $final->mode);
        $this->assertGreaterThanOrEqual(30, $final->total);
        $lektionen = collect($final->items)->pluck('lektion_id')->filter()->unique();
        $this->assertGreaterThanOrEqual(6, $lektionen->count(), 'final assessment covers most lessons');

        // Another user cannot open my session.
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('exercises.show', $final))->assertForbidden();
    }

    public function test_engine_check_handles_every_item_type(): void
    {
        $engine = app(ExerciseEngine::class);

        $mc = ['type' => 'mc', 'data' => [], 'solution' => ['answer' => 'Guten Tag']];
        $this->assertTrue($engine->check($mc, 'Guten Tag')['correct']);
        $this->assertFalse($engine->check($mc, 'Hallo')['correct']);

        $gap = ['type' => 'gap', 'data' => [], 'solution' => ['answer' => 'heiße', 'accept' => ['heiße', 'heisse']]];
        $this->assertTrue($engine->check($gap, 'heisse')['correct']);
        $this->assertFalse($engine->check($gap, 'heißt')['correct']);

        $order = ['type' => 'order', 'data' => ['tokens' => ['Ich', 'komme', 'aus', 'Marokko.']], 'solution' => ['answer' => 'Ich komme aus Marokko.']];
        $this->assertTrue($engine->check($order, ['Ich', 'komme', 'aus', 'Marokko.'])['correct']);
        $this->assertFalse($engine->check($order, ['komme', 'Ich', 'aus', 'Marokko.'])['correct']);

        $match = ['type' => 'match', 'data' => [], 'solution' => ['pairs' => [['der', 'Tisch'], ['die', 'Lampe'], ['das', 'Buch']]]];
        $this->assertTrue($engine->check($match, [['die', 'Lampe'], ['der', 'Tisch'], ['das', 'Buch']])['correct']);
        $this->assertFalse($engine->check($match, [['die', 'Tisch'], ['der', 'Lampe'], ['das', 'Buch']])['correct']);
        $this->assertFalse($engine->check($match, [['der', 'Tisch']])['correct']);

        $translate = ['type' => 'translate', 'data' => ['direction' => 'en-de'], 'solution' => ['answer' => 'Ich bin neu im Deutschkurs.']];
        $this->assertTrue($engine->check($translate, 'ich bin neu im deutschkurs')['correct']);
        $translateEn = ['type' => 'translate', 'data' => ['direction' => 'de-en'], 'solution' => ['answer' => "I'm 21 years old."]];
        $this->assertTrue($engine->check($translateEn, 'I am 21 years old')['correct']);

        $write = ['type' => 'write', 'data' => [], 'solution' => ['model_answer' => 'Hallo, ich heiße …', 'checklist' => ['Name']]];
        $this->assertTrue($engine->check($write, ['text' => 'Hallo, ich heiße Sara.', 'self_ok' => true])['correct']);
        $this->assertFalse($engine->check($write, ['text' => '', 'self_ok' => true])['correct']);
        $this->assertSame(['Name'], $engine->check($write, ['text' => 'x', 'self_ok' => false])['checklist']);

        $listen = ['type' => 'listen', 'data' => ['text' => 'Wie geht es Ihnen?'], 'solution' => ['answer' => 'Danke, gut.', 'transcript' => 'Wie geht es Ihnen?']];
        $res = $engine->check($listen, 'Danke, gut.');
        $this->assertTrue($res['correct']);
        $this->assertSame('Wie geht es Ihnen?', $res['transcript']);
    }

    /** Build the correct answer for an item from its server-side solution (as the engine expects it). */
    private function correctAnswer(array $item): mixed
    {
        $s = $item['solution'];

        return match ($item['type']) {
            'match' => $s['pairs'],
            'order' => is_array($item['data']['tokens'] ?? null) && ($item['data']['join'] ?? ' ') === ''
                ? mb_str_split($s['answer'])
                : explode(' ', $s['answer']),
            'write' => ['text' => 'Hallo! Ich heiße Test und ich komme aus Marokko. Ich wohne in Berlin.', 'self_ok' => true],
            default => (string) $s['answer'],
        };
    }
}
