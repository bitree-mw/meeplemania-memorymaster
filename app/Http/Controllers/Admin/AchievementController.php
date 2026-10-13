<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Platform\AchievementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'criteria' => collect(AchievementService::CRITERIA)->map(fn ($c, $key) => ['key' => $key, ...$c])->values(),
            'icons' => AchievementService::ICONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('achievements', 'name')],
            'description' => ['required', 'string', 'max:160'],
            'icon' => ['required', Rule::in(AchievementService::ICONS)],
            'criteria' => ['required', Rule::in(array_keys(AchievementService::CRITERIA))],
            'threshold' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $base = Str::limit(Str::slug($data['name'], '_'), 56, '') ?: 'achievement';
        $key = $base;
        for ($i = 2; Achievement::where('key', $key)->exists(); $i++) {
            $key = "{$base}_{$i}";
        }

        $achievement = Achievement::create([...$data, 'key' => $key, 'sort_order' => (int) Achievement::max('sort_order') + 1]);

        return back()->with('success', "“{$achievement->name}” created. Players who meet it unlock it after their next ranked run.");
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
