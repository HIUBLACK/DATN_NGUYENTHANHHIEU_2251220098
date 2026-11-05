const test = require('node:test');
const assert = require('node:assert/strict');
require('../public/admin-assets/js/admin-domain.js');
const domain = globalThis.AdminDomain;

function seed() {
    return {
        currentAdminId: '1',
        users: [{ id: '1', role: 'admin', name: 'Admin', status: 'active', history: [] }, { id: '2', role: 'admin', status: 'active', history: [] }, { id: '3', role: 'candidate', status: 'active', history: [] }],
        companies: [{ id: '1', status: 'active', history: [] }],
        jobs: [{ id: '1', companyId: '1', activity: 'open', moderation: 'pending', version: 2, publishedVersion: 1, content: { title: 'New', deadline: '2099-01-01' }, published: { title: 'Old', deadline: '2099-01-01' }, history: [] }],
        skills: [{ id: '1', name: 'JavaScript', normalized: 'javascript' }, { id: '2', name: 'Git', normalized: 'git' }],
        aliases: [{ id: '1', name: 'JS', skillId: '1', status: 'inactive' }]
    };
}

test('admin accounts and blank lock reasons are protected', function () {
    const state = seed();
    for (const id of ['1', '2']) {
        assert.throws(() => domain.applyAction(state, 'users', id, 'lock', 'Reason'), /Admin/);
    }
    assert.throws(() => domain.applyAction(state, 'users', '3', 'lock', '  '), /lý do/);
    domain.applyAction(state, 'users', '3', 'lock', 'Demo');
    domain.applyAction(state, 'users', '3', 'unlock');
    assert.equal(state.users[2].history.length, 2);
    assert.equal(state.users[2].status, 'active');
});

test('reject keeps published content and only pending versions can be moderated', function () {
    const state = seed();
    assert.throws(() => domain.applyAction(state, 'jobs', '1', 'reject', ' '), /lý do/);
    domain.applyAction(state, 'jobs', '1', 'reject', 'Needs detail');
    assert.equal(state.jobs[0].published.title, 'Old');
    assert.equal(state.jobs[0].activity, 'open');
    assert.throws(() => domain.applyAction(state, 'jobs', '1', 'approve'), /chờ duyệt/);
});

test('approval copies new version but never opens a closed or blocked job', function () {
    for (const status of ['closed', 'blocked']) {
        const state = seed();
        state.jobs[0].activity = status;
        domain.applyAction(state, 'jobs', '1', 'approve');
        assert.equal(state.jobs[0].published.title, 'New');
        assert.equal(state.jobs[0].publishedVersion, 2);
        assert.equal(state.jobs[0].activity, status);
    }
});

test('unlock restores closed state and cannot open expired or unpublished jobs', function () {
    const state = seed();
    const job = state.jobs[0];
    job.activity = 'closed';
    domain.applyAction(state, 'jobs', '1', 'lock', 'Demo');
    domain.applyAction(state, 'jobs', '1', 'unlock');
    assert.equal(job.activity, 'closed');
    job.activity = 'open';
    job.published.deadline = '2000-01-01';
    domain.applyAction(state, 'jobs', '1', 'lock', 'Demo');
    domain.applyAction(state, 'jobs', '1', 'unlock');
    assert.equal(job.activity, 'paused');
    assert.equal(job.moderation, 'pending');
});

test('company lock leaves individual job and moderation states intact', function () {
    const state = seed();
    const original = structuredClone(state.jobs);
    domain.applyAction(state, 'companies', '1', 'lock', 'Demo');
    domain.applyAction(state, 'companies', '1', 'unlock');
    assert.deepEqual(state.jobs, original);
});

test('aliases reject normalized collisions without merging distinct skills', function () {
    const state = seed();
    for (const name of [' javascript ', ' js ', 'Git']) {
        assert.throws(() => domain.validateAlias(state, { name, skillId: '1' }), /trùng/);
    }
    assert.doesNotThrow(() => domain.validateSkill(state, { name: 'GitHub', normalized: 'github' }));
    assert.throws(() => domain.validateSkill(state, { name: 'JavaScript', normalized: 'other' }), /trùng/);
    assert.doesNotThrow(() => domain.validateAlias(state, { name: 'JS', skillId: '1' }, '1'));
});
