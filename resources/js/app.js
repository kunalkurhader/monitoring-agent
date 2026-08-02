import './dashboard';
import './agent-installer';
import './fleet-dashboard';
import './settings';

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
        localStorage.setItem('monitoring-agent-theme', dark ? 'dark' : 'light');
        syncThemeControls();
        window.dispatchEvent(new CustomEvent('monitoring-agent:theme-changed'));
    });
});

syncThemeControls();

const mobileMenuToggle = document.querySelector('[data-mobile-menu-toggle]');
const mobileMenuPanel = document.querySelector('[data-mobile-menu-panel]');

if (mobileMenuToggle && mobileMenuPanel) {
    const setMobileMenu = (open) => {
        mobileMenuPanel.classList.toggle('hidden', !open);
        mobileMenuPanel.classList.toggle('mobile-menu-enter', open);
        mobileMenuToggle.setAttribute('aria-expanded', String(open));
        mobileMenuToggle.querySelector('[data-mobile-menu-open]')?.classList.toggle('hidden', open);
        mobileMenuToggle.querySelector('[data-mobile-menu-close]')?.classList.toggle('hidden', !open);
    };

    mobileMenuToggle.addEventListener('click', () => {
        setMobileMenu(mobileMenuToggle.getAttribute('aria-expanded') !== 'true');
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setMobileMenu(false);
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) setMobileMenu(false);
    });
}
