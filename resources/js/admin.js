import './bootstrap';

import Alpine from 'alpinejs';
import registerAppearance from './admin/appearance';
import registerAdminForms from './admin/forms';
import registerGallery from './admin/gallery';
import registerMediaLibrary from './admin/media-library';
import registerMenus from './admin/menus';

window.Alpine = Alpine;

Alpine.data('imageUpload', (config) => ({
    preview: config.current || '',
    hasFile: false,
    removed: false,
    error: '',
    objectUrl: null,

    pick() {
        this.$refs.input.click();
    },

    onFileChange(event) {
        const file = event.target.files[0];
        this.error = '';

        if (!file) {
            return;
        }

        if (!file.type.startsWith('image/')) {
            this.reset('Please choose an image file (JPG, PNG, WebP or GIF).');
            return;
        }

        if (file.size > config.maxKb * 1024) {
            this.reset(`This image is larger than ${config.maxKb / 1024} MB. Please choose a smaller file.`);
            return;
        }

        this.revoke();
        this.objectUrl = URL.createObjectURL(file);
        this.preview = this.objectUrl;
        this.hasFile = true;
        this.removed = false;
    },

    remove() {
        this.reset();
        this.preview = '';
        this.removed = true;
    },

    reset(error = '') {
        this.$refs.input.value = '';
        this.revoke();
        this.hasFile = false;
        this.preview = this.removed ? '' : config.current || '';
        this.error = error;
    },

    revoke() {
        if (this.objectUrl) {
            URL.revokeObjectURL(this.objectUrl);
            this.objectUrl = null;
        }
    },
}));

const adminThemeKey = 'admin-theme';
const systemDarkMode = window.matchMedia('(prefers-color-scheme: dark)');

function getSavedTheme() {
    const theme = localStorage.getItem(adminThemeKey);

    return theme === 'dark' || theme === 'light' ? theme : systemDarkMode.matches ? 'dark' : 'light';
}

function applyTheme(theme) {
    const isDark = theme === 'dark';

    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = theme;
    window.dispatchEvent(new CustomEvent('admin-theme-changed', { detail: { theme, isDark } }));

    return isDark;
}

Alpine.store('notif', {
    open: false,

    toggle() {
        this.open = !this.open;
    },

    close() {
        this.open = false;
    },

    openSheet() {
        this.open = true;
    },
});

Alpine.store('theme', {
    theme: getSavedTheme(),
    isDark: false,

    init() {
        this.isDark = applyTheme(this.theme);

        systemDarkMode.addEventListener('change', (event) => {
            if (localStorage.getItem(adminThemeKey)) {
                return;
            }

            this.theme = event.matches ? 'dark' : 'light';
            this.isDark = applyTheme(this.theme);
        });
    },

    toggle() {
        this.theme = this.isDark ? 'light' : 'dark';
        localStorage.setItem(adminThemeKey, this.theme);
        this.isDark = applyTheme(this.theme);
    },
});

registerAdminForms(Alpine);
registerGallery(Alpine);
registerMediaLibrary(Alpine);
registerAppearance(Alpine);
registerMenus(Alpine);

Alpine.start();
