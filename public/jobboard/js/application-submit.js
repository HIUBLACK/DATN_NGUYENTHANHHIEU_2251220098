document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-application-submit]').forEach(function (form) {
        let submitting = false;
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) {
                return;
            }
            if (submitting) {
                event.preventDefault();
                return;
            }
            submitting = true;
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
            });
        });
        window.addEventListener('pageshow', function () {
            submitting = false;
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = false;
            });
        });
    });
});
