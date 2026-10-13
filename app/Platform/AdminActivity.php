<?php

namespace App\Platform;

use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Audit trail for administrator accounts: sign-ins (including failed and
 * denied attempts) and every change made from the admin area.
 */
class AdminActivity
{
    public function record(string $action, string $description, ?User $actor = null): void
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        AdminActivityLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => Str::limit($description, 250),
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250) : 'console',
            'created_at' => now(),
        ]);
    }
}
