<div class="modal fade" id="admin-status-modal" tabindex="-1" aria-labelledby="admin-status-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="admin-status-form">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status">
                <input type="hidden" name="expected_status">
                <input type="hidden" name="expected_log_id">
                <input type="hidden" name="updated_at">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="admin-status-title">Xác nhận thay đổi trạng thái</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <p id="admin-status-message"></p>
                    <label for="admin-status-reason" class="form-label-custom">Lý do <span class="text-danger">*</span></label>
                    <textarea id="admin-status-reason" name="reason" class="form-control-custom" rows="3" maxlength="2000" required>{{ old('reason') }}</textarea>
                    <p class="small text-muted-green mt-3">Thao tác được lưu vào lịch sử. Dữ liệu và các phiên bản tin vẫn được giữ nguyên.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-dark-custom">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>
</div>
