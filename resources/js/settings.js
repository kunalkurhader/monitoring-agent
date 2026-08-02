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
            const danger = tab.dataset.settingsTab === 'danger-zone';
            tab.setAttribute('aria-selected', String(active));
            tab.classList.toggle('bg-emerald-500', active && !danger);
            tab.classList.toggle('bg-red-600', active && danger);
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

    const resetForm = settings.querySelector('[data-factory-reset-form]');
    if (resetForm) {
        const password = resetForm.querySelector('[data-reset-password]');
        const confirmation = resetForm.querySelector('[data-reset-confirmation]');
        const submit = resetForm.querySelector('[data-reset-submit]');
        const status = resetForm.querySelector('[data-reset-ready]');
        const syncResetState = () => {
            const ready = password.value.length > 0 && confirmation.value === 'ERASE EVERYTHING';
            submit.disabled = !ready;
            status.textContent = ready
                ? 'Confirmation complete. Review the warning before continuing.'
                : 'Enter your password and the exact confirmation phrase to unlock reset.';
            status.classList.toggle('text-red-600', ready);
            status.classList.toggle('dark:text-red-300', ready);
        };

        password.addEventListener('input', syncResetState);
        confirmation.addEventListener('input', syncResetState);
        syncResetState();
    }
}
