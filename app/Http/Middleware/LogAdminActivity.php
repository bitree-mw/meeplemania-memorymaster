<?php

namespace App\Http\Middleware;

use App\Platform\AdminActivity;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every successful change made from the admin area (any non-GET
 * request that did not fail validation) in the admin activity log.
 */
class LogAdminActivity
{
    /** Route name (without "admin.") => what the admin did. */
    private const LABELS = [
        'settings.update' => 'Changed settings',
        'settings.reset' => 'Reset settings to defaults',
        'players.disable' => 'Deactivated account',
        'players.enable' => 'Reactivated account',
        'players.password-reset' => 'Sent password reset link to',
        'players.promote' => 'Granted admin access to',
        'players.revoke' => 'Revoked admin access from',
        'sessions.approve' => 'Approved flagged run',
        'sessions.void' => 'Voided run',
        'challenges.upsert' => 'Updated Try Once challenge',
        'seasons.store' => 'Created season',
        'seasons.update' => 'Updated season',
        'seasons.destroy' => 'Deleted season',
        'achievements.store' => 'Created achievement',
        'achievements.update' => 'Updated achievement',
        'admins.store' => 'Created admin account',
        'district-requests.approve' => 'Approved district change (progress reset) for',
        'district-requests.reject' => 'Declined district change for',
    ];

    public function __construct(private AdminActivity $activity) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethodSafe() || $response->getStatusCode() >= 400 || $request->session()->has('errors')) {
            return $response;
        }

        $action = str((string) $request->route()?->getName())->after('admin.')->toString();

        $this->activity->record($action, trim((self::LABELS[$action] ?? $action).' '.$this->subject($request)), $request->user());

        return $response;
    }

    private function subject(Request $request): string
    {
        $parts = collect($request->route()?->parameters() ?? [])->map(fn ($value) => $value instanceof Model
            ? ($value->username ?? $value->name ?? $value->uuid ?? $value->user?->username ?? $value->key ?? "#{$value->getKey()}")
            : (string) $value);

        if ($parts->isEmpty()) {
            $parts = collect([$request->input('username') ?? $request->input('name') ?? $request->input('game')])->filter();
        }

        $subject = $parts->implode(' / ');

        return $request->filled('reason') ? "{$subject} (reason: {$request->input('reason')})" : $subject;
    }
}
