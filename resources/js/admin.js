import './bootstrap';

import Alpine from 'alpinejs';
import registerAppearance from './admin/appearance';
import registerCommandPalette from './admin/command-palette';
import registerEmailTemplates from './admin/email-templates';
import registerAdminForms from './admin/forms';
import registerGallery from './admin/gallery';
import registerIdle from './admin/idle';
import registerMediaLibrary from './admin/media-library';
import registerMenus from './admin/menus';

window.Alpine = Alpine;

window.copyText = async (text) => {
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch {}
    }

    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.appendChild(area);
    area.select();
    const copied = document.execCommand('copy');
    area.remove();

    return copied;
};

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
registerCommandPalette(Alpine);
registerEmailTemplates(Alpine);
registerMenus(Alpine);
registerIdle(Alpine);

Alpine.start();
