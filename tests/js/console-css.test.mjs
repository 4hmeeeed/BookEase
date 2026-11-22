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

test('console helpers sit inside @layer components, so utilities can override them', () => {
    // Outside a layer, `.ref-code` would beat utilities such as `text-sm` or `font-mono`.
    /** The at-rule heads (e.g. "@layer components") of the blocks enclosing `selector`. */
    const code = css.replace(/\/\*[\s\S]*?\*\//g, '');
    const enclosing = (selector) => {
        const at = code.indexOf(`${selector} {`);
        assert.notEqual(at, -1, `${selector} is missing from app.css`);
        const open = [];
        for (let i = 0; i < at; i++) {
            if (code[i] === '{') open.push(code.slice(Math.max(code.lastIndexOf('}', i), code.lastIndexOf(';', i), code.lastIndexOf('{', i - 1)) + 1, i).trim());
            else if (code[i] === '}') open.pop();
        }
        return open.filter((head) => head.startsWith('@'));
    };

    for (const selector of ['.ref-code', '.bell-ring', '.console-enter']) {
        assert.ok(enclosing(selector).includes('@layer components'), `${selector} is outside @layer components`);
    }
});
