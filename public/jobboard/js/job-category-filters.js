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

    function updateSpecialties(selected = '') {
        const items = categories.specialties.filter(specialty => specialty.status === 'active'
            && (!groupSelect.value || specialty.occupationId === groupSelect.value)
            && activeGroups.some(group => group.id === specialty.occupationId));
        specialtySelect.replaceChildren(new Option('Tất cả chuyên môn', ''));
        specialtySelect.disabled = false;
        items.forEach(specialty => specialtySelect.add(new Option(specialty.name, specialty.id)));
        specialtySelect.value = selected;

    }
    updateSpecialties(requestedSpecialty);
    specialtySelect.addEventListener('change', function () {
        const specialty = categories.specialties.find(item => item.id === specialtySelect.value);
        if (specialty) {
            groupSelect.value = specialty.occupationId;
            updateSpecialties(specialty.id);
        }
    });
    groupSelect.addEventListener('change', function () {
        updateSpecialties();
    });
});
