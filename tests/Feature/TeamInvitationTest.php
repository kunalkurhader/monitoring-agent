<?php

namespace Tests\Feature;

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_members_can_view_team_but_only_admins_can_invite(): void
    {
        $member = User::factory()->create(['is_admin' => false]);

        $this->actingAs($member)->get(route('team.index'))->assertOk()->assertSee($member->email);
        $this->actingAs($member)->post(route('team.invitations.store'), [
            'email' => 'coworker@example.com',
            'role' => 'member',
        ])->assertForbidden();
    }

    public function test_admin_can_invite_another_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('team.invitations.store'), [
            'email' => 'new-admin@example.com',
            'role' => 'admin',
        ])->assertRedirect();

        $invitation = TeamInvitation::query()->firstOrFail();
        $this->assertSame('new-admin@example.com', $invitation->email);
        $this->assertSame('admin', $invitation->role);
        $this->assertSame(64, strlen($invitation->token_hash));
    }

    public function test_recipient_can_accept_valid_invitation_and_set_password(): void
    {
        $token = 'valid-invitation-token';
        $invitation = TeamInvitation::query()->create([
            'email' => 'invitee@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'invited_by' => User::factory()->create(['is_admin' => true])->id,
            'expires_at' => now()->addDay(),
        ]);

        $this->get(route('invitations.accept', $token))->assertOk()->assertSee('invitee@example.com');
        $this->post(route('invitations.store', $token), [
            'name' => 'Invited User',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseHas('users', ['email' => 'invitee@example.com', 'is_admin' => false]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
        $this->get(route('invitations.accept', $token))->assertNotFound();
    }

    public function test_expired_invitation_is_rejected(): void
    {
        $token = 'expired-token';
        TeamInvitation::query()->create([
            'email' => 'expired@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('invitations.accept', $token))->assertNotFound();
    }

    public function test_final_administrator_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch(route('team.users.role', $admin), ['role' => 'member'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_admin);
    }
}
