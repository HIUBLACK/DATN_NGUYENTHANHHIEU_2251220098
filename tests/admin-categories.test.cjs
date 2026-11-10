const test = require('node:test');
const assert = require('node:assert/strict');
require('../public/admin-assets/js/admin-categories-domain.js');
const categories = globalThis.AdminCategories;

function seed() {
    return {
        occupations: [
            { id: 'NN-001', name: 'Phát triển phần mềm', status: 'active' },
            { id: 'NN-002', name: 'Dữ liệu và AI', status: 'inactive' }
        ],
        specialties: [{ id: 'CM-001', name: 'Backend Developer', occupationId: 'NN-001', status: 'active' }]
    };
}

test('occupation names reject whitespace and normalized duplicates including inactive entries', function () {
    const state = seed();
    for (const name of ['  ', ' PHÁT TRIỂN   PHẦN MỀM ', 'Dữ liệu và AI']) {
        assert.throws(() => categories.save(state, 'occupations', { name, status: 'active' }, null, '2026-11-10'));
    }
    assert.equal(state.occupations.length, 2);
});

test('specialties are unique within one group and must have an active parent for new data', function () {
    const state = seed();
    const value = { name: ' backend   developer ', occupationId: 'NN-001', status: 'active' };
    assert.throws(() => categories.validate(state, 'specialties', value), /đã tồn tại/);
    assert.throws(() => categories.validate(state, 'specialties', { ...value, occupationId: '' }), /chọn/);
    assert.throws(() => categories.validate(state, 'specialties', { ...value, occupationId: 'NN-002' }), /ẩn/);
    categories.toggle(state, 'occupations', 'NN-002');
    categories.save(state, 'specialties', { ...value, occupationId: 'NN-002' }, null, '2026-11-10');
    assert.equal(state.specialties.length, 2);
    assert.equal(state.specialties[0].id, 'CM-002');
});

test('group disabling is blocked from both form and toggle until all specialties are inactive', function () {
    const state = seed();
    assert.throws(() => categories.toggle(state, 'occupations', 'NN-001'), /còn chuyên môn/);
    assert.throws(() => categories.save(state, 'occupations', { ...state.occupations[0], status: 'inactive' }, 'NN-001'), /còn chuyên môn/);
    categories.toggle(state, 'specialties', 'CM-001');
    categories.toggle(state, 'occupations', 'NN-001');
    assert.equal(state.occupations[0].status, 'inactive');
    assert.equal(state.specialties.length, 1);
    assert.throws(() => categories.toggle(state, 'specialties', 'CM-001'), /ẩn/);
    categories.toggle(state, 'occupations', 'NN-001');
    categories.toggle(state, 'specialties', 'CM-001');
    assert.equal(state.specialties[0].status, 'active');
});

test('existing inactive specialty can be edited but cannot be moved to another inactive group', function () {
    const state = seed();
    categories.toggle(state, 'specialties', 'CM-001');
    categories.toggle(state, 'occupations', 'NN-001');
    const value = { ...state.specialties[0], description: 'Updated' };
    categories.save(state, 'specialties', value, 'CM-001');
    assert.equal(state.specialties[0].description, 'Updated');
    assert.throws(() => categories.save(state, 'specialties', { ...value, occupationId: 'NN-002' }, 'CM-001'), /ẩn/);
});
