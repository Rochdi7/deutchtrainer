<?php

namespace App\Http\Controllers;

use App\Models\GrammarResult;
use App\Services\Grammatik\GrammatikAktiv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** "Grammatik aktiv" module: the book's 80 units split into A1 / A2 / B1, with exercises and answer key. */
class GrammatikController extends Controller
{
    public function __construct(private readonly GrammatikAktiv $grammatik) {}

    public function index(Request $request, string $level = 'a1'): Response
    {
        $level = strtoupper($level);
        abort_unless(in_array($level, GrammatikAktiv::LEVELS, true), 404);

        $results = GrammarResult::where('user_id', $request->user()->id)->get()->groupBy('unit');

        $units = array_values(array_map(function (array $u) use ($results) {
            $done = $results->get($u['number'], collect());

            return [
                'number' => $u['number'],
                'level' => $u['level'],
                'chapter' => $u['chapter'],
                'title' => $u['title'],
                'topic' => $u['topic'],
                'topic_ar' => $u['topic_ar'] ?? null,
                'exercises' => count($u['exercises']),
                'done' => $done->count(),
                'correct' => $done->sum('correct'),
                'total' => $done->sum('total'),
            ];
        }, $this->grammatik->units()));

        return Inertia::render('Grammatik/Index', [
            'level' => $level,
            'levels' => GrammatikAktiv::LEVELS,
            'units' => $units,
        ]);
    }

    public function show(Request $request, int $unit): Response
    {
        $data = $this->grammatik->unit($unit);
        abort_unless($data !== null, 404);

        $numbers = array_keys($this->grammatik->units());
        $at = array_search($unit, $numbers, true);

        return Inertia::render('Grammatik/Unit', [
            'unit' => $data,
            'results' => GrammarResult::where('user_id', $request->user()->id)->where('unit', $unit)
                ->get(['exercise', 'correct', 'total'])->keyBy('exercise'),
            'prev' => $numbers[$at - 1] ?? null,
            'next' => $numbers[$at + 1] ?? null,
        ]);
    }

    /** Picture cropped from the book (storage/app/grammatik-aktiv/images), admin-only like the rest. */
    public function image(string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-z0-9-]+\.(png|jpg|webp)$/', $file), 404);
        $path = $this->grammatik->basePath().'/images/'.$file;
        abort_unless(is_file($path), 404);

        return response()->file($path, ['Cache-Control' => 'private, max-age=86400']);
    }

    /** Store the latest result of one exercise (gap/match: auto-checked; write: self-assessed). */
    public function result(Request $request, int $unit, string $exercise): RedirectResponse
    {
        $data = $this->grammatik->unit($unit);
        abort_unless($data !== null && in_array($exercise, array_column($data['exercises'], 'key'), true), 404);

        $valid = $request->validate([
            'correct' => ['required', 'integer', 'min:0', 'max:500'],
            'total' => ['required', 'integer', 'min:0', 'max:500', 'gte:correct'],
        ]);

        GrammarResult::updateOrCreate(
            ['user_id' => $request->user()->id, 'unit' => $unit, 'exercise' => $exercise],
            $valid,
        );

        return back();
    }
}
