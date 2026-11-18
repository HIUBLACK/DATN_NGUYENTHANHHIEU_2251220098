(function () {
    'use strict';

    var modal = document.getElementById('category-status-modal');
    var form = document.getElementById('category-status-form');
    var message = document.getElementById('category-status-message');
    if (!modal || !form || !message) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        if (!button) {
            event.preventDefault();
            return;
        }
        form.action = button.dataset.action;
        form.elements.updated_at.value = button.dataset.version;
        form.elements.is_active.value = button.dataset.active;
        message.textContent = (button.dataset.active === '1' ? 'Hiện ' : 'Ẩn ') + button.dataset.name + '?';
    });
}());
