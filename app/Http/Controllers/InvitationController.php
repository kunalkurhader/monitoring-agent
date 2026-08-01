<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function show(string $token): View
    {
        return view('invitations.accept', ['invitation' => $this->validInvitation($token), 'token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->validInvitation($token);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        DB::transaction(function () use ($invitation, $validated): void {
            User::query()->create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'is_admin' => $invitation->role === 'admin',
                'email_verified_at' => now(),
            ]);
            $invitation->update(['accepted_at' => now()]);
        });

        return redirect()->route('login')->with('status', 'Your account is ready. Sign in with your new password.');
    }

    private function validInvitation(string $token): TeamInvitation
    {
        $invitation = TeamInvitation::query()->where('token_hash', hash('sha256', $token))->first();

        abort_if(! $invitation || $invitation->accepted_at || $invitation->expires_at->isPast(), 404, 'This invitation is invalid or has expired.');

        return $invitation;
    }
}
