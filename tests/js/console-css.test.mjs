// Console CSS rules that no PHP test can see. Runs with the bell tests:
//   node --test "tests/js/*.test.mjs"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const css = readFileSync(new URL('../../resources/css/app.css', import.meta.url), 'utf8');

/** The declarations of the first `selector { … }` block in app.css. */
function declarations(selector) {
    const start = css.indexOf(`${selector} {`);
    assert.notEqual(start, -1, `${selector} is missing from app.css`);
    return css.slice(start, css.indexOf('}', start));
}

test('the console fade-in lets go when it ends, so dialogs open above the sidebar and top bar', () => {
    // A "both" or "forwards" fill keeps the finished animation in effect, and with it a
    // stacking context around the page: x-modal's z-50 would only count inside it.
    const animation = declarations('.console-enter').match(/animation:\s*([^;]+);/)?.[1];

    assert.ok(animation, '.console-enter has no animation');
    assert.doesNotMatch(animation, /\b(both|forwards)\b/);
});
