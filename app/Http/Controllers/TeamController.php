<?php

namespace App\Http\Controllers;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function index(): View
    {
        return view('team.index', [
            'members' => User::query()->orderByDesc('is_admin')->orderBy('name')->get(),
            'invitations' => TeamInvitation::query()->whereNull('accepted_at')->with('inviter')->latest()->get(),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['member', 'admin'])],
        ]);
        $email = Str::lower($validated['email']);
        $token = Str::random(64);

        $invitation = TeamInvitation::query()->updateOrCreate(
            ['email' => $email, 'accepted_at' => null],
            [
                'role' => $validated['role'],
                'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ],
        );

        $acceptUrl = route('invitations.accept', ['token' => $token]);
        Mail::send('emails.team-invitation', compact('invitation', 'acceptUrl'), function ($message) use ($email): void {
            $message->to($email)->subject('You are invited to '.config('app.name', 'Monitoring Agent'));
        });

        return back()->with('status', "Invitation sent to {$email}.");
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::in(['member', 'admin'])]]);
        $makeAdmin = $validated['role'] === 'admin';

        if ($user->is_admin && ! $makeAdmin && User::query()->where('is_admin', true)->count() === 1) {
            return back()->withErrors(['role' => 'The final administrator cannot be changed to a member.']);
        }

        $user->update(['is_admin' => $makeAdmin]);

        return back()->with('status', "{$user->email} is now a {$validated['role']}.");
    }
}
