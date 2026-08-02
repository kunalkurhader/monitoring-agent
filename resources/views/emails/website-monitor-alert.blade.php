<h1>{{ $monitor->name }}</h1>
<p>{{ $details['summary'] }}</p>
<p><strong>Website:</strong> <a href="{{ $monitor->url }}">{{ $monitor->url }}</a></p>
@if(isset($details['expiry']))<p><strong>Certificate expiry:</strong> {{ $details['expiry']->format('M j, Y H:i T') }}</p>@endif
<p>Checked by {{ config('app.name', 'Monitoring Agent') }} at {{ now()->format('M j, Y H:i T') }}.</p>
