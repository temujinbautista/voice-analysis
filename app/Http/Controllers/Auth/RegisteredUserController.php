<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Only this account is allowed to create additional user accounts.
     */
    private const ALLOWED_EMAIL = 'temujinbautista@gmail.com';

    public function create(Request $request): Response
    {
        abort_unless($request->user()?->email === self::ALLOWED_EMAIL, 403);

        return Inertia::render('auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->email === self::ALLOWED_EMAIL, 403);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('dashboard');
    }
}
