<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Platform\AchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AchievementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Achievements', [
            'achievements' => Achievement::orderBy('sort_order')->withCount('players')->get()->map(fn (Achievement $a) => [
                'id' => $a->id,
                'key' => $a->key,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'criteria' => $a->criteria,
                'criteria_label' => AchievementService::CRITERIA[$a->criteria]['label'] ?? $a->criteria,
                'direction' => AchievementService::CRITERIA[$a->criteria]['direction'] ?? 'min',
                'threshold' => $a->threshold,
                'is_active' => $a->is_active,
                'unlocked_by' => $a->players_count,
            ]),
        ]);
    }

    public function update(Request $request, Achievement $achievement): RedirectResponse
    {
        $achievement->update($request->validate([
            'name' => ['required', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:160'],
            'threshold' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]));

        return back()->with('success', "“{$achievement->name}” updated. Existing unlocks are kept.");
    }
}
