import { ref } from 'vue';

type Appearance = 'light' | 'dark' | 'system';

export function updateTheme(_value: Appearance) {
    document.documentElement.classList.remove('dark');
}

export function initializeTheme() {
    document.documentElement.classList.remove('dark');
}

export function useAppearance() {
    const appearance = ref<Appearance>('light');

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        updateTheme(value);
    }

    return {
        appearance,
        updateAppearance,
    };
}
