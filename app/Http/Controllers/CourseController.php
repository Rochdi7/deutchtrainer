<?php

namespace App\Http\Controllers;

use App\Models\Lektion;
use App\Models\LessonProgress;
use App\Services\Course\CourseProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(private readonly CourseProgressService $progress) {}

    /** A1 course dashboard. */
    public function dashboard(Request $request, string $level = 'A1'): Response
    {
        $level = strtoupper($level);
        abort_unless(Lektion::level($level)->exists(), 404);

        return Inertia::render('Course/Dashboard', [
            'course' => $this->progress->overview($request->user(), $level),
        ]);
    }

    /** Mark a lesson section as viewed / completed (objectives, grammar, dialog, revision, vocab). */
    public function markSection(Request $request, Lektion $lektion, string $section): RedirectResponse
    {
        abort_unless(in_array($section, LessonProgress::SECTIONS, true), 404);
        $data = $request->validate(['data' => ['nullable', 'array']])['data'] ?? [];
        $this->progress->markSection($request->user(), $lektion, $section, $data);

        return back();
    }
}
