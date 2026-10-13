<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\AdminActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private AdminActivity $activity) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login', ['status' => session('status')]);
    }

    public function createAdmin(): Response
    {
        return Inertia::render('Admin/Login', ['status' => session('status')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authenticate($request, adminArea: false);

        return redirect()->intended('/');
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        $user = $this->authenticate($request, adminArea: true);

        if (! $user->isAdmin()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->activity->record('login_denied', "Non-admin account {$user->username} tried the admin sign-in");

            throw ValidationException::withMessages(['login' => 'This account does not have admin access.']);
        }

        return redirect()->intended('/admin');
    }

    /**
     * Admin sign-ins are audited: successes, failures on the admin page, and
     * failures against any admin account wherever they happen.
     */
    private function authenticate(Request $request, bool $adminArea): User
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        // One field accepts either the username or the email address.
        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $credentials = [$field => $data['login'], 'password' => $data['password']];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $target = User::where($field, $data['login'])->first();

            if ($adminArea || $target?->isAdmin()) {
                $this->activity->record('login_failed', "Failed sign-in for “{$data['login']}”".($adminArea ? ' on the admin page' : ''), $target?->isAdmin() ? $target : null);
            }

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        $user = $request->user();

        if ($user->isDisabled()) {
            Auth::guard('web')->logout();

            if ($user->isAdmin()) {
                $this->activity->record('login_failed', 'Deactivated admin account tried to sign in', $user);
            }

            throw ValidationException::withMessages(['login' => 'This account has been disabled.']);
        }

        $request->session()->regenerate();

        if ($user->isAdmin()) {
            $this->activity->record('login', $adminArea ? 'Signed in on the admin page' : 'Signed in on the player page', $user);
        }

        return $user;
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            $this->activity->record('logout', 'Signed out', $request->user());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
