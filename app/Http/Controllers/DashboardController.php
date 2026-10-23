<?php

namespace App\Http\Controllers;

use App\Models\Lektion;
use App\Services\Course\CourseProgressService;
use App\Services\StatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, StatsService $stats, CourseProgressService $progress): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'lektionen' => $stats->perLektion($user),
            'global' => $stats->global($user),
            'chart' => $stats->last30Days($user),
            'weakest' => $stats->weakest($user),
            'recent' => $stats->recentSessions($user),
            // Compact A1 course summary for the entry card (null when no course content is seeded).
            'course' => Lektion::level('A1')->exists() ? $progress->overview($user, 'A1') : null,
        ]);
    }
}
