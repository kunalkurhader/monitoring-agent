<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $changes = ['name' => $validated['name']];
        if (! empty($validated['password'])) {
            $changes['password'] = $validated['password'];
        }
        $request->user()->update($changes);

        return back()->with('status', 'Profile updated.');
    }
}
