document.addEventListener('DOMContentLoaded', function () {
    const element = document.getElementById('recruiter-confirm');
    const message = document.getElementById('recruiter-confirm-message');
    const confirmButton = document.getElementById('recruiter-confirm-submit');
    if (!element || !message || !confirmButton) {
        return;
    }
    const modal = bootstrap.Modal.getOrCreateInstance(element);
    let pendingForm = null;
    document.getElementById('recruiter-confirm-title').textContent = 'Xác nhận cập nhật hồ sơ';
    confirmButton.textContent = 'Xác nhận';

    document.querySelectorAll('form[data-application-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }
            if (form.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            if (form.dataset.confirmed === 'true') {
                form.dataset.submitting = 'true';
                form.querySelector('button[type="submit"]').disabled = true;
                return;
            }
            event.preventDefault();
            pendingForm = form;
            message.textContent = form.dataset.applicationConfirm;
            modal.show();
        });
    });
    confirmButton.addEventListener('click', function () {
        if (!pendingForm) {
            return;
        }
        pendingForm.dataset.confirmed = 'true';
        pendingForm.requestSubmit();
        modal.hide();
        pendingForm = null;
    });
    element.addEventListener('hidden.bs.modal', function () {
        pendingForm = null;
    });
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-application-confirm]').forEach(function (form) {
            delete form.dataset.submitting;
            delete form.dataset.confirmed;
            form.querySelector('button[type="submit"]').disabled = false;
        });
    });
});
