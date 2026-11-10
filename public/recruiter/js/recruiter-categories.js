window.setupRecruiterCategories = function (form, state, original) {
    const groupSelect = form.elements.occupationId;
    const specialtySelect = form.elements.specialtyId;
    const savedGroup = original.occupationId || '';
    const savedSpecialty = original.specialtyId || '';
    groupSelect.replaceChildren(new Option('Chọn nhóm nghề', ''));
    state.occupations.filter(group => group.status === 'active' || group.id === savedGroup).forEach(function (group) {
        groupSelect.add(new Option(group.name + (group.status === 'inactive' ? ' (đã ẩn — giá trị cũ)' : ''), group.id));
    });
    if (savedGroup && !state.occupations.some(group => group.id === savedGroup)) {
        groupSelect.add(new Option(savedGroup + ' (danh mục cũ)', savedGroup));
    }
    groupSelect.value = savedGroup;

    function refreshSpecialties(selected = '') {
        specialtySelect.replaceChildren(new Option('Chọn chuyên môn', ''));
        specialtySelect.disabled = !groupSelect.value;
        const group = state.occupations.find(item => item.id === groupSelect.value);
        state.specialties.filter(function (specialty) {
            return specialty.occupationId === groupSelect.value
                && ((group?.status === 'active' && specialty.status === 'active') || (groupSelect.value === savedGroup && specialty.id === savedSpecialty));
        }).forEach(function (specialty) {
            const hidden = specialty.status !== 'active' || group?.status !== 'active';
            specialtySelect.add(new Option(specialty.name + (hidden ? ' (đã ẩn — giá trị cũ)' : ''), specialty.id));
        });
        if (groupSelect.value === savedGroup && savedSpecialty && !state.specialties.some(item => item.id === savedSpecialty)) {
            specialtySelect.add(new Option(savedSpecialty + ' (danh mục cũ)', savedSpecialty));
        }
        specialtySelect.value = selected;
    }
    refreshSpecialties(savedSpecialty);
    groupSelect.addEventListener('change', function () {
        refreshSpecialties();
    });

    function validate(draftOnly) {
        const groupId = groupSelect.value;
        const specialtyId = specialtySelect.value;
        if (draftOnly && !specialtyId) {
            return true;
        }
        const group = state.occupations.find(item => item.id === groupId);
        const specialty = state.specialties.find(item => item.id === specialtyId);
        const unchanged = groupId && specialtyId && groupId === savedGroup && specialtyId === savedSpecialty;
        const valid = group?.status === 'active' && specialty?.status === 'active' && specialty.occupationId === groupId;
        if (!valid && !unchanged) {
            groupSelect.setCustomValidity('Chọn nhóm nghề và chuyên môn đang hiện, đúng quan hệ trực thuộc.');
            groupSelect.reportValidity();
            return false;
        }
        return true;
    }
    return { validate };
};
