document.addEventListener('DOMContentLoaded', function () {
    const group = document.getElementById('job_category_id');
    const specialty = document.getElementById('job_specialization_id');
    if (!group || !specialty) {
        return;
    }

    function filterSpecialties() {
        for (const option of specialty.options) {
            const visible = !option.value || option.dataset.category === group.value;
            option.hidden = !visible;
            option.disabled = !visible;
        }
        if (specialty.selectedOptions[0]?.disabled) {
            specialty.value = '';
        }
    }

    group.addEventListener('change', filterSpecialties);
    filterSpecialties();
});
