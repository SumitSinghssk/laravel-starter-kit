const INDENT = 28;
const SAFE_URL = /^(\/(?!\/)|#|\?|https?:\/\/[^/\s]+|mailto:.+|tel:.+)/i;
let uidSeed = 0;
const withUid = (rows) =>
    rows.map((row) => ({ style: 'dropdown', image: '', description: '', ...row, uid: ++uidSeed, open: false, collapsed: false, uploading: false }));
const strip = (rows) =>
    rows.map(({ label, url, style, image, description, new_tab, is_active, depth }) => ({
        label,
        url,
        style: style || 'dropdown',
        image: image || '',
        description: description || '',
        new_tab: !!new_tab,
        is_active: !!is_active,
        depth,
    }));

export default function registerMenus(Alpine) {
    Alpine.data('menuBuilder', ({ rows, urls, maxDepth, canEdit, rich, siteUrl, version }) => ({
        rich,
        version,
        stale: false,
        rows: withUid(rows),
        saved: '',
        savedRows: JSON.parse(JSON.stringify(rows)),
        maxDepth,
        canEdit,
        errors: {},
        saving: false,
        drag: null,
        link: { label: '', url: '', new_tab: false },
        linkError: '',
        flash: [],

        init() {
            this.saved = JSON.stringify(strip(this.rows));
            window.addEventListener('beforeunload', (event) => {
                if (this.dirty) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        get dirty() {
            return JSON.stringify(strip(this.rows)) !== this.saved;
        },

        get errorCount() {
            return Object.keys(this.errors).length;
        },

        error(index, field) {
            return this.errors[`items.${index}.${field}`] ?? '';
        },

        rowHasError(index) {
            return Object.keys(this.errors).some((key) => key.startsWith(`items.${index}.`));
        },

        validUrl(url) {
            return SAFE_URL.test((url ?? '').trim()) && !/\s/.test((url ?? '').trim());
        },

        topOf(index) {
            for (let i = index; i >= 0; i--) {
                if (this.rows[i]?.depth === 0) return this.rows[i];
            }
            return null;
        },

        inMega(index) {
            return this.rich && this.topOf(index)?.style === 'mega';
        },

        role(index) {
            const row = this.rows[index];
            if (!row) return '';
            if (row.depth === 0) return this.hasChildren(index) ? (row.style === 'mega' ? 'Mega menu' : 'Dropdown') : '';
            if (this.inMega(index)) return row.depth === 1 ? 'Column' : row.image ? 'Card' : '';
            return row.depth === 2 ? 'Side menu' : '';
        },

        imageSrc(row) {
            const image = (row?.image ?? '').trim();
            if (!image) return '';
            return image.startsWith('/') ? siteUrl.replace(/\/$/, '') + image : image;
        },

        async uploadImage(row, event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (!file) return;
            if (!/^image\/(jpe?g|png|webp)$/.test(file.type)) {
                window.toast?.('error', 'Choose a JPG, PNG or WebP image.');
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                window.toast?.('error', 'The image can be at most 2 MB.');
                return;
            }

            row.uploading = true;
            try {
                const form = new FormData();
                form.append('file', file);
                const { data } = await window.axios.post(urls.upload, form);
                const base = siteUrl.replace(/\/$/, '');
                row.image = data.location.startsWith(base) ? data.location.slice(base.length) || '/' : data.location;
            } catch (error) {
                window.toast?.('error', error.response?.data?.errors?.file?.[0] ?? 'The image could not be uploaded.');
            } finally {
                row.uploading = false;
            }
        },

        isTitle(row) {
            return !(row?.url ?? '').trim();
        },

        needsChildren(index) {
            return this.isTitle(this.rows[index]) && !this.hasChildren(index);
        },

        blockEnd(index) {
            if (!this.rows[index]) return this.rows.length;
            let end = index + 1;
            while (end < this.rows.length && this.rows[end].depth > this.rows[index].depth) end++;
            return end;
        },

        span(index) {
            const row = this.rows[index];
            if (!row) return 0;
            return this.rows.slice(index + 1, this.blockEnd(index)).reduce((max, child) => Math.max(max, child.depth - row.depth), 0);
        },

        hasChildren(index) {
            const row = this.rows[index];
            return !!row && (this.rows[index + 1]?.depth ?? -1) > row.depth;
        },

        levelName(depth) {
            return ['', 'Level 2', 'Level 3'][depth] ?? '';
        },

        continues(index, level) {
            for (let j = index + 1; j < this.rows.length; j++) {
                if (this.rows[j].depth < level) return false;
                if (this.rows[j].depth === level) return true;
            }
            return false;
        },

        guides(index) {
            const row = this.rows[index];
            if (!row) return [];
            return Array.from({ length: row.depth }, (_, i) => {
                const level = i + 1;
                const more = this.continues(index, level);
                if (level === row.depth) return more ? 'tee' : 'elbow';
                return more ? 'pipe' : 'none';
            });
        },

        isHidden(index) {
            let depth = this.rows[index]?.depth ?? 0;
            for (let i = index - 1; i >= 0 && depth > 0; i--) {
                if (this.rows[i].depth < depth) {
                    if (this.rows[i].collapsed) return true;
                    depth = this.rows[i].depth;
                }
            }
            return false;
        },

        descendants(index) {
            return this.blockEnd(index) - index - 1;
        },

        reveal(index) {
            let depth = this.rows[index]?.depth ?? 0;
            for (let i = index - 1; i >= 0 && depth > 0; i--) {
                if (this.rows[i].depth < depth) {
                    this.rows[i].collapsed = false;
                    depth = this.rows[i].depth;
                }
            }
        },

        toggleCollapse(index) {
            const row = this.rows[index];
            if (row && this.hasChildren(index)) row.collapsed = !row.collapsed;
        },

        addLink() {
            const label = this.link.label.trim();
            const url = this.link.url.trim();
            if (!label) {
                this.linkError = 'Enter a label.';
                return;
            }
            if (url && !this.validUrl(url)) {
                this.linkError = 'Use a web address (https://…), a path on this site (/about), #section, mailto: or tel:.';
                return;
            }

            this.linkError = '';
            const [row] = withUid([
                { label, url, style: 'dropdown', image: '', description: '', new_tab: !!url && this.link.new_tab, is_active: true, depth: 0 },
            ]);
            this.rows.push(row);
            this.link = { label: '', url: '', new_tab: false };
            this.flash = [row.uid];
            setTimeout(() => (this.flash = []), 1600);
            this.$nextTick(() => {
                document.getElementById(`menu-row-${row.uid}`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                this.$refs.linkLabel?.focus();
            });
        },

        remove(index) {
            this.rows[index].collapsed = false;
            const end = this.blockEnd(index);
            for (let i = index + 1; i < end; i++) this.rows[i].depth = Math.max(0, this.rows[i].depth - 1);
            this.rows.splice(index, 1);
        },

        moveBlock(from, to) {
            const end = this.blockEnd(from);
            const block = this.rows.splice(from, end - from);
            this.rows.splice(to, 0, ...block);
        },

        moveUp(index) {
            const depth = this.rows[index].depth;
            let prev = index - 1;
            while (prev >= 0 && this.rows[prev].depth > depth) prev--;
            if (prev < 0 || this.rows[prev].depth < depth) return;
            this.moveBlock(index, prev);
        },

        moveDown(index) {
            const depth = this.rows[index].depth;
            const next = this.blockEnd(index);
            if (next >= this.rows.length || this.rows[next].depth < depth) return;
            const nextEnd = this.blockEnd(next);
            const size = this.blockEnd(index) - index;
            this.moveBlock(index, nextEnd - size);
        },

        canMoveUp(index) {
            const row = this.rows[index];
            if (!this.canEdit || !row) return false;
            let prev = index - 1;
            while (prev >= 0 && this.rows[prev].depth > row.depth) prev--;
            return prev >= 0 && this.rows[prev].depth === row.depth;
        },

        canMoveDown(index) {
            const row = this.rows[index];
            if (!this.canEdit || !row) return false;
            const next = this.blockEnd(index);
            return next < this.rows.length && this.rows[next].depth === row.depth;
        },

        canIndent(index) {
            const row = this.rows[index];
            const prev = this.rows[index - 1];
            return this.canEdit && !!row && !!prev && row.depth <= prev.depth && row.depth + 1 + this.span(index) < this.maxDepth;
        },

        canOutdent(index) {
            return this.canEdit && (this.rows[index]?.depth ?? 0) > 0;
        },

        shiftBlock(index, by) {
            const end = this.blockEnd(index);
            for (let i = index; i < end; i++) this.rows[i].depth += by;
        },

        indent(index) {
            if (this.canIndent(index)) this.shiftBlock(index, 1);
        },

        outdent(index) {
            if (this.canOutdent(index)) this.shiftBlock(index, -1);
        },

        startDrag(event, index) {
            if (!this.canEdit || event.button > 0) return;
            event.preventDefault();

            const end = this.blockEnd(index);
            const blockUids = this.rows.slice(index, end).map((row) => row.uid);
            const others = this.rows
                .filter((row, i) => (i < index || i >= end) && !this.isHidden(i))
                .map((row) => {
                    const rect = document.getElementById(`menu-row-${row.uid}`).getBoundingClientRect();
                    return { uid: row.uid, depth: row.depth, mid: rect.top + rect.height / 2 };
                });

            this.drag = {
                uid: this.rows[index].uid,
                blockUids,
                others,
                startX: event.clientX,
                startDepth: this.rows[index].depth,
                span: this.span(index),
                slot: index,
                depth: this.rows[index].depth,
            };

            const move = (e) => this.dragMove(e);
            const up = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
                window.removeEventListener('pointercancel', up);
                this.dragEnd();
            };
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
            window.addEventListener('pointercancel', up);
        },

        dragMove(event) {
            const drag = this.drag;
            if (!drag) return;

            let slot = drag.others.findIndex((o) => event.clientY < o.mid);
            if (slot === -1) slot = drag.others.length;

            const above = drag.others[slot - 1];
            const maxHere = above ? above.depth + 1 : 0;
            const limit = Math.max(0, Math.min(maxHere, this.maxDepth - 1 - drag.span));
            const wanted = drag.startDepth + Math.round((event.clientX - drag.startX) / INDENT);

            drag.slot = slot;
            drag.depth = Math.max(0, Math.min(wanted, limit));

            const edge = 60;
            if (event.clientY < edge) window.scrollBy(0, -12);
            else if (event.clientY > window.innerHeight - edge) window.scrollBy(0, 12);
        },

        dragEnd() {
            const drag = this.drag;
            this.drag = null;
            if (!drag) return;

            const from = this.rows.findIndex((row) => row.uid === drag.uid);
            const end = this.blockEnd(from);
            const block = this.rows.splice(from, end - from);
            const shift = drag.depth - block[0].depth;
            block.forEach((row) => (row.depth += shift));

            const anchor = drag.others[drag.slot];
            const to = anchor ? this.rows.findIndex((row) => row.uid === anchor.uid) : this.rows.length;
            this.rows.splice(to, 0, ...block);

            for (let i = to + block.length; i < this.rows.length && this.rows[i].depth > this.rows[i - 1].depth + 1; i++) {
                this.rows[i].depth = this.rows[i - 1].depth + 1;
            }
        },

        dropLine(uid) {
            if (!this.drag || this.drag.blockUids.includes(uid)) return false;
            return this.drag.others[this.drag.slot]?.uid === uid;
        },

        get dropAtEnd() {
            return !!this.drag && this.drag.slot === this.drag.others.length;
        },

        isDragging(uid) {
            return !!this.drag && this.drag.blockUids.includes(uid);
        },

        async save() {
            if (!this.canEdit || this.saving) return;
            this.saving = true;

            try {
                const { data } = await window.axios.put(urls.update, { items: strip(this.rows), version: this.version });
                this.version = data.version;
                this.stale = false;
                this.savedRows = data.rows;
                this.rows = withUid(data.rows);
                this.saved = JSON.stringify(strip(this.rows));
                this.errors = {};
                window.toast?.('success', data.message);
            } catch (error) {
                if (error.response?.status === 409) {
                    this.stale = true;
                    window.toast?.('error', error.response.data.message);
                } else if (error.response?.status === 422) {
                    this.errors = error.response.data.errors ?? {};
                    const first = Object.keys(this.errors).find((key) => key.startsWith('items.'));
                    const firstIndex = first ? Number(first.split('.')[1]) : -1;
                    const row = this.rows[firstIndex];
                    if (row) {
                        this.reveal(firstIndex);
                        row.open = true;
                        this.$nextTick(() => document.getElementById(`menu-row-${row.uid}`)?.scrollIntoView({ block: 'center', behavior: 'smooth' }));
                    }
                    window.toast?.(
                        'error',
                        this.errors.items ?? `${this.errorCount} ${this.errorCount === 1 ? 'link needs' : 'links need'} fixing before saving.`,
                    );
                } else {
                    window.toast?.('error', error.response?.data?.message ?? 'Saving failed. Please try again.');
                }
            } finally {
                this.saving = false;
            }
        },

        reloadLatest() {
            this.saved = JSON.stringify(strip(this.rows));
            window.location.reload();
        },

        discard() {
            this.rows = withUid(JSON.parse(JSON.stringify(this.savedRows)));
            this.errors = {};
            window.toast?.('info', 'Changes discarded.');
        },
    }));
}
