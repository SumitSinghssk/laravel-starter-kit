import axios from 'axios';

const CONCURRENT_FILES = 2;
const CHUNK_RETRIES = 4;
const POSTER_TIMEOUT = 8000;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function errorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const data = error?.response?.data;

    if (data?.errors) {
        return Object.values(data.errors).flat()[0] ?? fallback;
    }

    if (data?.message && error.response.status < 500) {
        return data.message;
    }

    if (!error?.response) {
        return 'Connection lost. Check your internet connection and try again.';
    }

    return fallback;
}

function isRetryable(error) {
    const status = error?.response?.status;

    return !status || status === 408 || status === 422 || status === 429 || status >= 500;
}

export function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export function formatDuration(seconds) {
    if (!seconds && seconds !== 0) return '';
    const s = Math.round(seconds);

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}

function captureVideoDetails(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const video = document.createElement('video');
        let meta = {};

        const finish = (result) => {
            clearTimeout(timer);
            video.removeAttribute('src');
            video.load();
            URL.revokeObjectURL(url);
            resolve(result);
        };
        const timer = setTimeout(() => finish(meta), POSTER_TIMEOUT);

        video.muted = true;
        video.playsInline = true;
        video.preload = 'auto';
        video.onerror = () => finish(meta);

        video.onloadedmetadata = () => {
            const duration = Number.isFinite(video.duration) ? video.duration : null;
            meta = { duration, width: video.videoWidth || null, height: video.videoHeight || null };

            try {
                video.currentTime = duration ? Math.min(1, duration / 4) : 0;
            } catch {
                finish(meta);
            }
        };

        video.onseeked = () => {
            if (!video.videoWidth) return finish(meta);

            const width = Math.min(640, video.videoWidth);
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = Math.round((video.videoHeight / video.videoWidth) * width);
            canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => finish({ ...meta, poster: blob }), 'image/jpeg', 0.82);
        };

        video.src = url;
    });
}

let taskCounter = 0;

export default function registerGallery(Alpine) {
    Alpine.data('galleryManager', (config) => ({
        items: [],
        total: 0,
        nextUrl: config.urls.media,
        loading: false,
        loadError: '',
        coverId: config.coverId,

        queue: [],
        dropActive: false,

        youtube: { url: '', title: '', saving: false, error: '' },

        selecting: false,
        selected: [],

        dragId: null,
        orderChanged: false,

        editing: null,
        form: { title: '', caption: '', thumbnail: null, thumbnailPreview: '', removeThumbnail: false, saving: false, error: '' },

        confirming: null,

        init() {
            this.loadMore();

            this._observer = new IntersectionObserver((entries) => entries.some((e) => e.isIntersecting) && this.loadMore(), {
                rootMargin: '600px 0px',
            });
            this._observer.observe(this.$refs.sentinel);

            window.addEventListener('beforeunload', (event) => {
                if (this.activeUploads) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        destroy() {
            this._observer?.disconnect();
        },

        async loadMore() {
            if (!this.nextUrl || this.loading) return;

            this.loading = true;
            this.loadError = '';

            try {
                const { data } = await axios.get(this.nextUrl);
                const known = new Set(this.items.map((item) => item.id));
                this.items.push(...data.data.filter((item) => !known.has(item.id)));
                this.nextUrl = data.next_page_url;
                this.total = data.total;
            } catch (error) {
                this.loadError = errorMessage(error, 'The media could not be loaded.');
            } finally {
                this.loading = false;
            }

            this.$nextTick(() => {
                const rect = this.$refs.sentinel.getBoundingClientRect();
                if (this.nextUrl && !this.loadError && rect.top < window.innerHeight + 600) this.loadMore();
            });
        },

        addItem(item) {
            if (!this.items.some((existing) => existing.id === item.id)) {
                this.items.push(item);
                this.total++;
            }
        },

        replaceItem(item) {
            const index = this.items.findIndex((existing) => existing.id === item.id);
            if (index !== -1) this.items[index] = item;
        },

        removeItems(ids) {
            const gone = new Set(ids);
            const before = this.items.length;
            this.items = this.items.filter((item) => !gone.has(item.id));
            this.total -= before - this.items.length;
            this.selected = this.selected.filter((id) => !gone.has(id));
            if (gone.has(this.coverId)) this.coverId = null;
            if (this.editing && gone.has(this.editing.id)) this.editing = null;
        },

        get counts() {
            return {
                images: this.items.filter((item) => item.type === 'image').length,
                videos: this.items.filter((item) => item.type !== 'image').length,
            };
        },

        get activeUploads() {
            return this.queue.filter((task) => ['preparing', 'uploading', 'finishing'].includes(task.status)).length;
        },

        get hasFinished() {
            return this.queue.some((task) => ['error', 'cancelled'].includes(task.status));
        },

        pick() {
            this.$refs.fileInput.click();
        },

        onDrop(event) {
            this.dropActive = false;
            this.addFiles(event.dataTransfer.files);
        },

        addFiles(fileList) {
            for (const file of Array.from(fileList ?? [])) {
                const kind = this.kindOf(file);
                const task = Alpine.reactive({
                    key: ++taskCounter,
                    file,
                    name: file.name,
                    size: file.size,
                    kind,
                    status: 'queued',
                    progress: 0,
                    error: '',
                    retryable: false,
                    paused: false,
                    session: null,
                    controller: null,
                });

                if (!kind) {
                    task.status = 'error';
                    task.error = 'Not a supported file. Use JPG, PNG or WebP images, or MP4, WebM or MOV videos.';
                } else if (file.size > config.maxSize[kind]) {
                    task.status = 'error';
                    task.error = `Too large (${formatBytes(file.size)}). ${kind === 'image' ? 'Images' : 'Videos'} can be at most ${formatBytes(config.maxSize[kind])}.`;
                } else if (file.size === 0) {
                    task.status = 'error';
                    task.error = 'This file is empty.';
                }

                this.queue.push(task);
            }

            this.$refs.fileInput.value = '';
            this.pump();
        },

        kindOf(file) {
            if (config.types.image.includes(file.type)) return 'image';
            if (config.types.video.includes(file.type)) return 'video';

            const extension = file.name.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'webp'].includes(extension)) return 'image';
            if (['mp4', 'm4v', 'webm', 'mov'].includes(extension)) return 'video';

            return null;
        },

        pump() {
            let running = this.activeUploads;

            for (const task of this.queue) {
                if (running >= CONCURRENT_FILES) break;
                if (task.status === 'queued') {
                    running++;
                    this.upload(task);
                }
            }
        },

        async upload(task) {
            task.status = 'preparing';
            task.error = '';
            task.controller = new AbortController();

            try {
                if (task.kind === 'video' && !task.details) {
                    task.details = await captureVideoDetails(task.file);
                }

                const { data: session } = await axios.post(
                    config.urls.uploads,
                    {
                        name: task.file.name,
                        size: task.file.size,
                        mime: task.file.type,
                        fingerprint: `${task.file.name}|${task.file.size}|${task.file.lastModified}`,
                    },
                    { signal: task.controller.signal },
                );
                task.session = session;

                await this.sendChunks(task, new Set(session.received));
                await this.finish(task);
            } catch (error) {
                if (task.paused || task.status === 'cancelled') return;

                const status = error?.response?.status;
                task.status = 'error';
                task.error = errorMessage(error);
                task.retryable = !status || status >= 500 || status === 408 || status === 429 || status === 409;
            } finally {
                this.pump();
            }
        },

        async sendChunks(task, received) {
            const { chunk_size: chunkSize, total_chunks: total } = task.session;
            task.status = 'uploading';

            for (let index = 0; index < total; index++) {
                if (received.has(index)) continue;

                const start = index * chunkSize;
                const blob = task.file.slice(start, Math.min(start + chunkSize, task.file.size));
                await this.sendChunk(task, index, blob, received);
                received.add(index);
                task.progress = Math.round((received.size / total) * 100);
            }
        },

        async sendChunk(task, index, blob, received) {
            const { chunk_size: chunkSize, total_chunks: total } = task.session;

            for (let attempt = 0; ; attempt++) {
                if (task.paused) throw new Error('paused');

                const body = new FormData();
                body.append('chunk', blob, `${index}`);

                try {
                    await axios.post(task.session.chunk_url.replace('__INDEX__', index), body, {
                        signal: task.controller.signal,
                        onUploadProgress: (event) => {
                            const done = received.size * chunkSize + (event.loaded / (event.total || blob.size)) * blob.size;
                            task.progress = Math.min(99, Math.round((done / task.file.size) * 100));
                        },
                    });

                    return;
                } catch (error) {
                    if (task.paused || task.status === 'cancelled') throw error;
                    if (!isRetryable(error) || attempt >= CHUNK_RETRIES) throw error;

                    await sleep(1000 * 2 ** attempt);
                }
            }
        },

        async finish(task) {
            task.status = 'finishing';
            task.progress = 100;

            const body = new FormData();
            const details = task.details ?? {};
            if (details.poster) body.append('thumbnail', details.poster, 'poster.jpg');
            if (details.duration) body.append('duration', details.duration);
            if (details.width) body.append('width', details.width);
            if (details.height) body.append('height', details.height);

            for (let attempt = 0; ; attempt++) {
                try {
                    const { data } = await axios.post(task.session.complete_url, body, { signal: task.controller.signal });
                    this.addItem(data.item);
                    task.status = 'done';
                    setTimeout(() => (this.queue = this.queue.filter((t) => t !== task)), 2500);

                    return;
                } catch (error) {
                    const missing = error?.response?.data?.missing;
                    if (missing?.length && attempt < 2) {
                        const all = new Set([...Array(task.session.total_chunks).keys()]);
                        missing.forEach((index) => all.delete(index));
                        await this.sendChunks(task, all);
                        task.status = 'finishing';
                        continue;
                    }

                    const status = error?.response?.status;
                    if ((!status || status >= 500 || status === 409) && attempt < 2 && !task.paused) {
                        await sleep(2000 * (attempt + 1));
                        continue;
                    }

                    throw error;
                }
            }
        },

        pause(task) {
            task.paused = true;
            task.status = 'paused';
            task.controller?.abort();
            this.pump();
        },

        resume(task) {
            task.paused = false;
            task.status = 'queued';
            this.pump();
        },

        retry(task) {
            task.status = 'queued';
            task.error = '';
            this.pump();
        },

        async cancel(task) {
            const cancelUrl = task.session?.cancel_url;
            task.status = 'cancelled';
            task.controller?.abort();
            this.queue = this.queue.filter((t) => t !== task);
            this.pump();

            if (cancelUrl) await axios.delete(cancelUrl).catch(() => {});
        },

        clearFinished() {
            this.queue = this.queue.filter((task) => !['error', 'cancelled', 'done'].includes(task.status));
        },

        taskLabel(task) {
            return (
                {
                    queued: 'Waiting…',
                    preparing: task.kind === 'video' ? 'Reading video…' : 'Starting…',
                    uploading: `${task.progress}%`,
                    finishing: task.kind === 'image' ? 'Optimising…' : 'Saving…',
                    paused: `Paused at ${task.progress}%`,
                    done: 'Added',
                    error: 'Failed',
                }[task.status] ?? ''
            );
        },

        async addYoutube() {
            if (!this.youtube.url.trim() || this.youtube.saving) return;

            this.youtube.saving = true;
            this.youtube.error = '';

            try {
                const { data } = await axios.post(config.urls.youtube, { url: this.youtube.url, title: this.youtube.title || null });
                this.addItem(data.item);
                this.youtube.url = '';
                this.youtube.title = '';
                window.toast?.('success', 'YouTube video added.');
            } catch (error) {
                this.youtube.error = errorMessage(error);
            } finally {
                this.youtube.saving = false;
            }
        },

        dragStart(item, event) {
            if (this.selecting) return;
            this.dragId = item.id;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(item.id));
        },

        dragEnter(item) {
            if (this.dragId === null || this.dragId === item.id) return;

            const from = this.items.findIndex((i) => i.id === this.dragId);
            const to = this.items.findIndex((i) => i.id === item.id);
            const [moved] = this.items.splice(from, 1);
            this.items.splice(to, 0, moved);
            this.orderChanged = true;
        },

        dragEnd() {
            this.dragId = null;
            if (this.orderChanged) this.saveOrder();
        },

        move(item, step) {
            const from = this.items.findIndex((i) => i.id === item.id);
            const to = from + step;
            if (to < 0 || to >= this.items.length) return;

            const [moved] = this.items.splice(from, 1);
            this.items.splice(to, 0, moved);
            this.saveOrder();
        },

        async saveOrder() {
            this.orderChanged = false;

            try {
                await axios.post(config.urls.reorder, { ids: this.items.map((item) => item.id) });
            } catch (error) {
                window.toast?.('error', errorMessage(error, 'The new order could not be saved. Refresh the page and try again.'));
            }
        },

        toggleSelecting() {
            this.selecting = !this.selecting;
            this.selected = [];
        },

        toggleSelected(item) {
            this.selected = this.selected.includes(item.id) ? this.selected.filter((id) => id !== item.id) : [...this.selected, item.id];
        },

        selectAll() {
            this.selected = this.selected.length === this.items.length ? [] : this.items.map((item) => item.id);
        },

        confirm(message, action, label = 'Delete') {
            this.confirming = { message, action, label, busy: false };
        },

        async runConfirmed() {
            if (!this.confirming || this.confirming.busy) return;

            this.confirming.busy = true;
            try {
                await this.confirming.action();
                this.confirming = null;
            } catch (error) {
                this.confirming.busy = false;
                window.toast?.('error', errorMessage(error));
            }
        },

        deleteSelected() {
            const ids = [...this.selected];
            this.confirm(`Delete ${ids.length} selected item${ids.length === 1 ? '' : 's'}? This cannot be undone.`, async () => {
                const { data } = await axios.post(config.urls.bulkDelete, { ids });
                this.removeItems(data.deleted);
                this.selecting = false;
                window.toast?.('success', `${data.deleted.length} item${data.deleted.length === 1 ? '' : 's'} deleted.`);
            });
        },

        deleteItem(item) {
            this.confirm(`Delete “${item.title || 'this item'}”? This cannot be undone.`, async () => {
                await axios.delete(item.delete_url);
                this.removeItems([item.id]);
                window.toast?.('success', 'Item deleted.');
            });
        },

        async setCover(item) {
            const itemId = this.coverId === item.id ? null : item.id;

            try {
                await axios.post(config.urls.cover, { item_id: itemId });
                this.coverId = itemId;
                this.items = this.items.map((i) => ({ ...i, is_cover: i.id === itemId }));
                if (this.editing) this.editing = this.items.find((i) => i.id === this.editing.id) ?? null;
                window.toast?.('success', itemId ? 'Album cover updated.' : 'Cover reset to the first item.');
            } catch (error) {
                window.toast?.('error', errorMessage(error));
            }
        },

        open(item) {
            if (this.selecting) return this.toggleSelected(item);

            this.editing = item;
            this.form = {
                title: item.title ?? '',
                caption: item.caption ?? '',
                thumbnail: null,
                thumbnailPreview: '',
                removeThumbnail: false,
                saving: false,
                error: '',
            };
        },

        close() {
            if (this.form.thumbnailPreview) URL.revokeObjectURL(this.form.thumbnailPreview);
            this.editing = null;
        },

        step(offset) {
            const index = this.items.findIndex((item) => item.id === this.editing?.id);
            const next = this.items[index + offset];
            if (next) this.open(next);
        },

        pickThumbnail(event) {
            const file = event.target.files[0];
            if (!file) return;

            if (!config.types.image.includes(file.type) || file.size > 2 * 1024 * 1024) {
                this.form.error = 'The thumbnail must be a JPG, PNG or WebP image of at most 2 MB.';
                event.target.value = '';
                return;
            }

            if (this.form.thumbnailPreview) URL.revokeObjectURL(this.form.thumbnailPreview);
            this.form.thumbnail = file;
            this.form.thumbnailPreview = URL.createObjectURL(file);
            this.form.removeThumbnail = false;
            this.form.error = '';
        },

        async save() {
            if (!this.editing || this.form.saving) return;

            this.form.saving = true;
            this.form.error = '';

            const body = new FormData();
            body.append('title', this.form.title);
            body.append('caption', this.form.caption);
            if (this.form.thumbnail) body.append('thumbnail', this.form.thumbnail);
            if (this.form.removeThumbnail) body.append('remove_thumbnail', '1');

            try {
                const { data } = await axios.post(this.editing.update_url, body);
                this.replaceItem(data.item);
                this.close();
                window.toast?.('success', 'Changes saved.');
            } catch (error) {
                this.form.error = errorMessage(error);
            } finally {
                this.form.saving = false;
            }
        },

        formatBytes,
        formatDuration,
    }));
}
