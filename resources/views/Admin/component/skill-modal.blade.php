<div class="modal fade" id="admin-skill-modal" tabindex="-1" aria-labelledby="admin-skill-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="admin-skill-form">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="admin-skill-title">Kỹ năng</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="skill-name" class="form-label-custom" id="skill-name-label">Tên kỹ năng *</label>
                        <input id="skill-name" name="name" class="form-control-custom" required maxlength="80">
                    </div>
                    <div class="mb-3" id="skill-normalized-group">
                        <label for="skill-normalized" class="form-label-custom">Tên chuẩn hóa *</label>
                        <input id="skill-normalized" name="normalized" class="form-control-custom" maxlength="80">
                    </div>
                    <div class="mb-3" id="skill-target-group" hidden>
                        <label for="skill-target" class="form-label-custom">Kỹ năng chuẩn *</label>
                        <select id="skill-target" name="skillId" class="form-select-custom"></select>
                    </div>
                    <div class="mb-3">
                        <label for="skill-status" class="form-label-custom">Trạng thái</label>
                        <select id="skill-status" name="status" class="form-select-custom">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Vô hiệu hóa</option>
                        </select>
                    </div>
                    <p class="alert alert-light border" id="skill-mapping-preview" role="status"></p>
                    <p class="small">Chỉ lưu mô phỏng trong tab. Không tự động gộp Git với GitHub hoặc kỹ năng khác.</p>
                    <p id="skill-error" class="text-danger" role="alert" hidden></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Hủy</button>
                    <button class="btn-dark-custom" type="submit">Lưu mô phỏng</button>
                </div>
            </form>
        </div>
    </div>
</div>
