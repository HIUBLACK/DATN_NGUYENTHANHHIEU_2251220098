document.addEventListener('DOMContentLoaded', function () {
    const source = document.getElementById('candidate-occupation-data');
    const groupSelect = document.getElementById('filter-occupationId');
    const specialtySelect = document.getElementById('filter-specialtyId');
    if (!source || !groupSelect || !specialtySelect) {
        return;
    }
    let categories = JSON.parse(source.textContent);
    try {
        const admin = JSON.parse(sessionStorage.getItem('viejob-admin-preview-v1') || 'null');
        if (Array.isArray(admin?.occupations) && Array.isArray(admin?.specialties)) {
            categories = admin;
        }
    } catch (error) {
        // Phiên demo không đọc được thì giữ danh mục mẫu do server cung cấp.
    }
    const parameters = new URLSearchParams(location.search);
    const requestedGroup = parameters.get('occupationId') || '';
    const requestedSpecialty = parameters.get('specialtyId') || '';
    const activeGroups = categories.occupations.filter(group => group.status === 'active');
    groupSelect.replaceChildren(new Option('Tất cả nhóm nghề', ''));
    activeGroups.forEach(group => groupSelect.add(new Option(group.name, group.id)));
    groupSelect.value = requestedGroup;

    function navigationLink(item, field) {
        const url = new URL(location.href);
        url.searchParams.delete('page');
        if (field === 'occupationId') {
            url.searchParams.delete('specialtyId');
        } else {
            url.searchParams.set('occupationId', item.occupationId);
        }
        url.searchParams.set(field, item.id);
        const link = document.createElement('a');
        link.className = 'btn btn-outline-primary btn-sm mr-2 mb-2';
        link.href = url.toString();
        link.textContent = item.name;
        if (parameters.get(field) === item.id) {
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
        }
        return link;
    }
    const groupNavigation = document.getElementById('occupation-navigation');
    groupNavigation.replaceChildren(...activeGroups.map(group => navigationLink(group, 'occupationId')));

    function updateSpecialties(selected = '') {
        const items = categories.specialties.filter(specialty => specialty.status === 'active'
            && specialty.occupationId === groupSelect.value
            && activeGroups.some(group => group.id === specialty.occupationId));
        specialtySelect.replaceChildren(new Option(groupSelect.value ? 'Tất cả chuyên môn' : 'Chọn nhóm nghề trước', ''));
        specialtySelect.disabled = !groupSelect.value;
        items.forEach(specialty => specialtySelect.add(new Option(specialty.name, specialty.id)));
        specialtySelect.value = selected;
        const navigation = document.getElementById('specialty-navigation');
        navigation.replaceChildren(...items.map(specialty => navigationLink(specialty, 'specialtyId')));
        if (!items.length) {
            navigation.textContent = groupSelect.value ? 'Nhóm này chưa có chuyên môn đang hiện.' : 'Chọn nhóm nghề để khám phá chuyên môn.';
        }
    }
    updateSpecialties(requestedSpecialty);
    groupSelect.addEventListener('change', function () {
        updateSpecialties();
    });
});
