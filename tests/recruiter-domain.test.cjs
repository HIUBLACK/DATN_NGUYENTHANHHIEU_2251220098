const test = require('node:test');
const assert = require('node:assert/strict');
require('../public/recruiter/js/recruiter-domain.js');
const domain = globalThis.RecruiterDomain;

const allowed = {
    applied: ['reviewing', 'rejected'],
    reviewing: ['interview', 'rejected'],
    interview: ['hired', 'rejected'],
    hired: [],
    rejected: [],
    withdrawn: []
};

test('application transitions reject all forbidden paths and never set withdrawn', function () {
    for (const [initial, destinations] of Object.entries(allowed)) {
        for (const destination of Object.keys(allowed)) {
            const application = { status: initial, history: [] };
            if (destinations.includes(destination)) {
                domain.transitionApplication(application, destination, 'demo time');
                assert.equal(application.status, destination);
                assert.equal(application.history.length, 1);
            } else {
                assert.throws(() => domain.transitionApplication(application, destination, 'demo time'));
                assert.equal(application.status, initial);
                assert.deepEqual(application.history, []);
            }
        }
    }
});

test('closed, blocked, expired and unpublished jobs cannot reopen', function () {
    for (const activity of ['closed', 'blocked', 'draft']) {
        const job = { activity, published: { deadline: '2027-01-01' }, content: {} };
        assert.throws(() => domain.transitionJob(job, 'reopen', '2026-11-04'));
    }
    const expired = { activity: 'paused', published: { deadline: '2026-11-03' }, content: { deadline: '2027-01-01' } };
    assert.throws(() => domain.transitionJob(expired, 'reopen', '2026-11-04'));
    const unpublished = { activity: 'paused', published: null, content: { deadline: '2027-01-01' } };
    assert.throws(() => domain.transitionJob(unpublished, 'reopen', '2026-11-04'));
});

test('published deadline controls reopening while a replacement awaits review', function () {
    const job = { activity: 'paused', moderation: 'pending', published: { deadline: '2026-12-01' }, content: { deadline: '2026-12-20' } };
    domain.transitionJob(job, 'reopen', '2026-11-04');
    assert.equal(job.activity, 'open');
    assert.equal(job.published.deadline, '2026-12-01');
    assert.equal(job.moderation, 'pending');
});
