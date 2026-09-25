const STORAGE_KEY = 'admin-last-activity';
const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'];

const readShared = () => {
    try {
        return Number(localStorage.getItem(STORAGE_KEY)) || 0;
    } catch {
        return 0;
    }
};

const writeShared = (value) => {
    try {
        localStorage.setItem(STORAGE_KEY, String(value));
    } catch {}
};

export default function registerIdle(Alpine) {
    Alpine.data('idleWatcher', ({ minutes, pingUrl, warnSeconds = 60 }) => ({
        idleMs: minutes * 60 * 1000,
        warnMs: Math.min(warnSeconds * 1000, minutes * 60 * 1000 - 5000),
        last: Date.now(),
        lastPing: Date.now(),
        warning: false,
        secondsLeft: warnSeconds,
        timer: null,
        signingOut: false,

        init() {
            if (!minutes) return;

            this.last = Math.max(readShared(), Date.now());
            writeShared(this.last);

            const onActivity = () => this.activity();
            ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, onActivity, { passive: true }));

            window.addEventListener('storage', (event) => {
                if (event.key === STORAGE_KEY) {
                    this.last = Number(event.newValue) || this.last;
                    if (this.warning && this.remaining() > this.warnMs) this.warning = false;
                }
            });

            this.timer = setInterval(() => this.tick(), 1000);
        },

        remaining() {
            return this.last + this.idleMs - Date.now();
        },

        activity() {
            if (this.warning || this.signingOut) return;

            const now = Date.now();
            if (now - this.last < 5000) return;

            this.last = now;
            writeShared(now);

            if (now - this.lastPing > 60000) this.ping();
        },

        async ping() {
            this.lastPing = Date.now();

            try {
                const response = await fetch(pingUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                    },
                });

                if (response.status === 401 || response.status === 419) this.signOut();
            } catch {}
        },

        tick() {
            this.last = Math.max(this.last, readShared());
            const remaining = this.remaining();

            if (remaining <= -3000) {
                this.signOut();
            } else if (remaining <= this.warnMs) {
                this.warning = true;
                this.secondsLeft = Math.max(0, Math.ceil(remaining / 1000));
            } else {
                this.warning = false;
            }
        },

        stay() {
            this.warning = false;
            this.last = Date.now();
            writeShared(this.last);
            this.ping();
        },

        signOut() {
            if (this.signingOut) return;
            this.signingOut = true;
            clearInterval(this.timer);
            window.location.reload();
        },
    }));
}
