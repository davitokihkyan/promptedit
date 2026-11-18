/** Follows the operating system's light/dark setting, including changes while the page is open. */
export function initializeTheme(): void {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)');
    const apply = () =>
        document.documentElement.classList.toggle('dark', prefersDark.matches);

    apply();
    prefersDark.addEventListener('change', apply);
}
