<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, StatsService $stats): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'lektionen' => $stats->perLektion($user),
            'global' => $stats->global($user),
            'chart' => $stats->last30Days($user),
            'weakest' => $stats->weakest($user),
            'recent' => $stats->recentSessions($user),
        ]);
    }
}
