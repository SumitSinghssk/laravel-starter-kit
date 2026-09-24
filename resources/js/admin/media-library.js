import axios from 'axios';
import { ChunkedUpload, UploadAborted, isRetryableError, uploadErrorMessage } from './chunked-upload';

const CONCURRENT_FILES = 2;
const ASPECT_TOLERANCE = 0.02;
const RASTER_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const EXTENSION_TYPES = {
    jpg: 'image/jpeg',
    jpeg: 'image/jpeg',
    png: 'image/png',
    webp: 'image/webp',
    gif: 'image/gif',
    svg: 'image/svg+xml',
    ico: 'image/x-icon',
};

let taskCounter = 0;

function typeOf(file) {
    if (file.type) return file.type === 'image/vnd.microsoft.icon' ? 'image/x-icon' : file.type;

    return EXTENSION_TYPES[file.name.split('.').pop().toLowerCase()] ?? '';
}

function imageSize(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const image = new Image();
        image.onload = () => {
            resolve({ width: image.naturalWidth, height: image.naturalHeight });
            URL.revokeObjectURL(url);
        };
        image.onerror = () => {
            resolve(null);
            URL.revokeObjectURL(url);
        };
        image.src = url;
    });
}

export default function registerMediaLibrary(Alpine) {
    Alpine.data('mediaLibrary', (config) => ({
        uploadOpen: false,
        folder: config.defaultFolder,
        queue: [],
        dropActive: false,
        uploadedCount: 0,
        navigating: false,

        scanning: false,
        changesFound: false,

        panelOpen: false,
        details: null,
        detailsLoading: false,
        detailsError: '',
        replace: null,
        confirming: null,
        busy: false,

        init() {
            if (config.stale) this.rescan(false);

            window.addEventListener('beforeunload', (event) => {
                if (this.activeUploads || this.replace?.status === 'uploading') {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        },

        async rescan(manual = true) {
            if (this.scanning) return;
            this.scanning = true;

            try {
                const { data } = await axios.post(config.urls.rescan);

                if (data.changed) {
                    if (manual) return this.reload();
                    this.changesFound = true;
                } else if (manual) {
                    window.toast?.('success', data.busy ? 'A scan is already running. Try again in a moment.' : 'Everything is up to date.');
                }
            } catch (error) {
                if (manual) window.toast?.('error', uploadErrorMessage(error, 'The folders could not be scanned.'));
            } finally {
                this.scanning = false;
            }
        },

        reload() {
            window.location.reload();
        },

        get activeUploads() {
            return this.queue.filter((task) => ['uploading', 'queued'].includes(task.status)).length;
        },

        get running() {
            return this.queue.filter((task) => task.status === 'uploading').length;
        },

        get settled() {
            return this.queue.length > 0 && this.activeUploads === 0 && !this.queue.some((task) => task.status === 'paused');
        },

        pickUploads() {
            this.$refs.uploadInput.click();
        },

        onDrop(event) {
            this.dropActive = false;
            this.addFiles(event.dataTransfer.files);
        },

        addFiles(fileList) {
            for (const file of Array.from(fileList ?? [])) {
                const task = {
                    key: ++taskCounter,
                    name: file.name,
                    size: file.size,
                    status: 'queued',
                    progress: 0,
                    error: '',
                    retryable: false,
                    upload: new ChunkedUpload(file, { startUrl: config.urls.upload, startData: { folder: this.folder } }),
                };

                if (!typeOf(file) || !Object.values(EXTENSION_TYPES).includes(typeOf(file))) {
                    Object.assign(task, { status: 'error', error: 'Not a supported image. Use JPG, PNG, WebP, GIF, SVG or ICO.' });
                } else if (file.size > config.maxSize) {
                    Object.assign(task, {
                        status: 'error',
                        error: `Too large (${this.bytes(file.size)}). Images can be at most ${this.bytes(config.maxSize)}.`,
                    });
                }

                this.queue.push(task);
            }

            this.$refs.uploadInput.value = '';
            this.pump();
        },

        pump() {
            for (const task of this.queue) {
                if (this.running >= CONCURRENT_FILES) break;
                if (task.status === 'queued') this.runTask(task);
            }

            if (this.settled && this.uploadedCount && !this.navigating && !this.queue.some((task) => task.status === 'error')) {
                this.navigating = true;
                setTimeout(() => this.showNewest(), 900);
            }
        },

        async runTask(task) {
            task = this.queue.find((t) => t.key === task.key);
            task.status = 'uploading';
            task.error = '';
            task.upload.onProgress = (percent) => (task.progress = percent);

            try {
                await task.upload.start();
                task.status = 'done';
                task.progress = 100;
                this.uploadedCount++;
            } catch (error) {
                if (error instanceof UploadAborted) return;

                task.status = 'error';
                task.error = uploadErrorMessage(error);
                task.retryable = isRetryableError(error);
            } finally {
                this.pump();
            }
        },

        pause(task) {
            task.status = 'paused';
            task.upload.pause();
            this.pump();
        },

        resume(task) {
            task.status = 'queued';
            this.pump();
        },

        async cancel(task) {
            this.queue = this.queue.filter((t) => t.key !== task.key);
            await task.upload.cancel();
            this.pump();
        },

        showNewest() {
            const url = new URL(window.location);
            url.searchParams.delete('page');
            url.searchParams.set('sort', 'newest');
            window.location = url;
        },

        taskLabel(task) {
            return (
                {
                    queued: 'Waiting…',
                    uploading: task.progress >= 99 ? 'Saving…' : `${task.progress}%`,
                    paused: `Paused at ${task.progress}%`,
                    done: 'Uploaded',
                    error: 'Failed',
                }[task.status] ?? ''
            );
        },

        async open(url) {
            this.panelOpen = true;
            this.detailsUrl = url;
            this.replace = null;
            await this.loadDetails();
        },

        async loadDetails() {
            this.detailsLoading = true;
            this.detailsError = '';

            try {
                const { data } = await axios.get(this.detailsUrl);
                this.details = data;
            } catch (error) {
                this.detailsError = uploadErrorMessage(error, 'The details could not be loaded.');
            } finally {
                this.detailsLoading = false;
            }
        },

        close() {
            if (this.replace?.status === 'uploading') return;
            this.panelOpen = false;
            this.confirming = null;
        },

        async copyUrl() {
            try {
                await navigator.clipboard.writeText(this.details.url);
                window.toast?.('success', 'Link copied.');
            } catch {
                window.prompt('Copy this link:', this.details.url);
            }
        },

        announce(file) {
            window.dispatchEvent(
                new CustomEvent('media-updated', {
                    detail: { id: file.id, thumb: file.thumb, size: file.size_label, dimensions: file.dimensions },
                }),
            );
        },

        pickReplacement() {
            document.getElementById('media-replace-input')?.click();
        },

        onReplaceDrop(event) {
            if (this.details?.can_replace) this.chooseReplacement(event.dataTransfer.files[0]);
        },

        async chooseReplacement(file) {
            const input = document.getElementById('media-replace-input');
            if (input) input.value = '';
            if (!file || !this.details) return;

            const type = typeOf(file);
            this.replace = { file, status: 'checking', progress: 0, error: '', upload: null, choice: null };

            if (!this.details.accept.includes(type)) {
                this.replace.status = 'error';
                this.replace.error =
                    this.details.extension === 'svg'
                        ? 'An SVG image can only be replaced by another SVG file.'
                        : this.details.extension === 'ico'
                          ? 'An .ico icon can only be replaced by another .ico file.'
                          : 'Please choose a JPG, PNG, WebP or GIF image.';
                return;
            }

            if (file.size > config.maxSize) {
                this.replace.status = 'error';
                this.replace.error = `Too large (${this.bytes(file.size)}). Images can be at most ${this.bytes(config.maxSize)}.`;
                return;
            }

            if (RASTER_TYPES.includes(type) && this.details.width && this.details.height) {
                const size = await imageSize(file);

                if (size) {
                    const current = this.details.width / this.details.height;
                    const next = size.width / size.height;

                    if (Math.abs(next - current) / current > ASPECT_TOLERANCE) {
                        this.replace.status = 'choose';
                        this.replace.choice = { width: size.width, height: size.height };
                        return;
                    }
                }
            }

            this.startReplace('keep');
        },

        async startReplace(fit) {
            const replace = this.replace;
            replace.status = 'uploading';
            replace.error = '';
            replace.fit = fit;
            replace.upload ??= new ChunkedUpload(replace.file, { startUrl: config.urls.upload, startData: { media_file_id: this.details.id } });
            replace.upload.onProgress = (percent) => (this.replace.progress = percent);

            try {
                const { file } = await replace.upload.start({ fit });
                this.replace = null;
                this.announce(file);
                await this.loadDetails();
                window.toast?.('success', 'Image replaced. Every page using it now shows the new version.');
            } catch (error) {
                if (error instanceof UploadAborted) return;
                this.replace.status = 'error';
                this.replace.error = uploadErrorMessage(error);
                this.replace.retryable = isRetryableError(error);
            }
        },

        async cancelReplace() {
            const upload = this.replace?.upload;
            this.replace = null;
            await upload?.cancel();
        },

        restore(version) {
            this.confirming = {
                title: 'Restore this version?',
                message: `The current image is kept as a version, so you can switch back. Saved ${version.created}.`,
                label: 'Restore',
                danger: false,
                action: async () => {
                    const { data } = await axios.post(version.restore_url);
                    this.details = data.file;
                    this.announce(data.file);
                    window.toast?.('success', 'Earlier version restored.');
                },
            };
        },

        remove() {
            this.confirming = {
                title: 'Delete this image?',
                message: `${this.details.filename} will be permanently deleted, together with its earlier versions.`,
                label: 'Delete',
                danger: true,
                action: async () => {
                    await axios.delete(this.details.delete_url);
                    window.dispatchEvent(new CustomEvent('media-deleted', { detail: { id: this.details.id } }));
                    this.panelOpen = false;
                    window.toast?.('success', 'Image deleted.');
                },
            };
        },

        async runConfirmed() {
            if (!this.confirming || this.busy) return;

            this.busy = true;
            try {
                await this.confirming.action();
                this.confirming = null;
            } catch (error) {
                window.toast?.('error', uploadErrorMessage(error));
            } finally {
                this.busy = false;
            }
        },

        bytes(bytes) {
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

            return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
        },
    }));
}
