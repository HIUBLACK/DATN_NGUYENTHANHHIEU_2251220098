window.setupAdminCategories = function (context, list) {
    const form = document.getElementById('admin-category-form');
    if (!form) {
        return;
    }
    const modalElement = document.getElementById('admin-category-modal');
    const modal = new bootstrap.Modal(modalElement);
    const error = document.getElementById('category-error');
    const warning = document.getElementById('category-warning');
    const isSpecialty = list.collection === 'specialties';
    let selectedId = null;

    function updateWarning() {
        warning.hidden = form.elements.status.value !== 'inactive';
        warning.textContent = isSpecialty
            ? 'Chuyên môn ẩn không được dùng cho dữ liệu mới. Dữ liệu cũ và danh mục vẫn được giữ lại.'
            : 'Chỉ có thể ẩn nhóm khi không còn chuyên môn đang hiện. Nhóm sẽ không được chọn cho chuyên môn mới.';
    }

    function open(id) {
        selectedId = id || null;
        const item = context.state[list.collection].find(entry => entry.id === selectedId);
        form.reset();
        error.hidden = true;
        const label = isSpecialty ? 'chuyên môn' : 'nhóm nghề';
        document.getElementById('category-modal-title').textContent = (item ? 'Chỉnh sửa ' : 'Thêm ') + label;
        document.getElementById('category-name-label').textContent = 'Tên ' + label + ' *';
        document.getElementById('category-parent-group').hidden = !isSpecialty;
        const parent = form.elements.occupationId;
        parent.required = isSpecialty;
        parent.disabled = !isSpecialty;
        parent.replaceChildren(new Option('Chọn nhóm nghề', ''));
        context.state.occupations.forEach(function (group) {
            const option = new Option(group.name + (group.status === 'inactive' ? ' (ẩn)' : ''), group.id);
            option.disabled = group.status !== 'active' && item?.occupationId !== group.id;
            parent.add(option);
        });
        if (item) {
            ['name', 'description', 'status', 'occupationId'].forEach(function (field) {
                if (item[field] !== undefined) {
                    form.elements[field].value = item[field];
                }
            });
        } else if (isSpecialty) {
            const filter = document.getElementById('filter-occupationId');
            const group = context.state.occupations.find(entry => entry.id === filter?.value);
            parent.value = group?.status === 'active' ? group.id : '';
        }
        updateWarning();
        modal.show();
    }

    function save(value) {
        return context.commit(function (next) {
            AdminCategories.save(next, list.collection, value, selectedId, AdminDomain.today());
        });
    }

    function confirmStatusChange(value) {
        let saved = false;
        const confirmation = document.getElementById('admin-confirm');
        confirmation.addEventListener('hidden.bs.modal', function () {
            if (!saved) {
                modal.show();
            }
        }, { once: true });
        modalElement.addEventListener('hidden.bs.modal', function () {
            const message = (value.status === 'inactive' ? 'Ẩn' : 'Hiện') + ' “' + value.name + '”? '
                + (value.status === 'inactive' ? 'Danh mục không được dùng cho dữ liệu mới; dữ liệu cũ vẫn giữ nguyên.' : 'Danh mục có thể được sử dụng khi nhóm trực thuộc đang hiện.');
            context.confirm(message, false, function () {
                saved = save(value);
                if (saved) {
                    list.render();
                }
            });
        }, { once: true });
        modal.hide();
    }

    form.addEventListener('input', updateWarning);
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const value = Object.fromEntries(new FormData(form));
        try {
            AdminCategories.validate(context.state, list.collection, value, selectedId);
            const item = context.state[list.collection].find(entry => entry.id === selectedId);
            if (item && item.status !== value.status) {
                confirmStatusChange(value);
            } else if (save(value)) {
                modal.hide();
                list.render();
            } else {
                error.textContent = 'Không thể lưu phiên demo. Dữ liệu chưa được cập nhật.';
                error.hidden = false;
            }
        } catch (failure) {
            error.textContent = failure.message;
            error.hidden = false;
        }
    });

    document.addEventListener('click', function (event) {
        const edit = event.target.closest('[data-category-edit]');
        if (edit) {
            open(edit.dataset.categoryEdit);
        }
        const toggle = event.target.closest('[data-category-toggle]');
        if (toggle) {
            const item = context.state[list.collection].find(entry => entry.id === toggle.dataset.categoryToggle);
            let message = (item.status === 'active' ? 'Ẩn' : 'Hiện') + ' “' + item.name + '”? ';
            message += isSpecialty
                ? 'Chuyên môn ẩn không được dùng cho dữ liệu mới. Chỉ hiện khi nhóm nghề đang hiện.'
                : 'Không thể ẩn nhóm còn chuyên môn đang hiện. Nhóm ẩn không được chọn cho dữ liệu mới.';
            context.confirm(message, false, function () {
                context.commit(next => AdminCategories.toggle(next, list.collection, item.id));
                list.render();
            });
        }
    });
};
