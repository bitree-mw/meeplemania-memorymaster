<?php

namespace App\Http\Controllers\Admin;

use App\Games\GameSettings;
use App\Http\Controllers\Controller;
use App\Models\GameSeason;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Platform-wide seasons: ranked play in every game counts toward the current one. */
class SeasonController extends Controller
{
    public function index(GameSettings $settings): Response
    {
        $today = $settings->today();

        return Inertia::render('Admin/Seasons', [
            'seasons' => GameSeason::orderByDesc('starts_on')->get()->map(fn (GameSeason $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'starts_on' => $s->starts_on->toDateString(),
                'ends_on' => $s->ends_on->toDateString(),
                'is_current' => $s->starts_on->toDateString() <= $today && $s->ends_on->toDateString() >= $today,
                'is_past' => $s->ends_on->toDateString() < $today,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        GameSeason::create($data);

        return back()->with('success', "Season “{$data['name']}” created.");
    }

    public function update(Request $request, GameSeason $season): RedirectResponse
    {
        $season->update($this->validated($request, $season));

        return back()->with('success', 'Season updated.');
    }

    public function destroy(GameSeason $season): RedirectResponse
    {
        $season->delete();

        return back()->with('success', 'Season deleted. Its games remain in all-time statistics.');
    }

    private function validated(Request $request, ?GameSeason $ignore = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);

        $overlaps = GameSeason::query()
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->whereDate('starts_on', '<=', $data['ends_on'])
            ->whereDate('ends_on', '>=', $data['starts_on'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => 'Seasons cannot overlap.']);
        }

        return $data;
    }
}
