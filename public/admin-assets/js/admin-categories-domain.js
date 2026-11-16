(function (root) {
    function normalize(value) {
        return String(value || '').normalize('NFKC').trim().replace(/\s+/g, ' ').toLocaleLowerCase('vi');
    }

    function validate(state, collection, value, id = null) {
        if (!['occupations', 'specialties', 'industries', 'skills', 'levels'].includes(collection)) {
            throw new Error('Danh mục không hợp lệ.');
        }
        const name = normalize(value.name);
        if (!name || value.name.length > 120) {
            throw new Error('Tên bắt buộc, tối đa 120 ký tự và không chỉ chứa khoảng trắng.');
        }
        if (!['active', 'inactive'].includes(value.status) || String(value.description || '').length > 1000) {
            throw new Error('Trạng thái không hợp lệ hoặc mô tả vượt quá 1.000 ký tự.');
        }
        const item = state[collection].find(entry => entry.id === id);
        if (id && !item) {
            throw new Error('Danh mục không còn tồn tại.');
        }
        const duplicate = state[collection].some(entry => entry.id !== id && normalize(entry.name) === name
            && (collection !== 'specialties' || entry.occupationId === value.occupationId));
        if (duplicate) {
            throw new Error(collection !== 'specialties' ? 'Tên danh mục đã tồn tại, kể cả mục ẩn.' : 'Tên chuyên môn đã tồn tại trong nhóm nghề này, kể cả chuyên môn ẩn.');
        }
        if (collection !== 'specialties' && collection !== 'occupations') {
            return;
        }
        if (collection === 'occupations') {
            if (value.status === 'inactive' && state.specialties.some(specialty => specialty.occupationId === id && specialty.status === 'active')) {
                throw new Error('Không thể ẩn nhóm nghề còn chuyên môn đang hiện. Hãy ẩn hoặc chuyển các chuyên môn đó trước.');
            }
            return;
        }
        const parent = state.occupations.find(group => group.id === value.occupationId);
        if (!parent) {
            throw new Error('Vui lòng chọn một nhóm nghề trực thuộc.');
        }
        // Cho phép sửa mô tả của dữ liệu cũ, không cho tạo/chuyển/hiện dưới nhóm đã tắt.
        if (parent.status !== 'active' && (!item || item.occupationId !== parent.id || value.status === 'active')) {
            throw new Error('Nhóm nghề đang ẩn. Hãy hiện nhóm trước hoặc chọn nhóm đang hiện.');
        }
    }

    function save(state, collection, value, id, created) {
        validate(state, collection, value, id);
        const fields = { name: value.name.trim().replace(/\s+/g, ' '), description: (value.description || '').trim(), status: value.status };
        if (collection === 'specialties') {
            fields.occupationId = value.occupationId;
        }
        const item = state[collection].find(entry => entry.id === id);
        if (item) {
            Object.assign(item, fields);
            return;
        }
        if (['industries', 'skills', 'levels'].includes(collection)) {
            fields.filterValue = fields.name;
        }
        const prefix = { occupations: 'NN-', specialties: 'CM-', industries: 'LV-', skills: 'KN-', levels: 'CB-' }[collection];
        const number = Math.max(0, ...state[collection].map(entry => Number(entry.id.slice(3)) || 0)) + 1;
        state[collection].unshift({ id: prefix + String(number).padStart(3, '0'), created, ...fields });
    }

    function toggle(state, collection, id) {
        const item = state[collection].find(entry => entry.id === id);
        if (!item) {
            throw new Error('Không tìm thấy danh mục.');
        }
        const value = { ...item, status: item.status === 'active' ? 'inactive' : 'active' };
        validate(state, collection, value, id);
        item.status = value.status;
    }

    root.AdminCategories = { validate, save, toggle };
})(typeof window === 'undefined' ? globalThis : window);
