const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(baseline = null, valid = true, draft = false) {
    const elements = {};
    function element() {
        return {
            setCustomValidity() {}, value: '', tagName: 'INPUT', textContent: '', className: '', children: [], listeners: {},
            addEventListener(name, handler) { this.listeners[name] = handler; },
            append(child) { this.children.push(child); },
            replaceChildren() { this.children = []; },
        };
    }
    const fields = ['title', 'job_position_id', 'industry_id', 'job_specialization_id', 'job_level_id', 'skills',
        'salary_min', 'salary_max', 'province_code', 'location', 'work_mode', 'employment_type',
        'quantity', 'application_deadline', 'description', 'requirements', 'benefits'];
    [...fields, 'real-job-form', 'job-review-modal', 'job-review-baseline', 'job-review-open',
        'job-review-confirm', 'job-intent', 'job-review-content', 'job-review-source'].forEach(id => { elements[id] = element(); });
    elements['job-review-baseline'].textContent = JSON.stringify(baseline);
    if (draft) elements['job-save-draft'] = element();
    fields.forEach(id => { elements[id].id = id; elements[id].required = true; });
    const form = elements['real-job-form'];
    let requests = 0;
    let visible = false;
    form.checkValidity = () => true;
    form.querySelectorAll = selector => selector === '[required]' ? fields.map(id => elements[id]).filter(input => input.required) : [];
    form.requestSubmit = () => {
        let prevented = false;
        form.listeners.submit({ preventDefault() { prevented = true; } });
        if (!prevented) requests++;
    };
    const modal = { show() { visible = true; }, hide() { visible = false; } };
    const bootstrap = { Modal: { getOrCreateInstance() { return modal; } } };
    let ready;
    const document = {
        getElementById(id) { return elements[id]; },
        createElement: element,
        addEventListener(name, callback) { ready = callback; },
    };
    vm.runInNewContext(fs.readFileSync('public/recruiter/js/job-review.js', 'utf8'), {
        document, bootstrap, window: { bootstrap, ViejobValidation: { validate() { return typeof valid === 'function' ? valid(elements) : valid; }, notify() {} }, addEventListener() {} }, Intl,
    });
    ready();
    return { elements, modal, requests: () => requests, visible: () => visible };
}

function renderedText(element) {
    return element.textContent + element.children.map(renderedText).join('\n');
}

test('create draft needs only title, skips modal and prevents duplicate clicks', () => {
    const app = setup(null, elements => Object.values(elements).every(input => !input.required || input.value), true);
    app.elements.title.value = 'Only title';
    app.elements['job-review-open'].listeners.click();
    assert.equal(app.visible(), false);
    app.elements['job-save-draft'].listeners.click();
    app.elements['job-save-draft'].listeners.click();
    assert.equal(app.requests(), 1);
    assert.equal(app.elements['job-intent'].value, 'draft');
    assert.equal(app.visible(), false);
    assert.equal(app.elements.description.required, true);
});

test('opening and returning preserve unsaved text; confirmation submits only once', () => {
    const app = setup();
    const e = app.elements;
    e.title.value = '<img src=x onerror=alert(1)> Unsaved title';
    e['job-review-open'].listeners.click();
    assert.equal(app.visible(), true);
    assert.equal(app.requests(), 0);
    assert.match(renderedText(e['job-review-content']), /<img src=x onerror=alert\(1\)> Unsaved title/);
    app.modal.hide();
    assert.equal(app.requests(), 0);
    assert.match(e.title.value, /Unsaved title/);
    e['job-review-open'].listeners.click();
    e['job-review-confirm'].listeners.click();
    e['job-review-confirm'].listeners.click();
    assert.equal(app.requests(), 1);
    assert.equal(e['job-intent'].value, 'submit');
});

test('comparison marks changed fields and requires renewed confirmation if the form changes', () => {
    const app = setup({ label: 'V1', values: { title: 'Old title', description: 'Old description' }, labels: {} });
    const e = app.elements;
    e.title.value = 'New title';
    e['job-review-open'].listeners.click();
    assert.match(e['job-review-content'].children[0].className, /is-changed/);
    const text = renderedText(e['job-review-content']);
    assert.match(text, /Old title/);
    assert.match(text, /New title/);
    assert.match(text, /Old description/);
    e.title.value = 'Final title';
    e['job-review-confirm'].listeners.click();
    assert.equal(app.requests(), 0);
    assert.match(renderedText(e['job-review-content']), /Final title/);
    e['job-review-confirm'].listeners.click();
    assert.equal(app.requests(), 1);
});

test('invalid form cannot open review or confirm a submission', () => {
    const app = setup(null, false);
    app.elements['job-review-open'].listeners.click();
    assert.equal(app.visible(), false);
    assert.equal(app.requests(), 0);
    app.elements['job-review-confirm'].listeners.click();
    app.elements['job-review-confirm'].listeners.click();
    assert.equal(app.requests(), 0);
});

test('reversed salary and unchanged edits do not open confirmation', () => {
    const app = setup();
    app.elements.salary_min.value = '20000000';
    app.elements.salary_max.value = '10000000';
    app.elements['job-review-open'].listeners.click();
    assert.equal(app.visible(), false);
    const unchanged = setup({ label: 'V1', values: {}, savedValues: {}, labels: {} });
    unchanged.elements['job-review-open'].listeners.click();
    assert.equal(unchanged.visible(), false);
    assert.equal(unchanged.requests(), 0);
});
