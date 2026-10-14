<?php

namespace App\Http\Controllers\Api;

use App\Games\GameRegistry;
use App\Games\PlayService;
use App\Http\Controllers\Controller;
use App\Models\GameMode;
use App\Models\GameSession;
use App\Platform\Device;
use App\Platform\GuestPlays;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PlayController extends Controller
{
    public function __construct(private PlayService $play, private GameRegistry $registry) {}

    public function store(Request $request, GuestPlays $guests): JsonResponse
    {
        $data = $request->validate([
            'game' => ['required', 'string', Rule::in($this->registry->slugs())],
            'mode' => ['required', Rule::in(GameMode::ALL)],
            'duration' => ['nullable', 'integer', Rule::in(config('games.platform.timed_durations'))],
        ]);

        $game = $this->registry->game($data['game']);
        abort_unless($game && ($mode = $this->registry->mode($game, $data['mode'])), 404);

        // Guests' games belong to their device, which also carries their play allowance.
        $guestToken = $request->user() ? null : Device::id($request);

        if ($guestToken && $guests->exhausted($guestToken)) {
            return response()->json(['message' => $guests->limitMessage(), 'code' => 'guest_limit'], 403);
        }

        return response()->json($this->play->start($game, $mode, $request->user(), $guestToken, $data['duration'] ?? null), 201);
    }

    public function show(GameSession $session): JsonResponse
    {
        Gate::authorize('play', $session);

        return response()->json([
            'session' => $this->play->present($session),
            'summary' => $session->isActive() ? null : $this->play->summary($session),
        ]);
    }

    public function sync(Request $request, GameSession $session): JsonResponse
    {
        Gate::authorize('play', $session);

        return response()->json($this->play->sync($session, $this->results($request)));
    }

    public function finish(Request $request, GameSession $session): JsonResponse
    {
        Gate::authorize('play', $session);

        $request->validate(['reason' => ['required', Rule::in(['ended', 'exited', 'time_up'])]]);

        return response()->json($this->play->finish($session, $this->results($request), $request->input('reason')));
    }

    private function results(Request $request): array
    {
        return $request->validate([
            'results' => ['present', 'array', 'max:60'],
            'results.*.key' => ['required', 'string', 'max:16'],
            'results.*.inputs' => ['present', 'array', 'max:80'],
            'results.*.inputs.*' => ['integer', 'min:0', 'max:100'],
            'results.*.timings' => ['present', 'array', 'max:80'],
            'results.*.timings.*' => ['integer', 'min:0', 'max:3600000'],
        ])['results'];
    }
}
