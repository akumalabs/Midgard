import { defineStore } from 'pinia';
import { useDark, useToggle } from '@vueuse/core';

export const useThemeStore = defineStore('theme', () => {
    // isDark handles local storage and system preference automatically
    const isDark = useDark({
        selector: 'html',
        attribute: 'class',
        valueDark: 'dark',
        valueLight: '',
    });

    const toggleDark = useToggle(isDark);

    return {
        isDark,
        toggleDark,
    };
});
