const dashboard = document.getElementById('monitor-dashboard');

if (dashboard && document.getElementById('agent-select')) {
    const agentSelect = document.getElementById('agent-select');
    const rangeSelect = document.getElementById('range-select');
    let requestController;
    let latestSeries = [];
    let processTimer;
    let storageTimer;
    let selectedProcessIndex = null;
    let selectedStorageIndex = null;

    const bytes = (value) => {
        if (!value) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
        return `${(value / (1024 ** exponent)).toFixed(exponent > 2 ? 1 : 0)} ${units[exponent]}`;
    };

    const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);

    const tabs = [...dashboard.querySelectorAll('[data-dashboard-tab]')];
    const panels = [...dashboard.querySelectorAll('[data-dashboard-panel]')];
    const selectTab = (name) => {
        tabs.forEach((tab) => {
            const selected = tab.dataset.dashboardTab === name;
            tab.classList.toggle('border-emerald-500', selected);
            tab.classList.toggle('text-emerald-700', selected);
            tab.classList.toggle('dark:text-emerald-300', selected);
            tab.classList.toggle('border-transparent', !selected);
            tab.classList.toggle('text-slate-500', !selected);
        });
        panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.dashboardPanel !== name));
        if (name === 'processes' && latestSeries.length) loadProcessesAt(selectedProcessIndex ?? latestSeries.length - 1);
        if (name === 'storage' && latestSeries.length) loadStorageAt(selectedStorageIndex ?? latestSeries.length - 1);
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => selectTab(tab.dataset.dashboardTab)));

    const drawChart = (canvas, series, key, color) => {
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.parentElement.clientWidth;
        const height = canvas.parentElement.clientHeight;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        const context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        context.clearRect(0, 0, width, height);

        const left = 38, right = 12, top = 12, bottom = 25;
        const plotWidth = width - left - right, plotHeight = height - top - bottom;
        context.font = '11px system-ui';
        const dark = document.documentElement.classList.contains('dark');
        context.fillStyle = dark ? '#94a3b8' : '#64748b';
        context.strokeStyle = dark ? '#273449' : '#e2e8f0';
        context.lineWidth = 1;
        [0, 25, 50, 75, 100].forEach((tick) => {
            const y = top + plotHeight - (tick / 100) * plotHeight;
            context.beginPath(); context.moveTo(left, y); context.lineTo(width - right, y); context.stroke();
            context.fillText(`${tick}%`, 2, y + 4);
        });

        if (!series.length) return;
        context.beginPath();
        series.forEach((point, index) => {
            const x = left + (index / Math.max(series.length - 1, 1)) * plotWidth;
            const y = top + plotHeight - (Math.max(0, Math.min(100, point[key])) / 100) * plotHeight;
            index === 0 ? context.moveTo(x, y) : context.lineTo(x, y);
        });
        context.strokeStyle = color;
        context.lineWidth = 2;
        context.stroke();
        context.fillStyle = dark ? '#94a3b8' : '#64748b';
        context.fillText(new Date(series[0].time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}), left, height - 5);
        const end = new Date(series.at(-1).time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        context.fillText(end, width - right - context.measureText(end).width, height - 5);
    };

    const renderStorage = (disks, sampledAt) => {
        document.getElementById('storage-synced').textContent = sampledAt ? `Snapshot ${new Date(sampledAt).toLocaleString()}` : 'No samples';
        document.getElementById('historical-storage-time').textContent = sampledAt ? new Date(sampledAt).toLocaleString() : 'No storage snapshot';
        document.getElementById('storage-list').innerHTML = disks.map((disk) => {
            const percent = disk.total_bytes > 0 ? Math.min(100, (disk.used_bytes / disk.total_bytes) * 100) : 0;
            return `<article class="rounded-xl border border-slate-200 p-4 dark:border-slate-800"><div class="flex items-start justify-between gap-3"><div><h3 class="font-medium text-slate-800 dark:text-slate-100">${escape(disk.mount_point)}</h3><p class="mt-1 text-xs text-slate-500">${escape(disk.device || 'Unknown device')} · ${escape(disk.file_system_type || 'filesystem')}</p></div><strong class="text-lg">${percent.toFixed(1)}%</strong></div><div class="mt-4 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"><div class="h-full rounded-full ${percent >= 90 ? 'bg-red-400' : percent >= 75 ? 'bg-amber-400' : 'bg-emerald-400'}" style="width:${percent.toFixed(2)}%"></div></div><dl class="mt-4 grid grid-cols-3 gap-3 text-xs"><div><dt class="text-slate-500">Used</dt><dd class="mt-1 font-medium">${bytes(disk.used_bytes)}</dd></div><div><dt class="text-slate-500">Free</dt><dd class="mt-1 font-medium">${bytes(disk.free_bytes)}</dd></div><div><dt class="text-slate-500">Total</dt><dd class="mt-1 font-medium">${bytes(disk.total_bytes)}</dd></div></dl></article>`;
        }).join('') || '<p class="text-sm text-slate-500">No storage volumes available at this time.</p>';
    };

    const render = (payload) => {
        const series = payload.series;
        latestSeries = series;
        const peak = series.length ? Math.max(...series.map((point) => point.cpu)) : 0;
        document.getElementById('cpu-now').textContent = `${payload.current.cpu.toFixed(1)}%`;
        document.getElementById('cpu-peak').textContent = `Peak ${peak.toFixed(1)}% in range`;
        document.getElementById('memory-now').textContent = bytes(payload.current.used_memory);
        document.getElementById('memory-total').textContent = `of ${bytes(payload.current.total_memory)}`;
        document.getElementById('process-count').textContent = payload.current.process_count;
        document.getElementById('last-sample').textContent = series.length ? new Date(series.at(-1).time).toLocaleTimeString() : 'No data';
        document.getElementById('connection-status').textContent = `Online · updated ${new Date(payload.server_time).toLocaleTimeString()}`;
        document.getElementById('status-dot').className = 'size-2 rounded-full bg-emerald-400';

        drawChart(document.getElementById('cpu-chart'), series, 'cpu', '#34d399');
        drawChart(document.getElementById('memory-chart'), series, 'memory', '#818cf8');

        document.getElementById('process-table').innerHTML = payload.processes.map((process) => `<tr><td class="py-3 text-slate-800 dark:text-slate-200">${escape(process.process_name || 'Unknown')}</td><td class="text-slate-500 dark:text-slate-400">${process.pid}</td><td class="text-right">${Number(process.cpu_usage).toFixed(1)}%</td><td class="text-right">${bytes(process.memory_bytes)}</td><td class="text-right text-slate-500 dark:text-slate-400">${escape(process.state || '—')}</td></tr>`).join('') || '<tr><td colspan="5" class="py-6 text-center text-slate-500">No process samples available.</td></tr>';

        document.getElementById('disk-synced').textContent = payload.disk_synced_at ? `Synced ${new Date(payload.disk_synced_at).toLocaleTimeString()}` : 'No samples';
        document.getElementById('disk-list').innerHTML = payload.disks.map((disk) => {
            const percent = disk.total_bytes > 0 ? Math.min(100, (disk.used_bytes / disk.total_bytes) * 100) : 0;
            return `<div><div class="mb-2 flex justify-between gap-3 text-xs"><span class="truncate text-slate-700 dark:text-slate-300">${escape(disk.mount_point)} · ${escape(disk.file_system_type || 'filesystem')}</span><span class="shrink-0 text-slate-500">${bytes(disk.used_bytes)} / ${bytes(disk.total_bytes)}</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800"><div class="h-full rounded-full ${percent >= 90 ? 'bg-red-400' : 'bg-emerald-400'}" style="width:${percent.toFixed(2)}%"></div></div></div>`;
        }).join('') || '<p class="text-sm text-slate-500">No disk samples available.</p>';

        if (selectedStorageIndex === null) renderStorage(payload.disks, payload.disk_synced_at);

        const slider = document.getElementById('process-time-slider');
        slider.max = Math.max(0, series.length - 1);
        if (selectedProcessIndex === null || selectedProcessIndex > series.length - 1) selectedProcessIndex = Math.max(0, series.length - 1);
        slider.value = selectedProcessIndex;
        document.getElementById('process-range-start').textContent = series.length ? new Date(series[0].time).toLocaleString() : '—';
        document.getElementById('process-range-end').textContent = series.length ? new Date(series.at(-1).time).toLocaleString() : '—';
        const storageSlider = document.getElementById('storage-time-slider');
        storageSlider.max = Math.max(0, series.length - 1);
        if (selectedStorageIndex === null || selectedStorageIndex > series.length - 1) selectedStorageIndex = Math.max(0, series.length - 1);
        storageSlider.value = selectedStorageIndex;
        document.getElementById('storage-range-start').textContent = series.length ? new Date(series[0].time).toLocaleString() : '—';
        document.getElementById('storage-range-end').textContent = series.length ? new Date(series.at(-1).time).toLocaleString() : '—';

        const heatmap = payload.process_heatmap;
        const maximum = Math.max(1, ...heatmap.rows.flatMap((row) => row.values));
        const firstLabel = heatmap.labels[0] ? new Date(heatmap.labels[0]).toLocaleString([], {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'}) : '';
        const middleLabel = heatmap.labels.length ? new Date(heatmap.labels[Math.floor(heatmap.labels.length / 2)]).toLocaleString([], {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'}) : '';
        const lastLabel = heatmap.labels.length ? new Date(heatmap.labels.at(-1)).toLocaleString([], {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'}) : '';
        document.getElementById('process-heatmap').innerHTML = heatmap.rows.length ? `
            <div class="min-w-[760px]">
                <div class="mb-2 grid grid-cols-[130px_repeat(24,minmax(18px,1fr))] gap-1 text-[10px] text-slate-500">
                    <span></span><span class="col-span-8">${escape(firstLabel)}</span><span class="col-span-8 text-center">${escape(middleLabel)}</span><span class="col-span-8 text-right">${escape(lastLabel)}</span>
                </div>
                <div class="space-y-1">${heatmap.rows.map((row) => `<div class="grid grid-cols-[130px_repeat(24,minmax(18px,1fr))] gap-1"><span class="truncate pr-2 text-xs text-slate-700 dark:text-slate-300">${escape(row.name)}</span>${row.values.map((value) => { const intensity = value === 0 ? 0.05 : 0.18 + ((value / maximum) * 0.82); return `<span class="h-6 rounded-sm" style="background-color:rgba(16,185,129,${intensity.toFixed(2)})" title="${escape(row.name)}: ${Number(value).toFixed(1)}% CPU"></span>`; }).join('')}</div>`).join('')}</div>
                <div class="mt-3 flex justify-end gap-2 text-[10px] text-slate-500"><span>Low</span><span class="h-3 w-16 rounded-sm bg-gradient-to-r from-emerald-100 to-emerald-500 dark:from-emerald-950 dark:to-emerald-400"></span><span>High</span></div>
            </div>` : '<p class="py-6 text-center text-sm text-slate-500">No process history available for this range.</p>';
    };

    const loadProcessesAt = async (index) => {
        if (!latestSeries.length) return;
        selectedProcessIndex = Math.max(0, Math.min(Number(index), latestSeries.length - 1));
        const selected = latestSeries[selectedProcessIndex];
        document.getElementById('process-time-slider').value = selectedProcessIndex;
        document.getElementById('historical-process-time').textContent = new Date(selected.time).toLocaleString();

        try {
            const query = new URLSearchParams({agent_id: agentSelect.value, at: selected.time});
            const response = await fetch(`${dashboard.dataset.processesEndpoint}?${query}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error(`Process history API returned HTTP ${response.status}`);
            const payload = await response.json();
            document.getElementById('historical-process-count').textContent = `${payload.processes.length} processes`;
            if (payload.sampled_at) document.getElementById('historical-process-time').textContent = new Date(payload.sampled_at).toLocaleString();
            document.getElementById('historical-process-table').innerHTML = payload.processes.map((process) => `<tr><td class="py-3 font-medium text-slate-800 dark:text-slate-200">${escape(process.process_name || 'Unknown')}</td><td>${process.pid}</td><td>${escape(process.user_name || '—')}</td><td class="max-w-sm truncate" title="${escape(process.command || '')}">${escape(process.command || '—')}</td><td class="text-right">${Number(process.cpu_usage).toFixed(1)}%</td><td class="text-right">${bytes(process.memory_bytes)}</td><td class="text-right">${escape(process.state || '—')}</td><td class="text-right">${process.start_time ? new Date(Number(process.start_time)).toLocaleString() : '—'}</td></tr>`).join('') || '<tr><td colspan="8" class="py-8 text-center text-slate-500">No process snapshot exists at this time.</td></tr>';
        } catch (exception) {
            document.getElementById('historical-process-table').innerHTML = `<tr><td colspan="8" class="py-8 text-center text-red-500">${escape(exception.message)}</td></tr>`;
        }
    };

    const loadStorageAt = async (index) => {
        if (!latestSeries.length) return;
        selectedStorageIndex = Math.max(0, Math.min(Number(index), latestSeries.length - 1));
        const selected = latestSeries[selectedStorageIndex];
        document.getElementById('storage-time-slider').value = selectedStorageIndex;
        document.getElementById('historical-storage-time').textContent = new Date(selected.time).toLocaleString();

        try {
            const query = new URLSearchParams({agent_id: agentSelect.value, at: selected.time});
            const response = await fetch(`${dashboard.dataset.storageEndpoint}?${query}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error(`Storage history API returned HTTP ${response.status}`);
            const payload = await response.json();
            renderStorage(payload.disks, payload.sampled_at);
        } catch (exception) {
            document.getElementById('storage-list').innerHTML = `<p class="text-sm text-red-500">${escape(exception.message)}</p>`;
        }
    };

    const refresh = async () => {
        requestController?.abort();
        requestController = new AbortController();
        const error = document.getElementById('dashboard-error');
        try {
            const query = new URLSearchParams({agent_id: agentSelect.value, range: rangeSelect.value});
            const response = await fetch(`${dashboard.dataset.endpoint}?${query}`, {headers: {'Accept': 'application/json'}, signal: requestController.signal});
            if (!response.ok) throw new Error(`Dashboard API returned HTTP ${response.status}`);
            render(await response.json());
            error.classList.add('hidden');
        } catch (exception) {
            if (exception.name === 'AbortError') return;
            error.textContent = exception.message;
            error.classList.remove('hidden');
            document.getElementById('connection-status').textContent = 'Refresh failed';
            document.getElementById('status-dot').className = 'size-2 rounded-full bg-red-400';
        }
    };

    document.getElementById('process-time-slider').addEventListener('input', (event) => {
        clearTimeout(processTimer);
        const index = Number(event.target.value);
        selectedProcessIndex = index;
        if (latestSeries[index]) document.getElementById('historical-process-time').textContent = new Date(latestSeries[index].time).toLocaleString();
        processTimer = setTimeout(() => loadProcessesAt(index), 200);
    });
    document.getElementById('storage-time-slider').addEventListener('input', (event) => {
        clearTimeout(storageTimer);
        const index = Number(event.target.value);
        selectedStorageIndex = index;
        if (latestSeries[index]) document.getElementById('historical-storage-time').textContent = new Date(latestSeries[index].time).toLocaleString();
        storageTimer = setTimeout(() => loadStorageAt(index), 200);
    });
    agentSelect.addEventListener('change', () => { selectedProcessIndex = null; selectedStorageIndex = null; refresh(); });
    rangeSelect.addEventListener('change', () => { selectedProcessIndex = null; selectedStorageIndex = null; refresh(); });
    window.addEventListener('resize', () => refresh());
    window.addEventListener('pulsewatch:theme-changed', refresh);
    refresh();
    setInterval(refresh, 5000);
}
