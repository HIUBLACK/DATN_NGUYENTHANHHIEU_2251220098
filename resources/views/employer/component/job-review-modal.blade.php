<script type="application/json" id="job-review-baseline">{!! Illuminate\Support\Js::encode($baseline) !!}</script>
<div class="modal fade" id="job-review-modal" tabindex="-1" aria-labelledby="job-review-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="job-review-title">Kiểm tra tin trước khi gửi duyệt</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <p id="job-review-source" class="text-muted"></p>
                <div id="job-review-content"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Quay lại chỉnh sửa</button>
                <button type="button" class="btn btn-primary" id="job-review-confirm">Xác nhận gửi duyệt</button>
            </div>
        </div>
    </div>
</div>
