// The console bell's background check. No packages needed:
//   node --test "tests/js/*.test.mjs"
import { beforeEach, mock, test } from 'node:test';
import assert from 'node:assert/strict';

const listeners = { window: {}, document: {} };
const on = (bucket) => (type, fn) => { (listeners[bucket][type] ??= []).push(fn); };
const fire = (bucket, type, event = {}) => (listeners[bucket][type] ?? []).forEach((fn) => fn(event));

globalThis.window = {
    matchMedia: () => ({ matches: false }),
    addEventListener: on('window'),
    dispatchEvent: (event) => fire('window', event.type, event),
};
globalThis.document = {
    title: 'BookEase — Labnasan Resort',
    visibilityState: 'visible',
    addEventListener: on('document'),
};

const { default: consoleBell } = await import('../../resources/js/console-bell.js');

let responses; // what the next fetch() calls answer, in order
let calls;

globalThis.fetch = async (url, options) => {
    calls.push({ url, options });
    const { status = 200, body = { unread: 0, newest: null } } = responses.shift() ?? {};
    return { status, ok: status >= 200 && status < 300, json: async () => body, text: async () => '<ul></ul>' };
};

/** Let pending promise callbacks (fetch → json → update) run. */
async function settle() {
    for (let i = 0; i < 6; i++) await new Promise((resolve) => setImmediate(resolve));
}

function makeBell(options = {}) {
    const bell = consoleBell({ pollUrl: '/poll', panelUrl: '/panel', unread: 0, ...options });
    bell.$refs = {};
    bell.init();
    return bell;
}

const polls = () => calls.filter((call) => call.url === '/poll').length;

beforeEach(() => {
    mock.timers.reset();
    mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 0 });
    for (const bucket of Object.values(listeners)) {
        for (const type of Object.keys(bucket)) delete bucket[type];
    }
    document.title = 'BookEase — Labnasan Resort';
    document.visibilityState = 'visible';
    responses = [];
    calls = [];
});

test('checks for new alerts every 30 seconds and shares the count', async () => {
    const shared = [];
    window.addEventListener('console-unread', (event) => shared.push(event.detail));
    responses.push({ body: { unread: 2, newest: 'b' } });
    const bell = makeBell();

    mock.timers.tick(29_999);
    await settle();
    assert.equal(polls(), 0);

    mock.timers.tick(1);
    await settle();
    assert.equal(polls(), 1);
    assert.equal(calls[0].options.headers.Accept, 'application/json');
    assert.equal(bell.unread, 2);
    assert.deepEqual(shared, [2]);
    assert.equal(document.title, '(2) BookEase — Labnasan Resort');
    assert.equal(bell.announcement, '2 new notifications');
});

test('shows 9+ once there are more than nine', () => {
    const bell = makeBell({ unread: 12 });

    assert.equal(bell.badge(), '9+');
    assert.equal(document.title, '(9+) BookEase — Labnasan Resort');
});

test('stops for good when the session has ended', async () => {
    responses.push({ status: 401 });
    makeBell();

    mock.timers.tick(30_000);
    await settle();
    mock.timers.tick(120_000);
    await settle();

    // Coming back to the tab, or pressing a key, must not start it again.
    document.visibilityState = 'hidden';
    fire('document', 'visibilitychange');
    document.visibilityState = 'visible';
    fire('document', 'visibilitychange');
    fire('window', 'keydown');
    await settle();

    assert.equal(polls(), 1);
});

test('pauses after 30 minutes without input and resumes on the next input', async () => {
    makeBell();
    for (let i = 0; i < 61; i++) {
        mock.timers.tick(30_000);
        await settle();
    }
    const beforeIdle = polls();
    assert.equal(beforeIdle, 60); // the 61st tick finds nobody has touched the page for over 30 minutes

    mock.timers.tick(300_000);
    await settle();
    assert.equal(polls(), beforeIdle);

    fire('window', 'pointerdown');
    await settle();
    assert.equal(polls(), beforeIdle + 1);
});

test('waits while the tab is hidden and checks as soon as it is visible again', async () => {
    makeBell();

    document.visibilityState = 'hidden';
    fire('document', 'visibilitychange');
    mock.timers.tick(120_000);
    await settle();
    assert.equal(polls(), 0);

    document.visibilityState = 'visible';
    fire('document', 'visibilitychange');
    await settle();
    assert.equal(polls(), 1);
});

test('refreshes an open dropdown when a newer alert arrives', async () => {
    responses.push({ body: { unread: 1, newest: 'a' } });
    const bell = makeBell();
    bell.open = true;

    mock.timers.tick(30_000);
    await settle();

    assert.ok(calls.some((call) => call.url === '/panel'));
    assert.equal(bell.panelHtml, '<ul></ul>');
});
