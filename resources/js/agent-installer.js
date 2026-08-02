const installer = document.getElementById('agent-installer');
const installCommand = document.getElementById('install-command');

if (installer && installCommand) {
    const shellQuote = (value) => `'${String(value).replaceAll("'", `'"'"'`)}'`;
    const update = () => {
        const installerUrl = new URL('/install-agent.sh', window.location.origin).toString();
        const downloadUrl = new URL('/downloads/agent.jar', window.location.origin).toString();
        const apiUrl = document.getElementById('install-api-url').value.trim();
        const token = document.getElementById('install-api-token').value;
        const hostname = document.getElementById('install-hostname').value.trim();
        const interval = document.getElementById('install-interval').value || '5';
        const filter = document.getElementById('install-filter').value.trim();
        const logs = document.getElementById('install-logs').value.split(/\r?\n/).map((path) => path.trim()).filter(Boolean);
        const options = [`--download-url ${shellQuote(downloadUrl)}`, `--url ${shellQuote(apiUrl)}`, `--token ${shellQuote(token || 'GENERATE_TOKEN_FIRST')}`, `--interval ${shellQuote(interval)}`];
        if (hostname) options.push(`--name ${shellQuote(hostname)}`);
        if (filter) options.push(`--filter ${shellQuote(filter)}`);
        logs.forEach((path) => options.push(`--log ${shellQuote(path)}`));
        installCommand.value = [
            `curl -fsSL ${shellQuote(installerUrl)} -o /tmp/monitoring-agent-install.sh && \\`,
            'sudo bash /tmp/monitoring-agent-install.sh \\',
            `  ${options.join(' \\\n  ')}`,
        ].join('\n');
        document.getElementById('copy-install-command').disabled = token.length < 56;
        document.getElementById('copy-install-status').textContent = '';
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
