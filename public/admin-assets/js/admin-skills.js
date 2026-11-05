window.setupAdminSkills = function (context, list) {
    const form = document.getElementById('admin-skill-form');
    if (!form) {
        return;
    }
    const isAlias = list.collection === 'aliases';
    const modal = new bootstrap.Modal(document.getElementById('admin-skill-modal'));
    const error = document.getElementById('skill-error');
    let selectedId = null;

    function preview() {
        const name = form.elements.name.value.trim() || 'Tên chưa nhập';
        const target = isAlias
            ? context.state.skills.find(skill => skill.id === form.elements.skillId.value)?.name || 'Chưa chọn kỹ năng'
            : form.elements.normalized.value.trim() || 'Chưa có tên chuẩn hóa';
        document.getElementById('skill-mapping-preview').textContent = 'Xem trước: ' + name + ' → ' + target;
    }

    function open(id) {
        selectedId = id || null;
        const item = context.state[list.collection].find(entry => entry.id === selectedId);
        form.reset();
        error.hidden = true;
        document.getElementById('admin-skill-title').textContent = (item ? 'Chỉnh sửa ' : 'Thêm ') + (isAlias ? 'tên đồng nghĩa' : 'kỹ năng');
        document.getElementById('skill-name-label').textContent = isAlias ? 'Tên đồng nghĩa *' : 'Tên kỹ năng *';
        document.getElementById('skill-normalized-group').hidden = isAlias;
        document.getElementById('skill-target-group').hidden = !isAlias;
        form.elements.normalized.required = !isAlias;
        form.elements.skillId.required = isAlias;
        form.elements.skillId.replaceChildren();
        context.state.skills.forEach(skill => form.elements.skillId.add(new Option(skill.name + (skill.status === 'inactive' ? ' (vô hiệu hóa)' : ''), skill.id)));
        if (item) {
            for (const field of ['name', 'normalized', 'skillId', 'status']) {
                if (item[field] !== undefined) {
                    form.elements[field].value = item[field];
                }
            }
        }
        preview();
        modal.show();
    }

    form.addEventListener('input', preview);
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        const value = Object.fromEntries(new FormData(form));
        value.name = value.name.trim();
        value.normalized = AdminDomain.normalize(value.normalized);
        try {
            if (isAlias) {
                AdminDomain.validateAlias(context.state, value, selectedId);
            } else {
                AdminDomain.validateSkill(context.state, value, selectedId);
            }
            const saved = context.commit(function (next) {
                const item = next[list.collection].find(entry => entry.id === selectedId);
                const fields = isAlias
                    ? { name: value.name, skillId: value.skillId, status: value.status }
                    : { name: value.name, normalized: value.normalized, status: value.status };
                if (item) {
                    Object.assign(item, fields);
                } else {
                    next[list.collection].unshift({ id: crypto.randomUUID(), created: AdminDomain.today(), used: false, ...fields });
                }
            });
            if (saved) {
                modal.hide();
                list.render();
            }
        } catch (failure) {
            error.textContent = failure.message;
            error.hidden = false;
        }
    });
    document.addEventListener('click', function (event) {
        const edit = event.target.closest('[data-skill-edit]');
        if (edit) {
            open(edit.dataset.skillEdit);
        }
        const toggle = event.target.closest('[data-alias-toggle]');
        if (toggle) {
            const item = context.state.aliases.find(alias => alias.id === toggle.dataset.aliasToggle);
            context.confirm('Thay đổi trạng thái ánh xạ “' + item.name + '”? Không xóa kỹ năng chuẩn.', false, function () {
                context.commit(function (next) {
                    const alias = next.aliases.find(entry => entry.id === item.id);
                    alias.status = alias.status === 'active' ? 'inactive' : 'active';
                });
                list.render();
            });
        }
    });
};
