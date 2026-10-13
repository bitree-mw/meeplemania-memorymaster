<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Platform\Presence\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function __construct(private PresenceService $presence) {}

    /** Heartbeat; responds with fresh counts so clients need no separate poll. */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate(['playing' => ['sometimes', 'boolean']]);

        $this->presence->heartbeat($request, (bool) ($data['playing'] ?? false));

        return response()->json($this->presence->counts());
    }

    public function counts(): JsonResponse
    {
        return response()->json($this->presence->counts());
    }
}
