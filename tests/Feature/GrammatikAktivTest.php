<?php

namespace Tests\Feature;

use App\Models\GrammarResult;
use App\Models\User;
use App\Services\Grammatik\GrammatikAktiv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Grammatik-aktiv module: admin-only pages, level split, result storage and the unit validator. */
class GrammatikAktivTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/grammatik-aktiv');
        File::ensureDirectoryExists($this->dir);
        File::put($this->dir.'/unit-01.json', json_encode($this->unit(1, 'A1')));
        File::put($this->dir.'/unit-49.json', json_encode($this->unit(49, 'B1')));
        $this->app->instance(GrammatikAktiv::class, new GrammatikAktiv($this->dir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function unit(int $n, string $level): array
    {
        return [
            'number' => $n, 'level' => $level, 'chapter' => 'Verben', 'title' => "Titel $n", 'topic' => "Thema $n",
            'book_pages' => [8, 9], 'info_md' => '### Regel',
            'exercises' => [
                ['key' => '1', 'instruction' => 'Ergänzen Sie.', 'type' => 'gap', 'items' => [['text' => '___ sprechen Deutsch.', 'answers' => ['Wir']]]],
                ['key' => '2', 'instruction' => 'Ordnen Sie zu.', 'type' => 'match', 'choices' => [['key' => 'A', 'text' => 'a'], ['key' => 'B', 'text' => 'b']], 'items' => [['text' => 'x', 'answer' => 'B']]],
                ['key' => '3', 'instruction' => 'Schreiben Sie.', 'type' => 'write', 'items' => [['text' => 'ich / gehen', 'solution' => 'Ich gehe.']]],
            ],
        ];
    }

    public function test_only_admins_can_open_the_module(): void
    {
        $this->actingAs(User::factory()->create())->get(route('grammatik.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('grammatik.show', 1))->assertForbidden();
    }

    public function test_index_lists_units_with_their_level(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        GrammarResult::create(['user_id' => $admin->id, 'unit' => 1, 'exercise' => '1', 'correct' => 1, 'total' => 1]);

        $this->actingAs($admin)->get(route('grammatik.index', 'b1'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Grammatik/Index')
            ->where('level', 'B1')
            ->has('units', 2)
            ->where('units.0.level', 'A1')
            ->where('units.0.done', 1)
            ->where('units.1.number', 49)
            ->where('units.1.level', 'B1'));

        $this->actingAs($admin)->get('/grammatik/c1')->assertNotFound();
    }

    public function test_unit_page_and_result_upsert(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('grammatik.show', 1))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Grammatik/Unit')
            ->where('unit.number', 1)
            ->has('unit.exercises', 3)
            ->where('prev', null)
            ->where('next', 49));
        $this->actingAs($admin)->get(route('grammatik.show', 2))->assertNotFound();

        $this->actingAs($admin)->post(route('grammatik.result', [1, '1']), ['correct' => 0, 'total' => 1])->assertRedirect();
        $this->actingAs($admin)->post(route('grammatik.result', [1, '1']), ['correct' => 1, 'total' => 1])->assertRedirect();
        $this->assertSame(1, GrammarResult::where('user_id', $admin->id)->count());
        $this->assertSame(1, GrammarResult::first()->correct);

        $this->actingAs($admin)->post(route('grammatik.result', [1, '9']), ['correct' => 1, 'total' => 1])->assertNotFound();
        $this->actingAs($admin)->post(route('grammatik.result', [1, '1']), ['correct' => 2, 'total' => 1])->assertSessionHasErrors('total');
    }

    public function test_book_pictures_are_served_to_admins_only(): void
    {
        File::ensureDirectoryExists($this->dir.'/images');
        File::put($this->dir.'/images/u01-1.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs(User::factory()->create())->get(route('grammatik.image', 'u01-1.png'))->assertForbidden();
        $this->actingAs($admin)->get(route('grammatik.image', 'u01-1.png'))->assertOk()->assertHeader('content-type', 'image/png');
        $this->actingAs($admin)->get(route('grammatik.image', 'missing.png'))->assertNotFound();
        $this->actingAs($admin)->get('/grammatik/bild/..%2Funit-01.json')->assertNotFound();
    }

    public function test_validator_reports_gap_answer_mismatch(): void
    {
        $unit = $this->unit(1, 'A1');
        $this->assertSame([], app(GrammatikAktiv::class)->validate($unit));

        $unit['exercises'][0]['items'][0]['answers'] = ['Wir', 'ihr'];
        $unit['exercises'][1]['items'][0]['answer'] = 'C';
        $unit['level'] = 'C1';
        $errors = app(GrammatikAktiv::class)->validate($unit);
        $this->assertCount(3, $errors);
        $this->assertStringContainsString('1 gaps but 2 answers', implode("\n", $errors));
    }
}
