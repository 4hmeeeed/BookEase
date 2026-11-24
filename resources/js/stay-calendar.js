/**
 * Availability calendar: Alpine.data('stayCalendar').
 *
 * Mirrors the server's half-open rule for the UI only. The server is still
 * the authority (quote endpoint now, locking reserve() at booking time).
 *
 *   – a "booked" day means that NIGHT is held
 *   – check-in must be a free night
 *   – check-out may be the first night of someone else's stay (changeover)
 *   – every night in [check-in, check-out) must be free
 *
 * Dates are 'YYYY-MM-DD' strings handled in UTC so the visitor's timezone
 * can never shift a day.
 */

const toUTC = (ymd) => {
    const [y, m, d] = ymd.split('-').map(Number);
    return Date.UTC(y, m - 1, d);
};
const fromUTC = (ms) => new Date(ms).toISOString().slice(0, 10);
const addDays = (ymd, n) => fromUTC(toUTC(ymd) + n * 86400000);
const diffDays = (a, b) => Math.round((toUTC(b) - toUTC(a)) / 86400000);
const monthKey = (ymd) => ymd.slice(0, 7);
const shiftMonth = (ym, n) => {
    const [y, m] = ym.split('-').map(Number);
    const d = new Date(Date.UTC(y, m - 1 + n, 1));
    return d.toISOString().slice(0, 7);
};

export default (config) => ({
    today: config.today,
    maxNights: config.maxNights,
    lastCheckIn: addDays(config.today, config.windowDays),
    firstCheckIn: addDays(config.today, config.minAdvanceDays || 0),

    month: monthKey(config.checkIn || config.today),
    direction: 1,
    booked: new Set(),
    loadedMonths: new Set(),
    loading: false,
    loadError: false,

    checkIn: null,
    checkOut: null,
    hover: null,

    quote: null,
    quoteLoading: false,
    quoteError: null,

    async init() {
        await this.ensureMonths(this.month);

        if (config.checkIn && config.checkOut) {
            this.checkIn = config.checkIn;
            this.checkOut = config.checkOut;
            this.fetchQuote();
        }
    },

    // ---------- data ----------
    async ensureMonths(ym) {
        // Load this month and the next, so stays that cross a month edge validate correctly.
        await Promise.all([ym, shiftMonth(ym, 1)].map((m) => this.loadMonth(m)));
    },

    async loadMonth(ym) {
        if (this.loadedMonths.has(ym)) return;
        this.loading = true;
        this.loadError = false;
        try {
            const res = await fetch(`${config.availabilityUrl}?month=${ym}`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const data = await res.json();
            data.booked.forEach((d) => this.booked.add(d));
            this.loadedMonths.add(ym);
        } catch {
            this.loadError = true;
        } finally {
            this.loading = false;
        }
    },

    async fetchQuote() {
        if (!this.checkIn || !this.checkOut) return;
        this.quoteLoading = true;
        this.quoteError = null;
        try {
            const params = new URLSearchParams({ check_in: this.checkIn, check_out: this.checkOut });
            const res = await fetch(`${config.quoteUrl}?${params}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (!data.ok) {
                this.quote = null;
                this.quoteError = data.message;
            } else {
                this.quote = data;
                if (!data.available) this.quoteError = data.message;
            }
        } catch {
            this.quoteError = 'We couldn’t check the price just now. Please try again.';
        } finally {
            this.quoteLoading = false;
        }
    },

    // ---------- navigation ----------
    get canGoBack() {
        return this.month > monthKey(this.today);
    },
    get canGoForward() {
        return this.month < monthKey(this.lastCheckIn);
    },
    async go(n) {
        this.direction = n;
        this.month = shiftMonth(this.month, n);
        await this.ensureMonths(this.month);
    },

    get monthLabel() {
        return new Date(toUTC(`${this.month}-01`)).toLocaleDateString('en-PH', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    },

    /** 6×7 grid; null = padding cell */
    get days() {
        const first = `${this.month}-01`;
        const startPad = new Date(toUTC(first)).getUTCDay(); // 0 = Sunday
        const count = diffDays(first, `${shiftMonth(this.month, 1)}-01`);
        const cells = Array(startPad).fill(null);
        for (let i = 0; i < count; i++) cells.push(addDays(first, i));
        return cells;
    },

    // ---------- rules ----------
    isPast(d) {
        return d < this.today;
    },
    isBooked(d) {
        return this.booked.has(d);
    },
    /** First held night on or after `from` (within loaded data), or null. */
    nextBookedAfter(from) {
        let next = null;
        this.booked.forEach((d) => {
            if (d >= from && (next === null || d < next)) next = d;
        });
        return next;
    },
    /** Latest allowed check-out for the current check-in. */
    get maxCheckOut() {
        if (!this.checkIn) return null;
        const byNights = addDays(this.checkIn, this.maxNights);
        const blocker = this.nextBookedAfter(addDays(this.checkIn, 1));
        return blocker && blocker < byNights ? blocker : byNights;
    },
    selectingCheckOut() {
        return this.checkIn && !this.checkOut;
    },
    canCheckIn(d) {
        return !this.isPast(d) && !this.isBooked(d) && d >= this.firstCheckIn && d <= this.lastCheckIn;
    },
    canCheckOut(d) {
        return this.checkIn && d > this.checkIn && d <= this.maxCheckOut;
    },
    isDisabled(d) {
        if (this.selectingCheckOut()) return !this.canCheckOut(d) && !this.canCheckIn(d);
        return !this.canCheckIn(d);
    },

    // ---------- selection ----------
    pick(d) {
        if (this.selectingCheckOut() && this.canCheckOut(d)) {
            this.checkOut = d;
            this.hover = null;
            this.fetchQuote();
            return;
        }
        if (this.canCheckIn(d)) {
            this.checkIn = d;
            this.checkOut = null;
            this.quote = null;
            this.quoteError = null;
        }
    },
    clear() {
        this.checkIn = this.checkOut = this.hover = this.quote = this.quoteError = null;
    },

    /** End of the range being shown (chosen or hovered check-out). */
    get rangeEnd() {
        if (this.checkOut) return this.checkOut;
        if (this.selectingCheckOut() && this.hover && this.canCheckOut(this.hover)) return this.hover;
        return null;
    },
    inRange(d) {
        return this.checkIn && this.rangeEnd && d > this.checkIn && d < this.rangeEnd;
    },

    dayClasses(d) {
        const start = d === this.checkIn;
        const end = d === this.rangeEnd;
        return {
            'is-start': start,
            'is-end': end,
            'has-range': start && !!this.rangeEnd,
            'is-in-range': this.inRange(d),
            'is-booked': this.isBooked(d) && !end,
            'is-changeover': this.isBooked(d) && end,
            'is-today': d === this.today,
            'is-disabled': this.isDisabled(d) && !start && !end,
        };
    },

    get nights() {
        return this.checkIn && this.checkOut ? diffDays(this.checkIn, this.checkOut) : 0;
    },

    pretty(d) {
        return d ? new Date(toUTC(d)).toLocaleDateString('en-PH', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' }) : '—';
    },
});
