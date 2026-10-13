<?php

namespace App\Http\Controllers\Admin;

use App\Games\GameRegistry;
use App\Games\LeaderboardService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LeaderboardController;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardReviewController extends Controller
{
    public function __invoke(Request $request, LeaderboardService $leaderboards, GameRegistry $registry): Response
    {
        [$board, $search, $page] = LeaderboardController::resolveBoard($request, $registry);
        $result = $leaderboards->page($board, $page, $search);

        return Inertia::render('Admin/Leaderboards', [
            ...LeaderboardController::boardProps($board, $registry),
            'search' => $search,
            'rows' => $result['rows'],
            'meta' => $result['meta'],
        ]);
    }
}
