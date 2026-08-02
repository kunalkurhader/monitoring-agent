const fleet = document.getElementById('fleet-dashboard');

if (fleet) {
    const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'})[character]);
    const value = (number) => number === null ? 'No data' : `${Number(number).toFixed(1)}%`;
    const refresh = async () => {
        try {
            const response = await fetch(fleet.dataset.endpoint, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error(`Dashboard API returned HTTP ${response.status}`);
            const payload = await response.json();
            document.getElementById('fleet-total').textContent = payload.summary.total;
            document.getElementById('fleet-healthy').textContent = payload.summary.healthy;
            document.getElementById('fleet-warnings').textContent = payload.summary.warnings;
            document.getElementById('fleet-errors').textContent = payload.summary.errors;
            document.getElementById('fleet-updated').textContent = `Updated ${new Date(payload.server_time).toLocaleTimeString()}`;
            document.getElementById('fleet-empty').classList.toggle('hidden', payload.monitors.length > 0);
            document.getElementById('fleet-content').classList.toggle('hidden', payload.monitors.length === 0);

            const colors = {healthy: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300', warning: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300', error: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300'};
            document.getElementById('fleet-monitors').innerHTML = payload.monitors.map((monitor) => `<tr><td class="py-3"><a class="font-medium text-emerald-600 hover:underline" href="${fleet.dataset.monitorUrl}?agent_id=${encodeURIComponent(monitor.id)}">${escape(monitor.hostname)}</a><p class="mt-1 text-xs text-slate-500">${escape(monitor.id)}</p></td><td><span class="rounded-full px-2.5 py-1 text-xs ${colors[monitor.status]}">${monitor.status}</span></td><td class="text-right">${value(monitor.cpu)}</td><td class="text-right">${value(monitor.memory)}</td><td class="text-right">${value(monitor.disk)}</td><td class="text-right text-slate-500">${monitor.last_seen_at ? new Date(monitor.last_seen_at).toLocaleTimeString() : 'Never'}</td></tr>`).join('');

            const issues = payload.monitors.flatMap((monitor) => monitor.issues);
            document.getElementById('fleet-issue-count').textContent = `${issues.length} active`;
            document.getElementById('fleet-issues').innerHTML = issues.map((issue) => `<div class="rounded-lg border p-3 text-sm ${issue.severity === 'error' ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-200' : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200'}"><span class="font-medium">${issue.severity === 'error' ? 'Error' : 'Warning'}:</span> ${escape(issue.message)}</div>`).join('') || '<p class="py-6 text-center text-sm text-slate-500">No active warnings or errors.</p>';

            document.getElementById('browser-total').textContent = payload.browser_summary.total;
            document.getElementById('browser-page-loads').textContent = payload.browser_summary.page_loads;
            document.getElementById('browser-requests').textContent = payload.browser_summary.requests;
            document.getElementById('browser-errors').textContent = payload.browser_summary.errors;
            document.getElementById('browser-empty').classList.toggle('hidden', payload.browser_monitors.length > 0);
            document.getElementById('browser-table').classList.toggle('hidden', payload.browser_monitors.length === 0);
            document.getElementById('browser-monitors').innerHTML = payload.browser_monitors.map((monitor) => `<tr><td class="py-3"><a class="font-medium text-emerald-600 hover:underline" href="${fleet.dataset.browserUrl}?project=${encodeURIComponent(monitor.id)}">${escape(monitor.name)}</a><p class="mt-1 text-xs text-slate-500">${escape(monitor.origin)}</p></td><td><span class="rounded-full px-2.5 py-1 text-xs ${colors[monitor.status]}">${monitor.status}</span></td><td class="text-right">${monitor.page_loads}</td><td class="text-right">${monitor.requests}</td><td class="text-right">${monitor.average_load === null ? 'No data' : `${monitor.average_load.toLocaleString()} ms`}</td><td class="text-right ${monitor.errors ? 'font-medium text-red-500' : 'text-emerald-600'}">${monitor.errors}</td><td class="text-right text-slate-500">${monitor.last_seen_at ? new Date(monitor.last_seen_at).toLocaleString() : 'Never'}</td></tr>`).join('');

            document.getElementById('uptime-total').textContent = payload.uptime_summary.total;
            document.getElementById('uptime-healthy').textContent = payload.uptime_summary.healthy;
            document.getElementById('uptime-unavailable').textContent = payload.uptime_summary.unavailable;
            document.getElementById('uptime-pending').textContent = payload.uptime_summary.pending;
            document.getElementById('uptime-ssl').textContent = payload.uptime_summary.ssl_expiring;
            document.getElementById('uptime-empty').classList.toggle('hidden', payload.uptime_monitors.length > 0);
            document.getElementById('uptime-table').classList.toggle('hidden', payload.uptime_monitors.length === 0);
            const uptimeColors = {...colors, paused: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'};
            const uptimeLabels = {healthy: 'Operational', error: 'Unavailable', warning: 'Pending', paused: 'Paused'};
            document.getElementById('uptime-monitors').innerHTML = payload.uptime_monitors.map((monitor) => `<tr><td class="py-3"><a class="font-medium text-emerald-600 hover:underline" href="${fleet.dataset.uptimeUrl}">${escape(monitor.name)}</a><p class="mt-1 max-w-md truncate text-xs text-slate-500">${escape(monitor.url)}</p></td><td><span class="rounded-full px-2.5 py-1 text-xs ${uptimeColors[monitor.status]}">${uptimeLabels[monitor.status]}</span></td><td class="text-right">${monitor.status_code === null ? '—' : monitor.status_code}</td><td class="text-right">${monitor.response_ms === null ? '—' : `${monitor.response_ms} ms`}</td><td class="text-right ${monitor.ssl_days !== null && monitor.ssl_days <= 30 ? 'text-amber-600' : ''}">${monitor.ssl_days === null ? '—' : (monitor.ssl_days < 0 ? `Expired ${Math.abs(monitor.ssl_days)}d ago` : monitor.ssl_days === 0 ? 'Expires today' : `${monitor.ssl_days}d left`)}</td><td class="text-right text-slate-500">${monitor.last_checked_at ? new Date(monitor.last_checked_at).toLocaleString() : 'Never'}</td></tr>`).join('');
            document.getElementById('fleet-error').classList.add('hidden');
        } catch (error) {
            document.getElementById('fleet-error').textContent = error.message;
            document.getElementById('fleet-error').classList.remove('hidden');
        }
    };
    refresh();
    setInterval(refresh, 10000);
}
