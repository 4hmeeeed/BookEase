/**
 * Console bell (staff + owner): checks for new alerts every 30 seconds.
 *
 * It only checks while the tab is visible, pauses after 30 minutes without
 * any input (so an unattended screen can still sign itself out), and stops
 * for good once the session has ended (401/419, or a redirect to sign in).
 * Opening the dropdown at that point reloads the page, which goes to sign-in.
 *
 *   <div x-data="consoleBell({ pollUrl: '…', panelUrl: '…', unread: 3 })">…</div>
 */
const INPUT_EVENTS = ['pointerdown', 'keydown', 'scroll', 'touchstart'];
const TITLE_PREFIX = /^\(\d+\+?\)\s/;

/** fetch() follows redirects, so a sign-in page can come back as a plain 200. */
const signedOut = (response) => response.status === 401 || response.status === 419 || response.redirected;

export default function consoleBell({ pollUrl, panelUrl, unread = 0, interval = 30000, idleLimit = 1800000 }) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const headers = (accept) => ({ Accept: accept, 'X-Requested-With': 'XMLHttpRequest' });

    return {
        unread,
        newest: null,
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
                if (signedOut(response)) {
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

            this.unread = count;
            this.newest = newest;

            if (count > before) this.ring(count - before);
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
