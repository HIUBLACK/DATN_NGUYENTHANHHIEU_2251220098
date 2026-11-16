document.addEventListener('DOMContentLoaded', function () {
    const configuration = document.querySelector('script[data-logout-url]');
    if (!configuration) {
        return;
    }

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) {
                return;
            }
            event.stopPropagation();
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(visible));
            button.textContent = visible ? 'Ẩn' : 'Hiện';
        });
    });

    document.addEventListener('submit', function (event) {
        if (!(event.target instanceof HTMLFormElement) || event.target.action !== configuration.dataset.logoutUrl) {
            return;
        }

        try {
            Object.keys(sessionStorage).forEach(function (key) {
                if (key.startsWith('viejob-recruiter-preview-') || key.startsWith('viejob-admin-preview-')) {
                    sessionStorage.removeItem(key);
                }
            });
        } catch (error) {
            // Storage restrictions must never prevent the server logout request.
        }
    });
});

window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        window.location.reload();
    }
});
