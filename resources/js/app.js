import './dashboard';

const syncThemeControls = () => {
    const dark = document.documentElement.classList.contains('dark');

    document.querySelectorAll('[data-theme-light]').forEach((element) => {
        element.classList.toggle('hidden', dark);
    });
    document.querySelectorAll('[data-theme-dark]').forEach((element) => {
        element.classList.toggle('hidden', !dark);
    });
};

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');
        document.documentElement.classList.toggle('scheme-dark', dark);
        document.documentElement.classList.toggle('scheme-light', !dark);
        localStorage.setItem('pulsewatch-theme', dark ? 'dark' : 'light');
        syncThemeControls();
    });
});

syncThemeControls();
