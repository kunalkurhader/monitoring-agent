<div id="agent-installer" data-token-endpoint="{{ route('agents.tokens.store') }}" class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
    <div class="chart-panel">
        <div class="panel-heading"><h2>Configure a Linux agent</h2><span>All fields stay in your browser</span></div>
        <div class="space-y-4">
            <label class="setup-label" for="install-api-url">{{ config('app.name', 'Monitoring Agent') }} URL<input class="setup-input mt-2" id="install-api-url" type="url" required></label>
            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <label class="setup-label" for="install-token-name">Token name<input class="setup-input mt-2" id="install-token-name" placeholder="Production web servers" maxlength="255"></label>
                <button id="generate-agent-token" type="button" class="mt-3 rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-700 dark:bg-slate-700 dark:hover:bg-slate-600">Generate 64-character token</button>
                <label class="setup-label mt-4" for="install-api-token">Agent API token<input class="setup-input mt-2 font-mono" id="install-api-token" type="text" readonly placeholder="Generate a token to continue" autocomplete="off"></label>
                <p id="token-generation-status" class="mt-2 text-xs text-slate-500">The token is shown once. Only its SHA-256 hash is stored.</p>
            </div>
            <label class="setup-label" for="install-hostname">Hostname override <span class="font-normal text-slate-500">(optional)</span><input class="setup-input mt-2" id="install-hostname" placeholder="web-prod-01"></label>
            <label class="setup-label" for="install-interval">Polling interval in seconds<input class="setup-input mt-2" id="install-interval" type="number" min="1" step="1" value="5" required></label>
            <label class="setup-label" for="install-filter">Process filter <span class="font-normal text-slate-500">(optional, comma-separated)</span><input class="setup-input mt-2" id="install-filter" placeholder="java,php,nginx"></label>
            <label class="setup-label" for="install-logs">Log files to synchronize <span class="font-normal text-slate-500">(optional, one absolute path per line)</span><textarea class="setup-input mt-2 font-mono text-xs" id="install-logs" rows="5" placeholder="/var/log/nginx/error.log&#10;/var/www/app/storage/logs/laravel.log"></textarea><span class="mt-2 block text-xs font-normal text-slate-500">Only these explicit files are read. Existing files start at their current end; newly appended content is synchronized.</span></label>
        </div>
    </div>
    <div class="chart-panel">
        <div class="panel-heading"><h2>Install command</h2><a href="{{ route('agent.download') }}" class="text-xs text-emerald-600 hover:underline">Download agent.jar directly</a></div>
        <p class="mb-3 text-sm text-slate-500">Copy this command and paste it into a systemd-based Linux server. It downloads the public JAR, validates the API token, saves a unique agent ID, installs a service, and starts monitoring.</p>
        <textarea id="install-command" readonly rows="11" class="w-full rounded-xl border border-slate-300 bg-slate-950 p-4 font-mono text-xs leading-6 text-emerald-300 dark:border-slate-700"></textarea>
        <div class="mt-3 flex flex-wrap items-center gap-3"><button id="copy-install-command" type="button" disabled class="rounded-xl bg-emerald-500 px-5 py-3 font-medium text-white enabled:hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40">Copy command</button><span id="copy-install-status" class="text-sm text-slate-500"></span></div>
        <p class="mt-4 text-xs text-amber-600 dark:text-amber-300">The command contains the API token. Treat it as a secret and clear it from shell history if required.</p>
    </div>
</div>
