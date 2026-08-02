const installer = document.getElementById('agent-installer');
const installCommand = document.getElementById('install-command');

if (installer && installCommand) {
    let downloadUrl = '';
    let expiresAt = null;
    let countdownTimer = null;
    let buildStarted = false;
    const shellQuote = (value) => `'${String(value).replaceAll("'", `'"'"'`)}'`;
    const update = () => {
        const installerUrl = new URL('/install-agent.sh', window.location.origin).toString();
        const apiUrl = document.getElementById('install-api-url').value.trim();
        const token = document.getElementById('install-api-token').value;
        const hostname = document.getElementById('install-hostname').value.trim();
        const interval = document.getElementById('install-interval').value || '5';
        const filter = document.getElementById('install-filter').value.trim();
        const logs = document.getElementById('install-logs').value.split(/\r?\n/).map((path) => path.trim()).filter(Boolean);
        const options = [`--download-url ${shellQuote(downloadUrl || 'BUILD_AGENT_JAR_FIRST')}`, `--expires-at ${shellQuote(expiresAt?.toISOString() || 'BUILD_AGENT_JAR_FIRST')}`, `--url ${shellQuote(apiUrl)}`, `--token ${shellQuote(token || 'GENERATE_TOKEN_FIRST')}`, `--interval ${shellQuote(interval)}`];
        if (hostname) options.push(`--name ${shellQuote(hostname)}`);
        if (filter) options.push(`--filter ${shellQuote(filter)}`);
        logs.forEach((path) => options.push(`--log ${shellQuote(path)}`));
        const remaining = expiresAt ? Math.max(0, Math.ceil((expiresAt.getTime() - Date.now()) / 1000)) : 0;
        const validity = expiresAt ? `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}` : 'not built';
        installCommand.value = [
            `# Temporary agent JAR: ${validity} remaining; expires ${expiresAt ? expiresAt.toLocaleString() : 'after a fresh build'}`,
            `curl -fsSL ${shellQuote(installerUrl)} -o /tmp/monitoring-agent-install.sh && \\`,
            'sudo bash /tmp/monitoring-agent-install.sh \\',
            `  ${options.join(' \\\n  ')}`,
        ].join('\n');
        document.getElementById('copy-install-command').disabled = token.length < 56 || !downloadUrl;
        document.getElementById('copy-install-status').textContent = '';
    };

    const buildAgent = async () => {
        const button = document.getElementById('build-agent-jar');
        const status = document.getElementById('agent-build-status');
        const link = document.getElementById('download-agent-jar');
        button.disabled = true;
        status.textContent = 'Building the latest Java agent… This can take a minute.';
        status.className = 'text-sm text-amber-600 dark:text-amber-300';
        document.getElementById('agent-build-countdown').textContent = 'Build in progress — the 10-minute window starts when complete';
        link.classList.add('hidden');
        downloadUrl = '';
        expiresAt = null;
        window.clearInterval(countdownTimer);
        update();
        try {
            const response = await fetch(installer.dataset.buildEndpoint, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Agent build failed.');
            downloadUrl = payload.download_url;
            link.href = downloadUrl;
            link.classList.remove('hidden');
            expiresAt = new Date(payload.expires_at);
            status.textContent = payload.message;
            status.className = 'text-sm text-emerald-600 dark:text-emerald-300';
            const countdown = document.getElementById('agent-build-countdown');
            const syncCountdown = () => {
                const remaining = Math.max(0, Math.ceil((expiresAt.getTime() - Date.now()) / 1000));
                const minutes = String(Math.floor(remaining / 60)).padStart(2, '0');
                const seconds = String(remaining % 60).padStart(2, '0');
                countdown.textContent = `Temporary JAR and install command expire in ${minutes}:${seconds}`;
                update();
                if (remaining === 0 && downloadUrl === payload.download_url) {
                    downloadUrl = '';
                    expiresAt = null;
                    link.classList.add('hidden');
                    status.textContent = 'This JAR has expired. Build a fresh copy to continue.';
                    status.className = 'text-sm text-amber-600 dark:text-amber-300';
                    countdown.textContent = 'Expired — rebuild required';
                    window.clearInterval(countdownTimer);
                    update();
                }
            };
            syncCountdown();
            countdownTimer = window.setInterval(syncCountdown, 1000);
        } catch (error) {
            status.textContent = error.message;
            status.className = 'text-sm text-red-500';
            document.getElementById('agent-build-countdown').textContent = 'Build failed — no temporary command is available';
        } finally {
            button.disabled = false;
        }
    };

    document.getElementById('install-api-url').value = window.location.origin;
    ['install-api-url', 'install-api-token', 'install-hostname', 'install-interval', 'install-filter', 'install-logs'].forEach((id) => document.getElementById(id).addEventListener('input', update));
    document.getElementById('generate-agent-token').addEventListener('click', async () => {
        const name = document.getElementById('install-token-name').value.trim();
        const status = document.getElementById('token-generation-status');
        if (!name) {
            status.textContent = 'Enter a descriptive token name first.';
            status.className = 'mt-2 text-xs text-red-500';
            return;
        }

        const button = document.getElementById('generate-agent-token');
        button.disabled = true;
        status.textContent = 'Generating token…';
        try {
            const response = await fetch(installer.dataset.tokenEndpoint, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({name}),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Token creation failed.');
            document.getElementById('install-api-token').value = payload.token;
            status.textContent = `${payload.message} Save the generated command now.`;
            status.className = 'mt-2 text-xs text-emerald-600 dark:text-emerald-300';
            update();
        } catch (error) {
            status.textContent = error.message;
            status.className = 'mt-2 text-xs text-red-500';
        } finally {
            button.disabled = false;
        }
    });
    document.getElementById('build-agent-jar').addEventListener('click', buildAgent);
    window.addEventListener('settings:tab-activated', (event) => {
        if (event.detail === 'server-agent' && !buildStarted) {
            buildStarted = true;
            buildAgent();
        }
    });
    document.getElementById('copy-install-command').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(installCommand.value);
        } catch {
            installCommand.select();
            document.execCommand('copy');
        }
        document.getElementById('copy-install-status').textContent = 'Copied to clipboard.';
    });
    update();
}
