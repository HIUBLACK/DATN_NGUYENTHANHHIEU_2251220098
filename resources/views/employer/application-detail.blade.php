@extends('employer.layout')

@section('content')
    <div id="application-detail-content">
        <section id="ai-evaluation" class="card p-4 mb-4" aria-labelledby="ai-evaluation-title">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 id="ai-evaluation-title" class="h4 mb-0">
                    <i class="bi bi-stars me-2" aria-hidden="true"></i>
                    Đánh giá phù hợp bằng AI
                </h2>
                <span class="badge-table pending">Chưa đánh giá</span>
            </div>
            <p class="alert alert-light border">Chưa triển khai đánh giá AI. Bạn vẫn có thể xem CV và xử lý hồ sơ bình thường.</p>
            <p class="mb-0">Tin ứng tuyển: <strong>{{ $application->jobVersion->title }}</strong> · Phiên bản V{{ $application->jobVersion->version_number }}</p>
        </section>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 mb-4">
                    <h2 class="h4">{{ $application->candidate->name }}</h2>
                    <p>{{ $application->candidate->email }} · {{ $application->candidate->candidateProfile?->phone ?: 'Chưa cung cấp số điện thoại' }}</p>
                    <p>Ứng tuyển: {{ $application->jobVersion->title }} · {{ \App\Support\DisplayDate::format($application->applied_at) }}</p>
                    <p>Lượt {{ $application->attempt_no }}/2 · Phiên bản tin V{{ $application->jobVersion->version_number }}</p>
                    <div class="mb-3">
                        <span class="badge-table {{ $statusTones[$application->status->value] }}">{{ $statusLabels[$application->status->value] }}</span>
                    </div>
                    <div class="recruiter-actions">
                        @foreach ($nextStatuses as $status)
                            <form method="post" action="{{ route('recruiter.application-status', $application->public_id) }}" data-application-confirm="Chuyển hồ sơ sang {{ $statusLabels[$status] }}?">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status }}">
                                <input type="hidden" name="expected_status" value="{{ $application->status->value }}">
                                <input type="hidden" name="updated_at" value="{{ $application->updated_at->format('Y-m-d H:i:s.u') }}">
                                <button type="submit" class="btn btn-outline-dark">{{ $statusLabels[$status] }}</button>
                            </form>
                        @endforeach
                        @if (empty($nextStatuses))
                            <span class="text-muted-green">Trạng thái kết thúc; không thể chuyển tiếp.</span>
                        @endif
                    </div>
                    @if ($application->cover_letter)
                        <h3 class="h5 mt-4">Thư giới thiệu</h3>
                        <p class="recruiter-content-text">{{ $application->cover_letter }}</p>
                    @endif
                </div>
                <div class="card p-4">
                    <h2 class="h5">CV đã nộp</h2>
                    <div class="recruiter-actions mb-3">
                        <a href="{{ route('recruiter.application-pdf', $application->public_id) }}" target="_blank" rel="noopener" class="btn btn-outline-dark">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Xem CV
                        </a>
                        <a href="{{ route('recruiter.application-pdf', [$application->public_id, 'download' => 1]) }}" class="btn btn-light">
                            <i class="bi bi-download" aria-hidden="true"></i> Tải CV
                        </a>
                    </div>
                    <p class="small">{{ $application->resume_snapshot_filename }} · CV phiên bản {{ $application->resume_revision }} tại thời điểm nộp.</p>
                    <iframe title="CV PDF đã nộp" class="recruiter-pdf" src="{{ route('recruiter.application-pdf', $application->public_id) }}"></iframe>
                </div>
            </div>
            <aside class="col-lg-4">
                <div class="card p-4">
                    <h2 class="h5 mb-4">Lịch sử xử lý</h2>
                    <ol class="recruiter-timeline">
                        @foreach ($application->statusHistories->sortBy('id') as $event)
                            <li>
                                <strong>{{ $statusLabels[$event->new_status->value] }}</strong>
                                <p class="small mb-0">{{ \App\Support\DisplayDate::format($event->created_at) }} · {{ $event->actor->name }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </aside>
        </div>
    </div>
    <div class="card p-4 mt-4" id="application-note-card">
        <h2 class="h5">Ghi chú nội bộ</h2>
        <form id="application-note-form" method="post" action="{{ route('recruiter.application-note', $application->public_id) }}">
            @csrf
            <label for="application-note" class="form-label-custom">Nội dung ghi chú <span class="text-danger">*</span></label>
            <textarea id="application-note" name="content" class="form-control-custom mb-3" rows="3" maxlength="2000" required>{{ old('note_id') ? '' : old('content') }}</textarea>
            <button class="btn-dark-custom" type="submit">Thêm ghi chú</button>
        </form>
        <div id="application-notes" class="mt-4">
            @forelse ($application->notes->sortByDesc('id') as $note)
                <article class="recruiter-note">
                    <p class="recruiter-content-text">{{ $note->content }}</p>
                    <p class="small mb-0">{{ \App\Support\DisplayDate::format($note->created_at) }} · {{ $note->recruiter->name }}</p>
                    <details class="mt-2">
                        <summary>Chỉnh sửa ghi chú</summary>
                        <form method="post" action="{{ route('recruiter.application-note', $application->public_id) }}" class="mt-2">
                            @csrf
                            <input type="hidden" name="note_id" value="{{ $note->id }}">
                            <input type="hidden" name="note_updated_at" value="{{ $note->updated_at->format('Y-m-d H:i:s.u') }}">
                            <label for="note-{{ $note->id }}">Nội dung ghi chú <span class="text-danger">*</span></label>
                            <textarea id="note-{{ $note->id }}" name="content" class="form-control-custom mb-2" rows="3" maxlength="2000" required>{{ (string) old('note_id') === (string) $note->id ? old('content') : $note->content }}</textarea>
                            <button type="submit" class="btn btn-outline-dark">Lưu ghi chú</button>
                        </form>
                    </details>
                </article>
            @empty
                <p class="text-muted-green">Chưa có ghi chú nội bộ.</p>
            @endforelse
        </div>
    </div>
    <a href="{{ route('recruiter.applications') }}" class="btn btn-light mt-4">Quay lại danh sách</a>
@endsection
