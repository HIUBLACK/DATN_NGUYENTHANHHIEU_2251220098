document.addEventListener('DOMContentLoaded', function () {
    let pendingForm = null;
    const confirmationButton = document.getElementById('confirmation-submit');
    const confirmationMessage = document.getElementById('confirmation-message');

    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.confirmed === 'true') {
                return;
            }

            event.preventDefault();
            pendingForm = form;

            if (confirmationMessage) {
                confirmationMessage.textContent = form.dataset.confirm;
                window.jQuery('#confirmation-modal').modal('show');
            }
        });
    });

    if (confirmationButton) {
        confirmationButton.addEventListener('click', function () {
            if (!pendingForm) {
                return;
            }

            pendingForm.dataset.confirmed = 'true';
            pendingForm.requestSubmit();
        });
    }

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordToggle);

            if (!input) {
                return;
            }

            const showPassword = input.type === 'password';
            input.type = showPassword ? 'text' : 'password';
            button.textContent = showPassword ? 'Ẩn' : 'Hiện';
            button.setAttribute('aria-pressed', String(showPassword));
        });
    });

    const dropzone = document.getElementById('resume-dropzone');
    const fileInput = document.getElementById('resume-pdf');
    const fileLabel = document.getElementById('selected-file-name');

    if (dropzone && fileInput) {
        fileInput.addEventListener('change', function () {
            if (fileLabel && fileInput.files.length > 0) {
                fileLabel.textContent = fileInput.files[0].name;
            }
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.remove('is-dragging');
            });
        });

        dropzone.addEventListener('drop', function (event) {
            fileInput.files = event.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        });
    }

    document.querySelectorAll('form[data-loading]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const indicator = document.getElementById(form.dataset.loading);

            if (indicator && form.checkValidity()) {
                indicator.hidden = false;
            }
        });
    });

    document.querySelectorAll('input, textarea, select').forEach(function (input) {
        input.addEventListener('invalid', function () {
            if (input.validity.valueMissing) {
                input.setCustomValidity('Vui lòng điền thông tin bắt buộc.');
            } else if (input.validity.typeMismatch) {
                input.setCustomValidity('Vui lòng nhập đúng định dạng.');
            }
        });

        input.addEventListener('input', function () {
            input.setCustomValidity('');
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            document.body.classList.remove('offcanvas-menu');
        }
    });

    const skillsEditor = document.getElementById('skills-editor');
    const skillTags = document.getElementById('skill-tags');
    const newSkill = document.getElementById('new-skill');
    const addSkillButton = document.getElementById('add-skill');

    function renderSkillTags() {
        if (!skillsEditor || !skillTags) {
            return;
        }

        skillTags.replaceChildren();
        const skills = skillsEditor.value.split(',').map(function (skill) {
            return skill.trim();
        }).filter(Boolean);

        skills.forEach(function (skill, index) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-light btn-sm mr-2 mb-2';
            button.textContent = skill + ' ×';
            button.setAttribute('aria-label', 'Xóa kỹ năng ' + skill);
            button.addEventListener('click', function () {
                skills.splice(index, 1);
                skillsEditor.value = skills.join(', ');
                renderSkillTags();
            });
            skillTags.append(button);
        });
    }

    if (skillsEditor && newSkill && addSkillButton) {
        skillsEditor.addEventListener('input', renderSkillTags);
        addSkillButton.addEventListener('click', function () {
            const value = newSkill.value.trim();

            if (value === '') {
                return;
            }

            const separator = skillsEditor.value.trim() === '' ? '' : ', ';
            skillsEditor.value += separator + value;
            newSkill.value = '';
            renderSkillTags();
        });
        renderSkillTags();
    }
});
