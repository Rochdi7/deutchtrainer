<?php

namespace App\Http\Controllers;

use App\Models\UserWordProgress;
use App\Models\Word;
use App\Services\Course\CourseProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RevisionController extends Controller
{
    /** Wiederholung: due words, difficult words, previous mistakes, lesson summaries. */
    public function index(Request $request, CourseProgressService $progress): Response
    {
        return Inertia::render('Revision/Index', [
            'revision' => $progress->revisionOverview($request->user()),
        ]);
    }

    /** Mark / unmark a word as difficult (adds it to the revision queue). */
    public function toggleDifficult(Request $request, Word $word): RedirectResponse
    {
        $p = UserWordProgress::firstOrNew(['user_id' => $request->user()->id, 'word_id' => $word->id]);
        $p->is_difficult = ! $p->is_difficult;
        if ($p->is_difficult) {
            $p->next_review_at = $p->next_review_at && $p->next_review_at->isPast() ? $p->next_review_at : now()->startOfDay();
        }
        $p->save();

        return back();
    }
}
