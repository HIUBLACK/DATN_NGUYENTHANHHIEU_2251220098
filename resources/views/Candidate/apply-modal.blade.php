<div class="modal fade" id="apply-modal" tabindex="-1" role="dialog" aria-labelledby="apply-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route('candidate.apply', $job['id']) }}" method="post">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title h5" id="apply-title">Ứng tuyển: {{ $job['title'] }}</h2>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Đóng">×</button>
                </div>
                <div class="modal-body">
                    <p>Chọn CV PDF hợp lệ. Không yêu cầu hoàn tất phân tích AI.</p>
                    <label for="application-resume">CV ứng tuyển</label>
                    <select id="application-resume" name="resume" class="form-control mb-3" required>
                        <option value="">Chọn CV</option>
                        @foreach ($demo['resumes'] as $resume)
                            <option value="{{ $resume['id'] }}" @selected($resume['main'])>{{ $resume['name'] }}</option>
                        @endforeach
                    </select>
                    <a href="{{ route('candidate.resumes') }}">Tải CV mới</a>
                    <div class="form-group mt-3">
                        <label for="application-letter">Thư giới thiệu (không bắt buộc)</label>
                        <textarea class="form-control" id="application-letter" name="letter" maxlength="2000" rows="4"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="confirmation" id="application-confirmation" value="1" required>
                        <label for="application-confirmation" class="form-check-label">Tôi xác nhận nộp hồ sơ demo; không gửi đến nhà tuyển dụng thật.</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary text-white" @disabled($applicationError)>Xác nhận ứng tuyển</button>
                </div>
            </form>
        </div>
    </div>
</div>
