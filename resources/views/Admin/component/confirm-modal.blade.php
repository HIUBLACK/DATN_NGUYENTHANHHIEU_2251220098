<div class="modal fade" id="admin-confirm" tabindex="-1" aria-labelledby="admin-confirm-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="admin-confirm-form">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="admin-confirm-title">Xác nhận thao tác demo</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p id="admin-confirm-message"></p>
                    <div id="admin-reason-group">
                        <label for="admin-reason" class="form-label-custom">Lý do *</label>
                        <textarea id="admin-reason" class="form-control-custom" rows="3" maxlength="1000"></textarea>
                    </div>
                    <p class="small text-muted-green mt-3 mb-0">Chỉ mô phỏng trong tab trình duyệt; chưa cập nhật hệ thống thật.</p>
                    <p id="admin-confirm-error" class="text-danger mt-2" role="alert" hidden></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-dark-custom" id="admin-confirm-submit">Xác nhận mô phỏng</button>
                </div>
            </form>
        </div>
    </div>
</div>
