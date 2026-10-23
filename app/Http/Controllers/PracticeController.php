<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseAttempt;
use App\Models\Lektion;
use App\Models\UserWordProgress;
use App\Services\Course\CourseProgressService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PracticeController extends Controller
{
    /** Üben: free practice hub. */
    public function index(Request $request, CourseProgressService $progress): Response
    {
        $user = $request->user();
        $lektionen = Lektion::level('A1')->withCount(['exercises', 'words'])->get()
            ->map(fn (Lektion $l) => ['id' => $l->id, 'number' => $l->number, 'title' => $l->title, 'exercises' => $l->exercises_count, 'words' => $l->words_count, 'completion' => $progress->lessonCompletion($user, $l)]);

        $typeCounts = Exercise::whereIn('lektion_id', $lektionen->pluck('id'))->selectRaw('type, COUNT(*) as c')->groupBy('type')->pluck('c', 'type');

        return Inertia::render('Practice/Index', [
            'lektionen' => $lektionen,
            'types' => collect(Exercise::TYPES)->reject(fn ($t) => $t === 'reading')->map(fn ($t) => ['key' => $t, 'count' => (int) ($typeCounts[$t] ?? 0)])->values(),
            'sections' => ['vocab', 'grammar', 'dialog', 'reading', 'listening'],
            'counts' => [
                'difficult' => UserWordProgress::where('user_id', $user->id)->where(fn ($q) => $q->where('is_difficult', true)->orWhereColumn('wrong_count', '>', 'correct_count'))->count(),
                'wrong' => ExerciseAttempt::where('user_id', $user->id)->where('is_correct', false)->whereNotNull('exercise_id')->distinct('exercise_id')->count('exercise_id'),
                'due' => UserWordProgress::where('user_id', $user->id)->where('next_review_at', '<=', now()->endOfDay())->count(),
            ],
            'finalBest' => $user->exerciseSessions()->where('mode', 'final')->whereNotNull('finished_at')->get()->max(fn ($s) => $s->scorePercent()),
        ]);
    }
}
