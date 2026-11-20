const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('required errors turn red, corrections clear them, and toast hides after five seconds', () => {
    const listeners = {};
    const classes = new Set();
    const toast = { hidden: true, textContent: '' };
    let timeout;
    let duration;
    const input = {
        willValidate: true, required: true, value: '', validity: { valid: false },
        classList: {
            toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
            contains(name) { return classes.has(name); },
        },
        setAttribute() {}, removeAttribute() {}, focus() {},
    };
    const window = {};
    const document = {
        getElementById(id) { return id === 'validation-toast' ? toast : null; },
        addEventListener(name, handler) { listeners[name] = handler; },
    };
    vm.runInNewContext(fs.readFileSync('public/js/form-validation.js', 'utf8'), {
        window, document, clearTimeout() {},
        setTimeout(callback, milliseconds) { timeout = callback; duration = milliseconds; },
    });
    assert.equal(window.ViejobValidation.validate({ elements: [input] }), false);
    assert.equal(classes.has('field-validation-error'), true);
    assert.equal(toast.hidden, false);
    assert.equal(duration, 5000);
    timeout();
    assert.equal(toast.hidden, true);
    input.value = 'Valid text';
    input.validity.valid = true;
    listeners.input({ target: input });
    assert.equal(classes.has('field-validation-error'), false);
    assert.equal(window.ViejobValidation.validate({ elements: [input] }), true);
});
