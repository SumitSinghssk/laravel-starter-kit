const clone = (value) => JSON.parse(JSON.stringify(value));
let keySeed = 0;
const withKeys = (variables) => variables.map((variable) => ({ ...variable, _key: ++keySeed, _name: variable.name }));

const TAB_OF = { variables: 'colors', slots: 'slots', typography: 'fonts', style: 'style' };

export default function registerAppearance(Alpine) {
    Alpine.data('appearanceEditor', ({ state, urls, slots, canEdit, tab }) => ({
        tab,
        canEdit,
        slots,
        values: { ...clone(state.values), variables: withKeys(state.values.variables) },
        savedValues: clone(state.values),
        saved: '',
        palette: state.palette,
        hex: state.hex,
        contrast: state.contrast,
        weights: state.weights,
        errors: {},
        saving: false,
        checking: false,
        previewDark: false,
        device: 'desktop',
        lastCss: null,
        picker: { open: false, slot: null, mode: 'light', style: '' },
        seq: 0,
        timer: null,
        leaving: false,

        init() {
            this.saved = JSON.stringify(this.payload());

            this.$watch('values', () => this.schedule());
            this.$watch('previewDark', () => this.refresh());
            this.$watch('tab', (value) => {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', value);
                history.replaceState(null, '', url);
            });
            this.$watch('values.style.dark_mode', (mode) => {
                if (mode !== 'auto') this.previewDark = false;
            });
            this.$watch('values.typography.heading_font', () => this.snapWeight());

            window.addEventListener('beforeunload', (event) => {
                if (this.dirty && !this.leaving) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
            window.addEventListener('scroll', () => this.closePicker(), true);
            window.addEventListener('resize', () => this.closePicker());
        },

        payload() {
            return {
                variables: this.values.variables.map(({ name, value, shades }) => ({ name, value, shades: !!shades })),
                slots: this.values.slots,
                typography: this.values.typography,
                style: this.values.style,
            };
        },

        get dirty() {
            return JSON.stringify(this.payload()) !== this.saved;
        },

        get errorCount() {
            return Object.keys(this.errors).length;
        },

        tabErrors(name) {
            return Object.keys(this.errors).filter((key) => TAB_OF[key.split('.')[0]] === name).length;
        },

        error(path) {
            return this.errors[path] ?? '';
        },

        schedule() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.refresh(), 250);
        },

        async refresh() {
            const seq = ++this.seq;
            this.checking = true;

            try {
                const { data } = await window.axios.post(urls.check, { ...this.payload(), dark: this.previewDark });
                if (seq !== this.seq) return;

                this.palette = data.palette;
                this.hex = data.hex;
                this.contrast = data.contrast;
                this.weights = data.weights;
                this.errors = data.errors;
                this.sendCss(data.css);
            } catch {
                if (seq === this.seq) window.toast?.('error', 'The preview could not be updated. Check your connection.');
            } finally {
                if (seq === this.seq) this.checking = false;
            }
        },

        sendCss(css) {
            this.lastCss = css;
            this.$refs.frame?.contentWindow?.postMessage({ type: 'theme-css', css }, window.location.origin);
        },

        frameLoaded() {
            if (this.lastCss) this.sendCss(this.lastCss);
            else if (this.dirty || this.previewDark) this.refresh();
        },

        async save() {
            if (!this.canEdit || this.saving) return;
            this.saving = true;

            try {
                const { data } = await window.axios.put(urls.update, this.payload());
                this.savedValues = clone(data.state.values);
                this.values.variables.forEach((variable) => (variable._name = variable.name));
                this.saved = JSON.stringify(this.payload());
                this.errors = {};
                this.palette = data.state.palette;
                this.hex = data.state.hex;
                this.contrast = data.state.contrast;
                window.toast?.('success', data.message);
            } catch (error) {
                if (error.response?.status === 422) {
                    this.errors = error.response.data.errors ?? {};
                    const first = Object.keys(this.errors)[0];
                    if (first && TAB_OF[first.split('.')[0]]) this.tab = TAB_OF[first.split('.')[0]];
                    window.toast?.('error', `${this.errorCount} ${this.errorCount === 1 ? 'value needs' : 'values need'} fixing before saving.`);
                } else {
                    window.toast?.('error', error.response?.data?.message ?? 'Saving failed. Please try again.');
                }
            } finally {
                this.saving = false;
            }
        },

        discard() {
            this.values = { ...clone(this.savedValues), variables: withKeys(this.savedValues.variables) };
            this.errors = {};
            window.toast?.('info', 'Changes discarded.');
        },

        allowLeave() {
            this.leaving = true;
        },

        addVariable() {
            let n = 1;
            const taken = new Set(this.values.variables.map((variable) => variable.name));
            while (taken.has(`color-${n}`)) n++;

            this.values.variables.push({ name: `color-${n}`, value: '#6366f1', shades: false, _key: ++keySeed, _name: `color-${n}` });
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('[data-variable-name]');
                inputs[inputs.length - 1]?.select();
            });
        },

        removeVariable(index) {
            this.values.variables.splice(index, 1);
        },

        renamed(variable) {
            const from = variable._name;
            const to = variable.name.trim().toLowerCase();
            variable.name = to;

            if (from && to && from !== to) {
                const shade = new RegExp(`^${from.replace(/[-]/g, '\\-')}-(\\d+)$`);
                for (const slot of Object.values(this.values.slots)) {
                    for (const mode of ['light', 'dark']) {
                        if (slot[mode] === from) slot[mode] = to;
                        else if (slot[mode] && shade.test(slot[mode])) slot[mode] = slot[mode].replace(shade, `${to}-$1`);
                    }
                }
            }
            variable._name = to;
        },

        usedBy(name) {
            if (!name) return [];
            const shade = new RegExp(`^${name.replace(/[-]/g, '\\-')}-\\d+$`);
            return Object.entries(this.values.slots)
                .filter(([, slot]) => [slot.light, slot.dark].some((ref) => ref === name || (ref && shade.test(ref))))
                .map(([key]) => this.slots[key]?.label ?? key);
        },

        shadesOf(name) {
            return Object.keys(this.palette).filter((key) => key.startsWith(`${name}-`) && /^\d+$/.test(key.slice(name.length + 1)));
        },

        get paletteGroups() {
            return this.values.variables
                .filter((variable) => this.palette[variable.name] !== undefined)
                .map((variable) => ({ name: variable.name, shades: variable.shades ? this.shadesOf(variable.name) : [] }));
        },

        pickerHex(variable) {
            return /^#[0-9a-f]{6}$/i.test(variable.value) ? variable.value : (this.hex[variable.name] ?? '#000000');
        },

        validColor(value) {
            return typeof CSS !== 'undefined' && CSS.supports('color', value);
        },

        swatch(name) {
            return this.palette[name] ?? 'transparent';
        },

        slotValue(key, mode) {
            return this.values.slots[key]?.[mode] ?? null;
        },

        contrastFor(key) {
            const report = this.contrast[key];
            return report ? (report[this.previewDark ? 'dark' : 'light'] ?? report.light) : null;
        },

        openPicker(event, slot, mode) {
            if (!this.canEdit) return;
            if (this.picker.open && this.picker.slot === slot && this.picker.mode === mode) return this.closePicker();

            const rect = event.currentTarget.getBoundingClientRect();
            const width = Math.min(320, window.innerWidth - 16);
            const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
            const below = window.innerHeight - rect.bottom - 14;
            const above = rect.top - 14;
            const openBelow = below >= 360 || below >= above;
            const space = Math.max(160, Math.min(416, openBelow ? below : above));
            const edge = openBelow ? `top:${Math.max(8, rect.bottom + 6)}px` : `bottom:${Math.max(8, window.innerHeight - rect.top + 6)}px`;

            this.picker = { open: true, slot, mode, style: `position:fixed;left:${left}px;width:${width}px;max-height:${space}px;${edge}` };
        },

        closePicker() {
            if (this.picker.open) this.picker.open = false;
        },

        pickerOutside(event) {
            if (!this.picker.open) return;
            if (this.$refs.picker?.contains(event.target) || event.target.closest('[data-picker-trigger]')) return;
            this.closePicker();
        },

        choose(name) {
            this.values.slots[this.picker.slot][this.picker.mode] = name;
            this.closePicker();
        },

        snapWeight() {
            const available = this.weights[this.values.typography.heading_font] ?? [];
            const current = Number(this.values.typography.heading_weight);
            if (available.length && !available.includes(current)) {
                this.values.typography.heading_weight = available.reduce((best, weight) =>
                    Math.abs(weight - 700) < Math.abs(best - 700) ? weight : best,
                );
            }
        },

        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
                window.toast?.('success', `Copied ${text}`);
            } catch {
                window.toast?.('error', 'Copy failed. Select the text and copy it yourself.');
            }
        },
    }));
}
