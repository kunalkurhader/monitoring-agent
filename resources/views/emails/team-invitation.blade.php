<h1>You are invited to {{ config('app.name', 'Monitoring Agent') }}</h1>
<p>{{ $invitation->inviter?->name ?? 'An administrator' }} invited you to join their monitoring dashboard as {{ $invitation->role === 'admin' ? 'an administrator' : 'a member' }}.</p>
<p><a href="{{ $acceptUrl }}">Accept invitation and set your password</a></p>
<p>This link expires on {{ $invitation->expires_at->format('M j, Y \a\t H:i T') }}. If you were not expecting this invitation, you can ignore this email.</p>
