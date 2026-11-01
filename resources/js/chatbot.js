/**
 * Floating inquiry chatbot (Alpine.data('chatbot')).
 *
 * Answers are always rendered with x-text, never as HTML, so nothing the
 * model returns can inject markup. The transcript is kept in sessionStorage
 * only as a convenience while browsing; the server keeps the real log.
 */
const STORAGE_KEY = 'bookease.chat.v1';
const MAX_KEPT = 30;

const storage = {
    read() {
        try {
            const raw = window.sessionStorage.getItem(STORAGE_KEY);
            const data = raw ? JSON.parse(raw) : null;
            return Array.isArray(data) ? data : [];
        } catch {
            return [];
        }
    },
    write(messages) {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, JSON.stringify(messages.slice(-MAX_KEPT)));
        } catch {
            /* storage blocked or full: the chat still works for this page */
        }
    },
    clear() {
        try {
            window.sessionStorage.removeItem(STORAGE_KEY);
        } catch {
            /* ignore */
        }
    },
};

export default ({ endpoint, maxLength = 500 }) => ({
    open: false,
    draft: '',
    sending: false,
    messages: [],
    maxLength,
    nextId: 1,

    init() {
        this.messages = storage.read();
        this.nextId = this.messages.reduce((max, m) => Math.max(max, m.id || 0), 0) + 1;
    },

    get remaining() {
        return this.maxLength - this.draft.length;
    },

    toggle() {
        this.open ? this.close() : this.show();
    },

    show() {
        this.open = true;
        this.$nextTick(() => {
            this.scrollToEnd();
            this.$refs.input?.focus();
        });
    },

    close() {
        this.open = false;
        this.$nextTick(() => this.$refs.launcher?.focus());
    },

    reset() {
        this.messages = [];
        storage.clear();
        this.$refs.input?.focus();
    },

    push(role, text, extra = {}) {
        this.messages.push({ id: this.nextId++, role, text, ...extra });
        storage.write(this.messages);
        this.$nextTick(() => this.scrollToEnd());
    },

    scrollToEnd() {
        const log = this.$refs.log;
        if (log) log.scrollTo({ top: log.scrollHeight, behavior: 'smooth' });
    },

    ask(text) {
        this.draft = text;
        this.send();
    },

    async send() {
        const question = this.draft.trim();
        if (!question || this.sending) return;

        if (question.length > this.maxLength) {
            this.push('error', `Please keep your question under ${this.maxLength} characters.`);
            return;
        }

        this.draft = '';
        this.push('user', question);
        this.sending = true;

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ question }),
            });

            const data = await response.json().catch(() => ({}));

            if (response.ok && typeof data.answer === 'string') {
                this.push('bot', data.answer, { fallback: Boolean(data.fallback) });
            } else if (response.status === 429) {
                this.push('error', data.message || 'You’re sending questions quickly. Please wait a moment.');
            } else if (response.status === 419) {
                this.push('error', 'Your session expired. Please refresh the page and ask again.');
            } else if (response.status === 422) {
                this.push('error', data.errors?.question?.[0] || 'Please check your question and try again.');
            } else {
                this.push('error', 'Something went wrong. Please try again, or contact the resort directly.');
            }
        } catch {
            this.push('error', 'You seem to be offline. Please check your connection and try again.');
        } finally {
            this.sending = false;
            this.$nextTick(() => this.$refs.input?.focus());
        }
    },
});
