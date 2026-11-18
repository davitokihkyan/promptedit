export type Appearance = 'light' | 'dark' | 'system';

// Read by the inline script in app.blade.php too, so the right theme applies before the page paints.
const STORAGE_KEY = 'appearance';

// One shared instance: read for the current OS setting and listened to for changes.
const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)');

/** The saved choice; "system" when nothing (or something unknown) is saved. */
export function getAppearance(): Appearance {
    const saved = localStorage.getItem(STORAGE_KEY);

    return saved === 'light' || saved === 'dark' ? saved : 'system';
}

export function setAppearance(appearance: Appearance): void {
    localStorage.setItem(STORAGE_KEY, appearance);
    applyTheme();
}

/** Applies the saved choice, and keeps following the OS while it's "system". */
export function initializeTheme(): void {
    applyTheme();
    systemPrefersDark.addEventListener('change', applyTheme);
}

function applyTheme(): void {
    const appearance = getAppearance();
    const dark =
        appearance === 'dark' ||
        (appearance === 'system' && systemPrefersDark.matches);

    document.documentElement.classList.toggle('dark', dark);
}
