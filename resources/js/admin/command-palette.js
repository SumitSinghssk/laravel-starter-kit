const RECENT_KEY = 'admin-search-recent';
const RECENT_LIMIT = 6;
const STATIC_LIMIT = 8;

const escapeHtml = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);

const escapeRegex = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const readRecent = () => {
    try {
        const list = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        return Array.isArray(list) ? list : [];
    } catch {
        return [];
    }
};

const writeRecent = (list) => {
    try {
        localStorage.setItem(RECENT_KEY, JSON.stringify(list));
    } catch {}
};

export default function registerCommandPalette(Alpine) {
    Alpine.data('commandPalette', (config) => ({
        open: false,
        query: '',
        active: 0,
        items: config.items,
        icons: config.icons,
        groups: [],
        searchedFor: '',
        loading: false,
        failed: false,
        recent: [],
        timer: null,
        controller: null,

        init() {
            this.recent = readRecent();
            this.$watch('query', () => {
                this.active = 0;
                this.schedule();
            });
        },

        get terms() {
            return this.query.trim().toLowerCase().split(/\s+/).filter(Boolean);
        },

        get staticResults() {
            const terms = this.terms;
            if (!terms.length) return this.items;

            return this.items
                .map((item) => {
                    const title = item.title.toLowerCase();
                    const haystack = `${title} ${item.hint} ${item.group} ${item.keywords ?? ''}`.toLowerCase();
                    if (!terms.every((term) => haystack.includes(term))) return null;

                    const score = title.startsWith(terms[0]) ? 0 : title.includes(terms[0]) ? 1 : 2;
                    return { item, score };
                })
                .filter(Boolean)
                .sort((a, b) => a.score - b.score)
                .slice(0, STATIC_LIMIT)
                .map(({ item }) => item);
        },

        get sections() {
            const sections = [];
            const q = this.query.trim();

            if (!q && this.recent.length) {
                sections.push({ key: 'recent', label: 'Recent', items: this.recent.map((item) => ({ ...item, kind: 'recent' })), clearable: true });
            }

            if (q && this.showGroups) {
                this.groups.forEach((group) => {
                    const items = group.items.map((item) => ({ ...item, icon: group.icon, group: group.label, kind: 'record' }));
                    if (group.more) {
                        items.push({
                            id: `${group.key}:more`,
                            title: `See all ${group.total} in ${group.label}`,
                            url: group.more,
                            icon: 'arrow-right',
                            kind: 'more',
                        });
                    }
                    sections.push({ key: group.key, label: group.label, total: group.total, items });
                });
            }

            const byGroup = {};
            this.staticResults.forEach((item) => {
                const label = q ? (item.group === 'Go to' ? 'Pages' : item.group) : item.group;
                (byGroup[label] ??= []).push({ ...item, id: `${item.group}:${item.url}`, kind: 'static' });
            });
            Object.entries(byGroup).forEach(([label, items]) => sections.push({ key: `static:${label}`, label, items }));

            let index = 0;
            sections.forEach((section) => section.items.forEach((item) => (item.index = index++)));

            return sections;
        },

        get flat() {
            return this.sections.flatMap((section) => section.items);
        },

        get showGroups() {
            const q = this.query.trim().toLowerCase();
            const last = this.searchedFor.toLowerCase();

            return last !== '' && q.startsWith(last);
        },

        get stale() {
            return this.searching && this.searchedFor !== this.query.trim();
        },

        async submit(newTab = false) {
            const q = this.query.trim();

            if (this.stale) {
                clearTimeout(this.timer);
                await this.fetchResults(q);
            }

            this.go(this.flat[this.active], newTab);
        },

        get searching() {
            return this.query.trim().length >= config.minLength;
        },

        get pending() {
            return this.searching && (this.loading || this.searchedFor !== this.query.trim());
        },

        show() {
            this.open = true;
            this.query = '';
            this.active = 0;
            this.recent = readRecent();
            this.$nextTick(() => this.$refs.input.focus());
        },

        close() {
            this.open = false;
        },

        schedule() {
            clearTimeout(this.timer);
            const q = this.query.trim();

            if (q.length < config.minLength) {
                this.controller?.abort();
                this.groups = [];
                this.searchedFor = '';
                this.loading = false;
                this.failed = false;
                return;
            }

            this.timer = setTimeout(() => this.fetchResults(q), 200);
        },

        async fetchResults(q) {
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            this.failed = false;

            try {
                const response = await fetch(`${config.url}?q=${encodeURIComponent(q)}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: this.controller.signal,
                });

                if (!response.ok) throw new Error(String(response.status));

                const data = await response.json();
                if (q !== this.query.trim()) return;

                this.groups = data.groups;
                this.searchedFor = q;
                this.active = 0;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    this.failed = true;
                    this.searchedFor = q;
                }
            } finally {
                if (q === this.query.trim()) this.loading = false;
            }
        },

        mark(text) {
            const safe = escapeHtml(text);
            const terms = this.terms.filter((term) => term.length > 1);
            if (!terms.length) return safe;

            const pattern = new RegExp(`(${terms.map((term) => escapeRegex(escapeHtml(term))).join('|')})`, 'gi');
            return safe.replace(pattern, '<mark class="rounded-sm bg-amber-100 text-inherit dark:bg-amber-400/25">$1</mark>');
        },

        icon(name) {
            return this.icons[name] ?? this.icons.search ?? '';
        },

        remember(item) {
            if (item.kind !== 'record' && item.kind !== 'recent') return;

            const entry = { id: item.id, title: item.title, subtitle: item.subtitle ?? '', url: item.url, icon: item.icon, group: item.group ?? '' };
            this.recent = [entry, ...readRecent().filter((existing) => existing.url !== entry.url)].slice(0, RECENT_LIMIT);
            writeRecent(this.recent);
        },

        clearRecent() {
            this.recent = [];
            writeRecent([]);
            this.active = 0;
        },

        go(item, newTab = false) {
            if (!item) return;

            this.remember(item);
            this.open = false;

            if (item.external || newTab) {
                window.open(item.url, '_blank', 'noopener');
            } else {
                window.location.href = item.url;
            }
        },

        move(step) {
            const count = this.flat.length;
            if (!count) return;

            this.active = (this.active + step + count) % count;
            this.$nextTick(() => this.$refs.list.querySelector(`[data-index='${this.active}']`)?.scrollIntoView({ block: 'nearest' }));
        },
    }));
}
