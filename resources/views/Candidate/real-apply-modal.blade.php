<div class="modal fade" id="apply-real-modal" tabindex="-1" aria-labelledby="apply-real-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="h5 modal-title" id="apply-real-title">Xác nhận ứng tuyển</h2>
                <button type="button" class="close" data-dismiss="modal" aria-label="Đóng">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <form method="post" action="{{ route('candidate.apply', $job['id']) }}" data-application-submit>
                @csrf
                <input type="hidden" name="job_version_id" value="{{ $applicationVersion }}">
                <input type="hidden" name="attempt_no" value="{{ $applicationEligibility['attempt'] }}">
                <div class="modal-body">
                    <h3 class="h5">{{ $job['title'] }}</h3>
                    <p>{{ $job['company'] }} · {{ $job['position'] }} · {{ $job['location'] }}</p>
                    <label for="application-resume">Chọn CV <span class="text-danger">*</span></label>
                    <select id="application-resume" name="resume" class="form-control mb-3" required>
                        @foreach ($applicationResumes as $resume)
                            <option value="{{ $resume->public_id }}" @selected(old('resume', $applicationResumes->firstWhere('is_primary', true)?->public_id) === $resume->public_id)>{{ $resume->display_name }}{{ $resume->is_primary ? ' (CV chính)' : '' }}</option>
                        @endforeach
                    </select>
                    <label for="application-letter">Thư giới thiệu (không bắt buộc)</label>
                    <textarea id="application-letter" name="letter" rows="4" maxlength="2000" class="form-control mb-3">{{ old('letter') }}</textarea>
                    <label>
                        <input type="checkbox" name="confirmation" value="1" required>
                        Tôi xác nhận nộp CV đã chọn cho tin tuyển dụng này.
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Quay lại</button>
                    <button type="submit" class="btn btn-primary text-white">Xác nhận nộp hồ sơ</button>
                </div>
            </form>
        </div>
    </div>
</div>
