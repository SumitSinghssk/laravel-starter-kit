export default function registerEmailTemplates(Alpine) {
    Alpine.data('emailTemplates', (config) => ({
        templates: config.templates,
        current: null,
        values: {},
        saved: {},
        enabled: true,
        activeField: 'body',
        subject: '',
        html: '',
        unknown: [],
        loading: false,
        failed: false,
        device: 'desktop',
        testOpen: false,
        testEmail: config.testEmail,
        testing: false,
        testResult: null,
        submitting: false,
        timer: null,
        requestId: 0,
        rootEl: null,

        init() {
            this.rootEl = this.$el;
            const start = config.initial && this.templates[config.initial] ? config.initial : null;
            if (start) this.open(start, config.old, false);

            this.$watch('values', () => this.schedulePreview(), { deep: true });
            this.$watch('device', () =>
                setTimeout(() => {
                    const frame = this.rootEl.querySelector('iframe');
                    if (frame) this.fit(frame);
                }, 350),
            );

            window.addEventListener('beforeunload', (event) => {
                if (this.isDirty() && !this.submitting && !window.skipUnsavedCheck) event.preventDefault();
            });
        },

        get template() {
            return this.current ? this.templates[this.current] : null;
        },

        get groups() {
            return Object.entries(config.groups).map(([id, label]) => ({
                id,
                label,
                items: Object.values(this.templates).filter((item) => item.group === id),
            }));
        },

        has(field) {
            return this.template?.fields.includes(field) ?? false;
        },

        open(key, old = null, scroll = true) {
            if (
                this.current &&
                this.current !== key &&
                this.isDirty() &&
                !window.confirm('You have unsaved changes. Leave this email without saving?')
            ) {
                return;
            }

            const template = this.templates[key];
            this.current = key;
            this.saved = { ...template.values };
            this.values = { ...template.values, ...(old?.values ?? {}) };
            this.enabled = old ? old.enabled : template.enabled;
            this.activeField = template.fields.includes('body') ? 'body' : template.fields[0];
            this.testOpen = false;
            this.testResult = null;
            this.html = '';
            this.remember(key);
            this.preview();

            if (scroll) this.$nextTick(() => this.rootEl.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },

        close() {
            if (this.isDirty() && !window.confirm('You have unsaved changes. Leave this email without saving?')) return;

            this.current = null;
            this.remember(null);
        },

        remember(key) {
            const url = new URL(window.location);
            if (key) url.searchParams.set('template', key);
            else url.searchParams.delete('template');
            window.history.replaceState({}, '', url);
        },

        isDirty() {
            if (!this.template) return false;

            return JSON.stringify(this.values) !== JSON.stringify(this.saved) || this.enabled !== this.template.enabled;
        },

        isDefault() {
            return JSON.stringify(this.values) === JSON.stringify(this.template.defaults);
        },

        url(pattern) {
            return pattern.replace('__KEY__', this.current);
        },

        insert(name) {
            const field = this.template.fields.includes(this.activeField) ? this.activeField : 'body';
            const input = this.rootEl.querySelector(`[data-field="${field}"]`);
            const token = `{${name}}`;
            const value = this.values[field] ?? '';

            if (!input) {
                this.values[field] = value + token;
                return;
            }

            const start = input.selectionStart ?? value.length;
            const end = input.selectionEnd ?? value.length;
            this.values[field] = value.slice(0, start) + token + value.slice(end);

            this.$nextTick(() => {
                input.focus();
                input.setSelectionRange(start + token.length, start + token.length);
            });
        },

        useDefault(field) {
            this.values[field] = this.template.defaults[field];
        },

        schedulePreview() {
            if (!this.current) return;

            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.preview(), 350);
        },

        async preview() {
            const id = ++this.requestId;
            this.loading = true;

            try {
                const { data } = await window.axios.post(this.url(config.previewUrl), this.values);
                if (id !== this.requestId) return;

                this.subject = data.subject;
                this.unknown = data.unknown;
                this.html = '<base target="_blank">' + data.html;
                this.failed = false;
            } catch {
                if (id === this.requestId) this.failed = true;
            } finally {
                if (id === this.requestId) this.loading = false;
            }
        },

        fit(frame) {
            try {
                frame.style.height = '0px';
                frame.style.height = frame.contentDocument.documentElement.scrollHeight + 'px';
            } catch {}
        },

        async sendTest() {
            this.testing = true;
            this.testResult = null;

            try {
                const { data } = await window.axios.post(this.url(config.testUrl), { ...this.values, test_email: this.testEmail });
                this.testResult = data;
            } catch (error) {
                const response = error.response;

                if (response?.status === 429) {
                    this.testResult = { ok: false, message: 'Too many tests in a row. Wait a minute and try again.' };
                } else if (response?.data?.errors) {
                    this.testResult = { ok: false, message: Object.values(response.data.errors)[0][0] };
                } else {
                    this.testResult = {
                        ok: false,
                        message: response?.data?.message ?? 'The test could not be sent. Check your connection and try again.',
                    };
                }
            } finally {
                this.testing = false;
            }
        },
    }));
}
