<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Device;
use App\Platform\Districts;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', ['avatars' => User::AVATARS, 'districts' => Districts::options()]);
    }

    public function store(Request $request, Device $device): RedirectResponse
    {
        // One account per device at a time: switching needs a sign-out first.
        if ($device->signedInUserId($request) !== null) {
            throw ValidationException::withMessages(['username' => Device::BUSY_MESSAGE]);
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:24', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'avatar' => ['nullable', Rule::in(User::AVATARS)],
            'district' => ['required', Rule::in(Districts::keys())],
        ], [
            'username.regex' => 'Usernames may only contain letters, numbers and underscores.',
        ]);

        $user = User::create($data);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $device->claim($request);

        return redirect()->intended('/')->with('success', "Welcome to MeepleMania, {$user->username}!");
    }
}
