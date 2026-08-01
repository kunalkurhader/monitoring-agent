const dashboard = document.getElementById('monitor-dashboard');

if (dashboard && document.getElementById('agent-select')) {
    const agentSelect = document.getElementById('agent-select');
    const rangeSelect = document.getElementById('range-select');
    let requestController;

    const bytes = (value) => {
        if (!value) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const exponent = Math.min(Math.floor(Math.log(value) / Math.log(1024)), units.length - 1);
        return `${(value / (1024 ** exponent)).toFixed(exponent > 2 ? 1 : 0)} ${units[exponent]}`;
    };

    const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);

    const drawChart = (canvas, series, key, color) => {
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        canvas.width = width * ratio;
        canvas.height = height * ratio;
        const context = canvas.getContext('2d');
        context.scale(ratio, ratio);
        context.clearRect(0, 0, width, height);

        const left = 38, right = 12, top = 12, bottom = 25;
        const plotWidth = width - left - right, plotHeight = height - top - bottom;
        context.font = '11px system-ui';
        context.fillStyle = '#64748b';
        context.strokeStyle = '#273449';
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
        context.fillStyle = '#64748b';
        context.fillText(new Date(series[0].time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'}), left, height - 5);
        const end = new Date(series.at(-1).time).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
        context.fillText(end, width - right - context.measureText(end).width, height - 5);
    };

    const render = (payload) => {
        const series = payload.series;
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

        document.getElementById('process-table').innerHTML = payload.processes.map((process) => `<tr><td class="py-3 text-slate-200">${escape(process.process_name || 'Unknown')}</td><td class="text-slate-400">${process.pid}</td><td class="text-right">${Number(process.cpu_usage).toFixed(1)}%</td><td class="text-right">${bytes(process.memory_bytes)}</td><td class="text-right text-slate-400">${escape(process.state || '—')}</td></tr>`).join('') || '<tr><td colspan="5" class="py-6 text-center text-slate-500">No process samples available.</td></tr>';

        document.getElementById('disk-synced').textContent = payload.disk_synced_at ? `Synced ${new Date(payload.disk_synced_at).toLocaleTimeString()}` : 'No samples';
        document.getElementById('disk-list').innerHTML = payload.disks.map((disk) => {
            const percent = disk.total_bytes > 0 ? Math.min(100, (disk.used_bytes / disk.total_bytes) * 100) : 0;
            return `<div><div class="mb-2 flex justify-between gap-3 text-xs"><span class="truncate text-slate-300">${escape(disk.mount_point)} · ${escape(disk.file_system_type || 'filesystem')}</span><span class="shrink-0 text-slate-500">${bytes(disk.used_bytes)} / ${bytes(disk.total_bytes)}</span></div><div class="h-2 overflow-hidden rounded-full bg-slate-800"><div class="h-full rounded-full ${percent >= 90 ? 'bg-red-400' : 'bg-emerald-400'}" style="width:${percent.toFixed(2)}%"></div></div></div>`;
        }).join('') || '<p class="text-sm text-slate-500">No disk samples available.</p>';
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

    agentSelect.addEventListener('change', refresh);
    rangeSelect.addEventListener('change', refresh);
    window.addEventListener('resize', () => refresh());
    refresh();
    setInterval(refresh, 5000);
}
