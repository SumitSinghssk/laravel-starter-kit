import axios from 'axios';

const RETRIES = 4;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export class UploadAborted extends Error {}

export function uploadErrorMessage(error, fallback = 'Something went wrong. Please try again.') {
    const data = error?.response?.data;

    if (data?.errors) return Object.values(data.errors).flat()[0] ?? fallback;
    if (data?.message && error.response.status < 500) return data.message;
    if (!error?.response) return 'Connection lost. Check your internet connection and try again.';

    return fallback;
}

export function isRetryableError(error) {
    const status = error?.response?.status;

    return !status || [408, 409, 429].includes(status) || status >= 500;
}

export class ChunkedUpload {
    constructor(file, { startUrl, startData = {} }) {
        this.file = file;
        this.startUrl = startUrl;
        this.startData = startData;
        this.session = null;
        this.controller = null;
        this.stopped = false;
        this.onProgress = () => {};
    }

    async start(completeData = {}) {
        this.stopped = false;
        this.controller = new AbortController();

        try {
            const { data } = await axios.post(
                this.startUrl,
                {
                    ...this.startData,
                    name: this.file.name,
                    size: this.file.size,
                    mime: this.file.type,
                    fingerprint: `${this.file.name}|${this.file.size}|${this.file.lastModified}`,
                },
                { signal: this.controller.signal },
            );
            this.session = data;

            const received = new Set(data.received);
            await this.sendChunks(received);

            return await this.complete(completeData, received);
        } catch (error) {
            if (this.stopped) throw new UploadAborted();
            throw error;
        }
    }

    pause() {
        this.stopped = true;
        this.controller?.abort();
    }

    async cancel() {
        this.pause();
        if (this.session?.cancel_url) await axios.delete(this.session.cancel_url).catch(() => {});
    }

    async sendChunks(received) {
        const { chunk_size: chunkSize, total_chunks: total } = this.session;

        for (let index = 0; index < total; index++) {
            if (received.has(index)) continue;

            const start = index * chunkSize;
            const blob = this.file.slice(start, Math.min(start + chunkSize, this.file.size));
            await this.sendChunk(index, blob, received);
            received.add(index);
            this.onProgress(Math.min(99, Math.round((received.size / total) * 100)));
        }
    }

    async sendChunk(index, blob, received) {
        const { chunk_size: chunkSize } = this.session;

        for (let attempt = 0; ; attempt++) {
            if (this.stopped) throw new UploadAborted();

            const body = new FormData();
            body.append('chunk', blob, String(index));

            try {
                await axios.post(this.session.chunk_url.replace('__INDEX__', index), body, {
                    signal: this.controller.signal,
                    onUploadProgress: (event) => {
                        const done = received.size * chunkSize + (event.loaded / (event.total || blob.size)) * blob.size;
                        this.onProgress(Math.min(99, Math.round((done / this.file.size) * 100)));
                    },
                });

                return;
            } catch (error) {
                const retry = isRetryableError(error) || error?.response?.status === 422;
                if (this.stopped || !retry || attempt >= RETRIES) throw error;

                await sleep(1000 * 2 ** attempt);
            }
        }
    }

    async complete(completeData, received) {
        for (let attempt = 0; ; attempt++) {
            try {
                const { data } = await axios.post(this.session.complete_url, completeData, { signal: this.controller.signal });
                this.onProgress(100);

                return data;
            } catch (error) {
                const missing = error?.response?.data?.missing;

                if (missing?.length && attempt < 2) {
                    missing.forEach((index) => received.delete(index));
                    await this.sendChunks(received);
                    continue;
                }

                if (isRetryableError(error) && attempt < 2 && !this.stopped) {
                    await sleep(2000 * (attempt + 1));
                    continue;
                }

                throw error;
            }
        }
    }
}
