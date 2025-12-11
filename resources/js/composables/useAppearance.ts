export function updateTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Always use light mode - remove dark class if present
    document.documentElement.classList.remove('dark');
}

export function initializeTheme() {
    if (typeof window === 'undefined') {
        return;
    }

    // Always initialize to light mode
    updateTheme();
}

// Keep the composable for backwards compatibility but always return light
export function useAppearance() {
    return {
        appearance: { value: 'light' },
        updateAppearance: () => {
            // No-op: theme is always light
            updateTheme();
        },
    };
}
