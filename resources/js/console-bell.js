/**
 * Console bell (staff + owner): checks for new alerts every 30 seconds.
 *
 * It only checks while the tab is visible, pauses after 30 minutes without
 * any input (so an unattended screen can still sign itself out), and stops
 * for good once the session has ended (401/419, or a redirect to sign in) or
 * the account may no longer see alerts (403). Opening the dropdown after the
 * session ended reloads the page, which goes to sign-in.
 *
 * It rings when the newest unread alert is newer than any it has seen (the
 * server sends it as "id@unix-time"), not when the count rises: an alert read
 * in another tab must not hide one that arrived in the same 30 seconds.
 *
 *   <div x-data="consoleBell({ pollUrl: '…', panelUrl: '…', unread: 3, newest: 'id@1700000000' })">…</div>
 */
const INPUT_EVENTS = ['pointerdown', 'keydown', 'scroll', 'touchstart'];
const TITLE_PREFIX = /^\(\d+\+?\)\s/;

/** fetch() follows redirects, so a sign-in page can come back as a plain 200. */
const signedOut = (response) => response.status === 401 || response.status === 419 || response.redirected;

/** The time part of an "id@unix-time" marker, or null when there is none. */
const stampOf = (marker) => {
    const stamp = Number(String(marker ?? '').split('@')[1]);
    return Number.isFinite(stamp) ? stamp : null;
};

export default function consoleBell({ pollUrl, panelUrl, unread = 0, newest = null, interval = 30000, idleLimit = 1800000 }) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const headers = (accept) => ({ Accept: accept, 'X-Requested-With': 'XMLHttpRequest' });

    return {
        unread,
        newest,
        latestStamp: stampOf(newest) ?? -Infinity, // newest alert time the bell has already rung (or loaded) for
        open: false,
        panelHtml: '',
        loading: false,
        announcement: '',
        timer: null,
        idle: false,
        stopped: false,
        lastInput: Date.now(),
        baseTitle: document.title.replace(TITLE_PREFIX, ''),

        init() {
            const onInput = () => {
                this.lastInput = Date.now();
                if (this.idle && !this.stopped) {
                    this.idle = false;
                    this.poll();
                }
            };
            INPUT_EVENTS.forEach((type) => window.addEventListener(type, onInput, { passive: true }));

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.poll();
                } else {
                    this.clear();
                }
            });

            this.updateTitle();
            this.schedule();
        },

        badge() {
            return this.unread > 9 ? '9+' : String(this.unread);
        },

        schedule() {
            this.clear();
            if (this.stopped || document.visibilityState !== 'visible') return;
            this.timer = setTimeout(() => this.poll(), interval);
        },

        clear() {
            clearTimeout(this.timer);
            this.timer = null;
        },

        async poll() {
            if (this.stopped || document.visibilityState !== 'visible') return;

            if (Date.now() - this.lastInput > idleLimit) {
                this.idle = true; // the next input resumes checking
                this.clear();
                return;
            }

            try {
                const response = await fetch(pollUrl, { credentials: 'same-origin', headers: headers('application/json') });
                if (signedOut(response) || response.status === 403) {
                    this.stop();
                    return;
                }
                if (response.ok) this.receive(await response.json());
            } catch {
                // Offline or the server is busy: the next check tries again.
            }

            this.schedule();
        },

        receive({ unread: count, newest }) {
            const before = this.unread;
            const changed = newest !== this.newest;
            const stamp = stampOf(newest);
            // Same second, different alert: still new if the count went up.
            const arrived = newest !== null && changed && (stamp === null
                || stamp > this.latestStamp
                || (stamp === this.latestStamp && count > before));

            this.unread = count;
            this.newest = newest;
            if (stamp !== null) this.latestStamp = Math.max(this.latestStamp, stamp);

            if (arrived) this.ring(Math.max(1, count - before));
            this.updateTitle();
            window.dispatchEvent(new CustomEvent('console-unread', { detail: count }));

            if (this.open && changed) this.loadPanel();
        },

        ring(added) {
            this.announcement = `${added} new notification${added === 1 ? '' : 's'}`;

            const bell = this.$refs.bell;
            if (!bell || reduceMotion.matches) return;
            bell.classList.remove('bell-ring');
            void bell.offsetWidth; // restart the animation if it is still running
            bell.classList.add('bell-ring');
        },

        updateTitle() {
            document.title = this.unread > 0 ? `(${this.badge()}) ${this.baseTitle}` : this.baseTitle;
        },

        toggle() {
            this.open = !this.open;
            if (this.open) this.loadPanel();
        },

        async loadPanel() {
            this.loading = true;
            try {
                const response = await fetch(panelUrl, { credentials: 'same-origin', headers: headers('text/html') });
                if (signedOut(response)) {
                    // Reloading lands on the sign-in page, which returns here afterwards.
                    this.stop();
                    window.location.reload();
                    return;
                }
                if (response.ok) this.panelHtml = await response.text();
            } catch {
                // Keep what is shown; "See all" still works.
            } finally {
                this.loading = false;
            }
        },

        stop() {
            this.stopped = true;
            this.clear();
        },
    };
}
