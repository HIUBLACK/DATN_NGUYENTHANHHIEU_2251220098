<div class="modal fade" id="admin-category-modal" tabindex="-1" aria-labelledby="category-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="admin-category-form">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="category-modal-title">Danh mục</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category-name" class="form-label-custom" id="category-name-label">Tên danh mục *</label>
                        <input id="category-name" name="name" class="form-control-custom" required maxlength="120">
                    </div>
                    <div class="mb-3" id="category-parent-group" hidden>
                        <label for="category-parent" class="form-label-custom">Nhóm nghề trực thuộc *</label>
                        <select id="category-parent" name="occupationId" class="form-select-custom"></select>
                        <p class="small text-muted-green mt-2">Không thể chọn nhóm nghề ẩn cho chuyên môn mới hoặc khi chuyển nhóm.</p>
                    </div>
                    <div class="mb-3">
                        <label for="category-description" class="form-label-custom">Mô tả</label>
                        <textarea id="category-description" name="description" class="form-control-custom" rows="3" maxlength="1000"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="category-status" class="form-label-custom">Trạng thái</label>
                        <select id="category-status" name="status" class="form-select-custom">
                            <option value="active">Hiện</option>
                            <option value="inactive">Ẩn</option>
                        </select>
                    </div>
                    <p id="category-warning" class="alert alert-warning" hidden></p>
                    <p id="category-error" class="text-danger" role="alert" hidden></p>
                    <p class="small text-muted-green mb-0">Chỉ lưu mô phỏng trong tab trình duyệt, chưa cập nhật database.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn-dark-custom">
                        <i class="bi bi-check2" aria-hidden="true"></i>
                        Lưu mô phỏng
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
