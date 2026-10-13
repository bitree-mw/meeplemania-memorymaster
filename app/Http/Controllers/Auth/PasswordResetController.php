<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\AdminActivity;
use App\Platform\PasswordResetLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetLinks $links) {}

    public function request(): Response
    {
        return Inertia::render('Auth/ForgotPassword', ['status' => session('status')]);
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:255']]);

        $field = str_contains($data['login'], '@') ? 'email' : 'username';
        $user = User::where($field, $data['login'])->first();

        if ($user) {
            $this->links->send($user);
        }

        // Same response whether or not the account exists, to avoid account enumeration.
        return back()->with('status', 'If that account exists, a reset link is on its way to its email address.');
    }

    public function reset(string $username, string $token): Response
    {
        $link = $this->links->find($username, $token);

        return Inertia::render('Auth/ResetPassword', [
            'username' => $username,
            'token' => $token,
            'valid' => $link !== null,
            'expires_at' => $link?->expires_at->toIso8601String(),
        ]);
    }

    public function update(Request $request, AdminActivity $activity): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:24'],
            'token' => ['required', 'string', 'max:64'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $link = $this->links->find($data['username'], $data['token']);

        if (! $link) {
            throw ValidationException::withMessages(['token' => 'This reset link is invalid, has already been used or has expired. Request a new one.']);
        }

        $user = $this->links->reset($link, $data['password']);

        if ($user->isAdmin()) {
            $activity->record('password_reset', 'Changed password with a reset link', $user);
        }

        return redirect($user->isAdmin() ? '/admin/login' : '/login')
            ->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
