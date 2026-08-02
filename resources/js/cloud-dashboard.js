document.querySelectorAll('[data-cloud-detail-tabs]').forEach((navigation) => {
    const dashboard = navigation.closest('#cloud-instance-dashboard, #cloud-database-dashboard');
    const tabs = [...navigation.querySelectorAll('[data-cloud-detail-tab]')];
    const panels = [...dashboard.querySelectorAll('[data-cloud-detail-panel]')];
    const activate = (name) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.cloudDetailTab === name;
            tab.classList.toggle('border-emerald-500', active);
            tab.classList.toggle('text-emerald-700', active);
            tab.classList.toggle('dark:text-emerald-300', active);
            tab.classList.toggle('border-transparent', !active);
            tab.classList.toggle('text-slate-500', !active);
        });
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.cloudDetailPanel !== name));
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.cloudDetailTab)));
});

const cloudDashboard = document.getElementById('cloud-instance-dashboard');

if (cloudDashboard && document.getElementById('cloud-resource-select')) {
    const resourceSelect = document.getElementById('cloud-resource-select');
    const rangeSelect = document.getElementById('cloud-range-select');
    const requested = new URLSearchParams(window.location.search).get('resource_id');
    if (requested && [...resourceSelect.options].some((option) => option.value === requested)) resourceSelect.value = requested;
    let latestPayload;
    let requestController;

    const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);

    const bytes = (value) => {
        const number = Number(value || 0);
        if (!number) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const exponent = Math.min(Math.floor(Math.log(number) / Math.log(1024)), units.length - 1);
        return `${(number / (1024 ** exponent)).toFixed(exponent > 1 ? 1 : 0)} ${units[exponent]}`;
    };

    const drawChart = (canvas, series, definitions, percent = false) => {
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.parentElement.clientWidth;
        const height = canvas.parentElement.clientHeight;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        const context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        context.clearRect(0, 0, width, height);
        const left = 44, right = 12, top = 14, bottom = 25;
        const plotWidth = width - left - right, plotHeight = height - top - bottom;
        const maximum = percent ? 100 : Math.max(1, ...series.flatMap((point) => definitions.map((item) => Number(point[item.key] || 0))));
        const dark = document.documentElement.classList.contains('dark');
        context.font = '11px system-ui';
        context.fillStyle = dark ? '#94a3b8' : '#64748b';
        context.strokeStyle = dark ? '#273449' : '#e2e8f0';
        context.lineWidth = 1;
        [0, .25, .5, .75, 1].forEach((part) => {
            const y = top + plotHeight - (part * plotHeight);
            context.beginPath(); context.moveTo(left, y); context.lineTo(width - right, y); context.stroke();
            const value = maximum * part;
            context.fillText(percent ? `${Math.round(value)}%` : bytes(value), 2, y + 4);
        });
        if (!series.length) return;
        definitions.forEach((definition) => {
            context.beginPath();
            series.forEach((point, index) => {
                const x = left + (index / Math.max(series.length - 1, 1)) * plotWidth;
                const y = top + plotHeight - (Math.min(maximum, Number(point[definition.key] || 0)) / maximum) * plotHeight;
                index === 0 ? context.moveTo(x, y) : context.lineTo(x, y);
            });
            context.strokeStyle = definition.color;
            context.lineWidth = 2;
            context.stroke();
        });
        context.fillStyle = dark ? '#94a3b8' : '#64748b';
        const first = new Date(series[0].time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        const last = new Date(series.at(-1).time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        context.fillText(first, left, height - 5);
        context.fillText(last, width - right - context.measureText(last).width, height - 5);
        definitions.forEach((definition, index) => {
            context.fillStyle = definition.color;
            context.fillRect(left + (index * 100), 3, 8, 3);
            context.fillStyle = dark ? '#cbd5e1' : '#475569';
            context.fillText(definition.label, left + 12 + (index * 100), 8);
        });
    };

    const render = (payload) => {
        latestPayload = payload;
        const series = payload.series;
        const hasData = series.length > 0;
        document.getElementById('cloud-no-data').classList.toggle('hidden', hasData);
        document.getElementById('cloud-telemetry').classList.toggle('hidden', !hasData);
        document.getElementById('cloud-status-dot').className = `size-2 rounded-full ${payload.resource.state === 'running' ? 'bg-emerald-400' : 'bg-slate-400'}`;
        document.getElementById('cloud-status').textContent = `${payload.resource.state || 'unknown'} · ${payload.resource.region}`;
        if (!hasData) return;
        const current = payload.current;
        document.getElementById('cloud-cpu-now').textContent = `${Number(current.cpu).toFixed(1)}%`;
        document.getElementById('cloud-cpu-peak').textContent = `Peak ${Math.max(...series.map((point) => point.cpu)).toFixed(1)}% in range`;
        document.getElementById('cloud-memory-used').textContent = payload.memory_used_percent === null ? 'Not reported' : `${Number(payload.memory_used_percent).toFixed(1)}%`;
        document.getElementById('cloud-network-in').textContent = bytes(current.network_in);
        document.getElementById('cloud-network-out').textContent = bytes(current.network_out);
        document.getElementById('cloud-instance-state').textContent = payload.resource.state || 'unknown';
        document.getElementById('cloud-last-sample').textContent = `Last sample ${new Date(current.time).toLocaleTimeString()}`;
        document.getElementById('cloud-instance-id').textContent = payload.resource.resource_id;
        document.getElementById('cloud-account').textContent = payload.resource.connection.name;
        document.getElementById('cloud-region').textContent = payload.resource.region;
        document.getElementById('cloud-zone').textContent = payload.resource.availability_zone || '—';
        document.getElementById('cloud-type').textContent = payload.resource.instance_type || '—';
        document.getElementById('cloud-private-ip').textContent = payload.resource.metadata?.private_ip || '—';
        document.getElementById('cloud-platform').textContent = payload.resource.metadata?.platform || '—';
        document.getElementById('cloud-filesystem-summary').textContent = payload.filesystems.length ? `${payload.filesystems.length} mounted ${payload.filesystems.length === 1 ? 'filesystem' : 'filesystems'}` : 'CWAgent data unavailable';
        document.getElementById('cloud-filesystem-list').innerHTML = payload.filesystems.map((filesystem) => {
            const percent = filesystem.used_percent || (filesystem.total_bytes > 0 ? (filesystem.used_bytes / filesystem.total_bytes) * 100 : 0);
            const usageColor = percent >= 90 ? 'bg-red-400' : percent >= 75 ? 'bg-amber-400' : 'bg-emerald-400';
            return `<article class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"><div class="flex items-start justify-between gap-3"><div><h3 class="font-medium">${escape(filesystem.path)}</h3><p class="mt-1 text-xs text-slate-500">${escape(filesystem.device || 'Unknown device')} · ${escape(filesystem.filesystem_type || 'filesystem')}</p></div><strong class="text-lg">${Number(percent).toFixed(1)}%</strong></div><div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"><div class="h-full rounded-full ${usageColor}" style="width:${Math.min(100, percent).toFixed(2)}%"></div></div><dl class="mt-4 grid grid-cols-3 gap-3 text-xs"><div><dt class="text-slate-500">Used</dt><dd class="mt-1 font-medium">${bytes(filesystem.used_bytes)}</dd></div><div><dt class="text-slate-500">Free</dt><dd class="mt-1 font-medium">${bytes(filesystem.free_bytes)}</dd></div><div><dt class="text-slate-500">Total</dt><dd class="mt-1 font-medium">${bytes(filesystem.total_bytes)}</dd></div></dl>${filesystem.free_inodes === null ? '' : `<p class="mt-3 text-xs text-slate-500">${Number(filesystem.free_inodes).toLocaleString()} free inodes</p>`}</article>`;
        }).join('') || '<div class="rounded-xl border border-dashed border-slate-300 p-5 text-sm text-slate-500 dark:border-slate-700"><p class="font-medium text-slate-700 dark:text-slate-300">Filesystem metrics are not being reported</p><p class="mt-1">Install and configure the CloudWatch Agent with disk metrics on this instance.</p></div>';
        const totalGiB = payload.volumes.reduce((total, volume) => total + Number(volume.metadata?.size_gib || 0), 0);
        document.getElementById('cloud-volume-summary').textContent = `${payload.volumes.length} ${payload.volumes.length === 1 ? 'volume' : 'volumes'} · ${totalGiB} GiB provisioned`;
        document.getElementById('cloud-storage-summary').textContent = `${payload.filesystems.length} mounts · ${payload.volumes.length} EBS volumes · ${totalGiB} GiB provisioned`;
        document.getElementById('cloud-volume-list').innerHTML = payload.volumes.map((volume) => {
            const metadata = volume.metadata || {};
            const performance = [metadata.iops ? `${Number(metadata.iops).toLocaleString()} IOPS` : null, metadata.throughput_mibps ? `${metadata.throughput_mibps} MiB/s` : null].filter(Boolean).join(' · ');
            return `<article class="grid gap-2 py-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto_auto_auto] sm:items-center sm:gap-5"><div class="min-w-0"><h4 class="truncate font-medium">${escape(volume.name || volume.resource_id)}</h4><p class="mt-0.5 truncate font-mono text-xs text-slate-500">${escape(volume.resource_id)} · ${escape(metadata.device || 'device unknown')}</p></div><div><span class="text-xs text-slate-500">Capacity</span><p class="font-medium">${Number(metadata.size_gib || 0).toLocaleString()} GiB · ${escape(metadata.volume_type || 'EBS')}</p></div><div><span class="text-xs text-slate-500">Performance</span><p class="font-medium">${performance ? escape(performance) : 'Standard'}</p></div><div class="flex items-center gap-2 sm:justify-end"><span class="rounded-full bg-emerald-100 px-2 py-1 text-xs text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">${escape(volume.state || 'unknown')}</span><span class="text-xs text-slate-500">${metadata.encrypted ? 'Encrypted' : 'Not encrypted'}</span></div></article>`;
        }).join('') || '<p class="py-3 text-sm text-slate-500">No attached EBS volumes were returned for this instance.</p>';
        drawChart(document.getElementById('cloud-cpu-chart'), series, [{key: 'cpu', label: 'CPU', color: '#34d399'}], true);
        drawChart(document.getElementById('cloud-network-chart'), series, [{key: 'network_in', label: 'Inbound', color: '#34d399'}, {key: 'network_out', label: 'Outbound', color: '#818cf8'}]);
        drawChart(document.getElementById('cloud-disk-chart'), series, [{key: 'disk_read', label: 'Read', color: '#38bdf8'}, {key: 'disk_write', label: 'Write', color: '#f59e0b'}]);
        drawChart(document.getElementById('cloud-status-chart'), series, [{key: 'status_failed', label: 'Failed', color: '#fb7185'}]);
    };

    const refresh = async () => {
        requestController?.abort();
        requestController = new AbortController();
        const error = document.getElementById('cloud-error');
        try {
            const query = new URLSearchParams({resource_id: resourceSelect.value, range: rangeSelect.value});
            const response = await fetch(`${cloudDashboard.dataset.endpoint}?${query}`, {headers: {'Accept': 'application/json'}, signal: requestController.signal});
            if (!response.ok) throw new Error(`Cloud dashboard API returned HTTP ${response.status}`);
            render(await response.json());
            error.classList.add('hidden');
        } catch (exception) {
            if (exception.name === 'AbortError') return;
            error.textContent = exception.message;
            error.classList.remove('hidden');
            document.getElementById('cloud-status').textContent = 'Refresh failed';
            document.getElementById('cloud-status-dot').className = 'size-2 rounded-full bg-red-400';
        }
    };

    resourceSelect.addEventListener('change', refresh);
    rangeSelect.addEventListener('change', refresh);
    window.addEventListener('resize', () => { if (latestPayload) render(latestPayload); });
    window.addEventListener('monitoring-agent:theme-changed', () => { if (latestPayload) render(latestPayload); });
    refresh();
    setInterval(refresh, 60000);
}

const databaseDashboard = document.getElementById('cloud-database-dashboard');

if (databaseDashboard && document.getElementById('cloud-database-select')) {
    const databaseSelect = document.getElementById('cloud-database-select');
    const rangeSelect = document.getElementById('cloud-database-range');
    const requested = new URLSearchParams(window.location.search).get('resource_id');
    if (requested && [...databaseSelect.options].some((option) => option.value === requested)) databaseSelect.value = requested;
    let latestPayload;
    let requestController;
    const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'})[character]);
    const bytes = (value) => {
        const number = Number(value || 0);
        if (!number) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const exponent = Math.min(Math.floor(Math.log(number) / Math.log(1024)), units.length - 1);
        return `${(number / (1024 ** exponent)).toFixed(exponent > 1 ? 1 : 0)} ${units[exponent]}`;
    };
    const draw = (canvas, series, definitions, ceiling = null) => {
        const ratio = window.devicePixelRatio || 1, width = canvas.parentElement.clientWidth, height = canvas.parentElement.clientHeight;
        canvas.width = width * ratio; canvas.height = height * ratio;
        const context = canvas.getContext('2d'); context.scale(ratio, ratio); context.clearRect(0, 0, width, height);
        const left = 44, right = 12, top = 14, bottom = 25, plotWidth = width - left - right, plotHeight = height - top - bottom;
        const maximum = ceiling || Math.max(1, ...series.flatMap((point) => definitions.map((definition) => Number(point[definition.key] || 0))));
        const dark = document.documentElement.classList.contains('dark');
        context.font = '11px system-ui'; context.fillStyle = dark ? '#94a3b8' : '#64748b'; context.strokeStyle = dark ? '#273449' : '#e2e8f0';
        [0, .25, .5, .75, 1].forEach((part) => { const y = top + plotHeight - part * plotHeight; context.beginPath(); context.moveTo(left, y); context.lineTo(width - right, y); context.stroke(); context.fillText((maximum * part).toFixed(maximum < 10 ? 1 : 0), 2, y + 4); });
        definitions.forEach((definition, definitionIndex) => {
            context.beginPath(); series.forEach((point, index) => { const x = left + (index / Math.max(series.length - 1, 1)) * plotWidth; const y = top + plotHeight - (Math.min(maximum, Number(point[definition.key] || 0)) / maximum) * plotHeight; index ? context.lineTo(x, y) : context.moveTo(x, y); }); context.strokeStyle = definition.color; context.lineWidth = 2; context.stroke();
            context.fillStyle = definition.color; context.fillRect(left + definitionIndex * 100, 3, 8, 3); context.fillStyle = dark ? '#cbd5e1' : '#475569'; context.fillText(definition.label, left + 12 + definitionIndex * 100, 8);
        });
        if (series.length) { context.fillStyle = dark ? '#94a3b8' : '#64748b'; const first = new Date(series[0].time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}), last = new Date(series.at(-1).time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}); context.fillText(first, left, height - 5); context.fillText(last, width - right - context.measureText(last).width, height - 5); }
    };
    const render = (payload) => {
        latestPayload = payload;
        const series = payload.series, hasData = series.length > 0, resource = payload.resource, metadata = resource.metadata || {};
        document.getElementById('cloud-database-no-data').classList.toggle('hidden', hasData);
        document.getElementById('cloud-database-telemetry').classList.toggle('hidden', !hasData);
        document.getElementById('cloud-database-dot').className = `size-2 rounded-full ${resource.state === 'available' ? 'bg-emerald-400' : 'bg-amber-400'}`;
        document.getElementById('cloud-database-status').textContent = `${resource.state || 'unknown'} · ${resource.region}`;
        if (!hasData) return;
        const current = payload.current, provisionedBytes = Number(metadata.allocated_storage_gib || 0) * 1073741824;
        document.getElementById('rds-cpu').textContent = `${Number(current.cpu).toFixed(1)}%`;
        document.getElementById('rds-cpu-peak').textContent = `Peak ${Math.max(...series.map((point) => point.cpu)).toFixed(1)}%`;
        document.getElementById('rds-connections').textContent = Math.round(current.connections).toLocaleString();
        document.getElementById('rds-memory').textContent = bytes(current.free_memory);
        document.getElementById('rds-storage').textContent = bytes(current.free_storage);
        document.getElementById('rds-storage-percent').textContent = provisionedBytes ? `${((current.free_storage / provisionedBytes) * 100).toFixed(1)}% of ${metadata.allocated_storage_gib} GiB free` : 'Free capacity';
        document.getElementById('rds-latency').textContent = `${Number(current.read_latency_ms).toFixed(1)} / ${Number(current.write_latency_ms).toFixed(1)} ms`;
        document.getElementById('rds-resource-id').textContent = resource.resource_id;
        document.getElementById('rds-engine').textContent = `${metadata.engine || 'RDS'} ${metadata.engine_version || ''}`;
        document.getElementById('rds-class').textContent = resource.instance_type || '—';
        document.getElementById('rds-region').textContent = `${resource.region} / ${resource.availability_zone || '—'}`;
        document.getElementById('rds-provisioned').textContent = `${metadata.allocated_storage_gib || '—'} GiB · ${metadata.storage_type || 'storage'}`;
        document.getElementById('rds-multi-az').textContent = metadata.multi_az ? 'Multi-AZ' : 'Single-AZ';
        document.getElementById('rds-endpoint').textContent = metadata.endpoint ? `${metadata.endpoint}:${metadata.port || ''}` : '—';
        draw(document.getElementById('rds-cpu-chart'), series, [{key: 'cpu', label: 'CPU', color: '#34d399'}], 100);
        draw(document.getElementById('rds-connections-chart'), series, [{key: 'connections', label: 'Connections', color: '#818cf8'}]);
        draw(document.getElementById('rds-latency-chart'), series, [{key: 'read_latency_ms', label: 'Read', color: '#38bdf8'}, {key: 'write_latency_ms', label: 'Write', color: '#f59e0b'}]);
        draw(document.getElementById('rds-iops-chart'), series, [{key: 'read_iops', label: 'Read', color: '#38bdf8'}, {key: 'write_iops', label: 'Write', color: '#f59e0b'}]);
        document.getElementById('rds-query-window').textContent = payload.query_window_ended_at ? `Window ending ${new Date(payload.query_window_ended_at).toLocaleString()}` : 'Performance Insights unavailable';
        document.getElementById('rds-query-table').innerHTML = payload.queries.map((query) => `<tr><td class="max-w-2xl py-3"><p class="truncate font-mono text-xs" title="${escape(query.query_text || '')}">${escape(query.query_text || query.query_id)}</p><p class="mt-1 text-xs text-slate-500">${escape(query.query_id)}</p></td><td class="text-right font-medium">${Number(query.db_load).toFixed(2)}</td><td class="text-right">${query.average_latency_ms === null ? 'Unavailable' : `${Number(query.average_latency_ms).toFixed(2)} ms`}</td><td class="text-right">${query.calls_per_second === null ? 'Unavailable' : Number(query.calls_per_second).toFixed(2)}</td></tr>`).join('') || '<tr><td colspan="4" class="py-8 text-center text-slate-500">No query insights are available. Enable RDS Performance Insights and grant the monitoring role PI read permissions.</td></tr>';
    };
    const refresh = async () => {
        requestController?.abort(); requestController = new AbortController(); const error = document.getElementById('cloud-database-error');
        try { const query = new URLSearchParams({resource_id: databaseSelect.value, range: rangeSelect.value}); const response = await fetch(`${databaseDashboard.dataset.endpoint}?${query}`, {headers: {'Accept': 'application/json'}, signal: requestController.signal}); if (!response.ok) throw new Error(`RDS dashboard API returned HTTP ${response.status}`); render(await response.json()); error.classList.add('hidden'); }
        catch (exception) { if (exception.name === 'AbortError') return; error.textContent = exception.message; error.classList.remove('hidden'); document.getElementById('cloud-database-status').textContent = 'Refresh failed'; }
    };
    databaseSelect.addEventListener('change', refresh); rangeSelect.addEventListener('change', refresh);
    window.addEventListener('resize', () => { if (latestPayload) render(latestPayload); }); window.addEventListener('monitoring-agent:theme-changed', () => { if (latestPayload) render(latestPayload); });
    refresh(); setInterval(refresh, 60000);
}
