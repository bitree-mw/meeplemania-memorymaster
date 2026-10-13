<?php

namespace App\Platform;

use App\Games\LeaderboardService;
use App\Models\DistrictChangeRequest;
use App\Models\GameSession;
use App\Models\LeaderboardSnapshot;
use App\Models\PlayerAchievement;
use App\Models\PlayerProfile;
use App\Models\PlayerStatistic;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A player's district is chosen once. Changing it later is a request an admin
 * approves or declines; approval moves the player and wipes their game
 * progress (runs, points, statistics, achievements, rank history) so nobody can
 * carry a score into a new district. The player is told by a pop-up on their
 * next visit (noticeFor / dismiss).
 */
class DistrictChanges
{
    public function __construct(private LeaderboardService $leaderboards) {}

    public function pendingFor(User $user): ?DistrictChangeRequest
    {
        return DistrictChangeRequest::where('user_id', $user->id)->where('status', DistrictChangeRequest::PENDING)->first();
    }

    public function request(User $user, string $to, ?string $reason): DistrictChangeRequest
    {
        if ($to === $user->district) {
            throw ValidationException::withMessages(['to_district' => 'That is already your district.']);
        }

        if ($this->pendingFor($user)) {
            throw ValidationException::withMessages(['to_district' => 'You already have a request waiting for an admin.']);
        }

        return DistrictChangeRequest::create([
            'user_id' => $user->id,
            'from_district' => $user->district,
            'to_district' => $to,
            'reason' => $reason,
            'status' => DistrictChangeRequest::PENDING,
        ]);
    }

    public function approve(DistrictChangeRequest $request, User $admin): void
    {
        $this->ensurePending($request);

        DB::transaction(function () use ($request, $admin) {
            $user = $request->user;

            // Sessions cascade to rounds and Try Once attempts.
            GameSession::where('user_id', $user->id)->delete();
            PlayerStatistic::where('user_id', $user->id)->delete();
            PlayerProfile::where('user_id', $user->id)->delete();
            PlayerAchievement::where('user_id', $user->id)->delete();
            LeaderboardSnapshot::where('user_id', $user->id)->delete();

            $user->forceFill(['district' => $request->to_district])->save();
            $request->update(['status' => DistrictChangeRequest::APPROVED, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        });

        $this->leaderboards->forgetCache();
    }

    public function reject(DistrictChangeRequest $request, User $admin, ?string $note): void
    {
        $this->ensurePending($request);

        $request->update([
            'status' => DistrictChangeRequest::REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /** The latest decision the player has not seen yet, for the pop-up. */
    public function noticeFor(User $user): ?array
    {
        $request = DistrictChangeRequest::where('user_id', $user->id)
            ->whereIn('status', [DistrictChangeRequest::APPROVED, DistrictChangeRequest::REJECTED])
            ->whereNull('notified_at')
            ->latest('reviewed_at')
            ->first();

        return $request ? [
            'id' => $request->id,
            'status' => $request->status,
            'from' => Districts::name($request->from_district),
            'to' => Districts::name($request->to_district),
            'note' => $request->review_note,
        ] : null;
    }

    public function dismiss(User $user, int $requestId): void
    {
        DistrictChangeRequest::where('user_id', $user->id)->where('id', $requestId)->whereNull('notified_at')->update(['notified_at' => now()]);
    }

    private function ensurePending(DistrictChangeRequest $request): void
    {
        if ($request->status !== DistrictChangeRequest::PENDING) {
            throw ValidationException::withMessages(['request' => 'This request has already been reviewed.']);
        }
    }
}
