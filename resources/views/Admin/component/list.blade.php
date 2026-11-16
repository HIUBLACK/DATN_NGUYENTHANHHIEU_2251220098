<div class="card p-4 mb-4">
    <form id="admin-filters" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="admin-search" class="form-label-custom">Tìm kiếm</label>
            <input id="admin-search" name="q" class="form-control-custom" placeholder="Nhập tên hoặc từ khóa">
        </div>
        <div id="admin-extra-filters" class="col-md-8 row g-3 mt-0"></div>
        <div class="col-12">
            <button type="reset" class="btn btn-outline-dark">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                Xóa bộ lọc
            </button>
        </div>
    </form>
</div>
<div class="d-flex flex-wrap gap-3 justify-content-between align-items-center mb-3">
    <p id="admin-result-count" class="mb-0" role="status"></p>
    <div id="admin-list-actions" class="admin-actions"></div>
</div>
<div class="table-card-custom">
    <div class="table-responsive">
        <table class="table-custom admin-table">
            <caption class="visually-hidden">{{ $title }}</caption>
            <thead id="admin-table-head"></thead>
            <tbody id="admin-table-body"></tbody>
        </table>
    </div>
</div>
<nav id="admin-pagination" class="d-flex flex-wrap gap-2 mt-3" aria-label="Phân trang"></nav>
