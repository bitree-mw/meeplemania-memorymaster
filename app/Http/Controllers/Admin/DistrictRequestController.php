<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DistrictChangeRequest;
use App\Platform\DistrictChanges;
use App\Platform\Districts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DistrictRequestController extends Controller
{
    public function __construct(private DistrictChanges $changes) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['approved', 'rejected'], true) ? $request->query('status') : DistrictChangeRequest::PENDING;

        $requests = DistrictChangeRequest::query()
            ->with(['user:id,username,email,avatar', 'user.profile:id,user_id,total_points,games_played', 'reviewer:id,username'])
            ->where('status', $status)
            ->orderBy($status === DistrictChangeRequest::PENDING ? 'created_at' : 'reviewed_at', $status === DistrictChangeRequest::PENDING ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (DistrictChangeRequest $r) => [
                'id' => $r->id,
                'user' => [
                    'id' => $r->user->id,
                    'username' => $r->user->username,
                    'email' => $r->user->email,
                    'avatar' => $r->user->avatar ?? 'meeple',
                    'total_points' => (int) ($r->user->profile->total_points ?? 0),
                    'games_played' => (int) ($r->user->profile->games_played ?? 0),
                ],
                'from' => Districts::name($r->from_district) ?? 'None',
                'to' => Districts::name($r->to_district),
                'reason' => $r->reason,
                'requested_at' => $r->created_at->toIso8601String(),
                'reviewer' => $r->reviewer?->username,
                'review_note' => $r->review_note,
                'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/DistrictRequests', ['requests' => $requests, 'status' => $status]);
    }

    public function approve(Request $request, DistrictChangeRequest $districtRequest): RedirectResponse
    {
        $this->changes->approve($districtRequest, $request->user());

        return back()->with('success', "{$districtRequest->user->username} moved to ".Districts::name($districtRequest->to_district).'. Their game progress has been reset.');
    }

    public function reject(Request $request, DistrictChangeRequest $districtRequest): RedirectResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        $this->changes->reject($districtRequest, $request->user(), $data['note'] ?? null);

        return back()->with('success', "Request from {$districtRequest->user->username} declined.");
    }
}
