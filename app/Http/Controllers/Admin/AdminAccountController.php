<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\User;
use App\Platform\AdminAccounts;
use App\Platform\Districts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administrator accounts (capped at User::MAX_ADMINS) and their activity log.
 */
class AdminAccountController extends Controller
{
    public function __construct(private AdminAccounts $admins) {}

    public function index(Request $request): Response
    {
        $filter = $request->validate([
            'admin' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:64'],
        ]);

        $lastLogins = AdminActivityLog::where('action', 'login')
            ->selectRaw('user_id, MAX(created_at) as at')
            ->groupBy('user_id')
            ->pluck('at', 'user_id');

        $activity = AdminActivityLog::query()
            ->with('user:id,username')
            ->when($filter['admin'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filter['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AdminActivityLog $log) => [
                'id' => $log->id,
                'admin' => $log->user?->username,
                'action' => $log->action,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Admins', [
            'admins' => User::where('role', User::ROLE_ADMIN)->orderBy('created_at')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'email' => $u->email,
                'avatar' => $u->avatar ?? 'meeple',
                'disabled' => $u->isDisabled(),
                'joined' => $u->created_at->toDateString(),
                'last_login' => isset($lastLogins[$u->id]) ? Carbon::parse($lastLogins[$u->id])->toIso8601String() : null,
            ]),
            'max' => User::MAX_ADMINS,
            'slotsLeft' => $this->admins->slotsLeft(),
            'activity' => $activity,
            'actions' => AdminActivityLog::distinct()->orderBy('action')->pluck('action'),
            'filter' => ['admin' => isset($filter['admin']) ? (int) $filter['admin'] : null, 'action' => $filter['action'] ?? null],
            'districts' => Districts::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'district' => ['nullable', Rule::in(Districts::keys())],
        ], [
            'username.regex' => 'Usernames may only contain letters, numbers and underscores.',
        ]);

        $user = $this->admins->create($data);

        return back()->with('success', "Admin account {$user->username} created.");
    }

    public function promote(User $player): RedirectResponse
    {
        if ($player->isDisabled()) {
            throw ValidationException::withMessages(['admin' => 'Reactivate this account before granting admin access.']);
        }

        $this->admins->promote($player);

        return back()->with('success', "{$player->username} is now an administrator.");
    }

    public function revoke(Request $request, User $player): RedirectResponse
    {
        if ($player->is($request->user())) {
            throw ValidationException::withMessages(['admin' => 'You cannot revoke your own admin access.']);
        }

        $this->admins->revoke($player);

        return back()->with('success', "{$player->username} is no longer an administrator.");
    }
}
