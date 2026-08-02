const settings = document.getElementById('settings-page');

if (settings) {
    const tabs = [...settings.querySelectorAll('[data-settings-tab]')];
    const panels = [...settings.querySelectorAll('[data-settings-panel]'), document.getElementById('email-delivery')].filter(Boolean);
    const panelName = (panel) => panel.dataset.settingsPanel || panel.id;
    const validTabs = panels.map(panelName);

    const activate = (name, updateHash = false) => {
        const selected = validTabs.includes(name) ? name : validTabs[0];
        tabs.forEach((tab) => {
            const active = tab.dataset.settingsTab === selected;
            tab.setAttribute('aria-selected', String(active));
            tab.classList.toggle('bg-emerald-500', active);
            tab.classList.toggle('text-white', active);
            tab.classList.toggle('bg-white', !active);
            tab.classList.toggle('dark:bg-slate-900', !active);
        });
        panels.forEach((panel) => panel.classList.toggle('hidden', panelName(panel) !== selected));
        if (updateHash) history.replaceState(null, '', `#${selected}`);
    };

    tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.settingsTab, true)));
    window.addEventListener('hashchange', () => activate(location.hash.slice(1)));
    activate(location.hash.slice(1));
}
