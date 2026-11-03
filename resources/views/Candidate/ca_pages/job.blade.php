@extends('Candidate.master')

@section('content')
    <section class="job-detail-page">
        <div class="container">
            <nav aria-label="Đường dẫn" class="job-detail-breadcrumb">
                <a href="{{ route('home') }}">Trang chủ</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('jobs') }}">Việc làm</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Chi tiết việc làm</span>
            </nav>
            <div class="row">
                <div class="col-lg-8">
                    <section class="job-detail-card" aria-labelledby="job-title">
                        <div class="mb-3">
                            <span class="badge {{ $job['status'] === 'open' ? 'badge-success' : 'badge-secondary' }}">{{ $statusLabels[$job['status']] }}</span>
                        </div>
                        <h1 id="job-title" class="job-detail-title">{{ $job['title'] }}</h1>
                        <p class="job-detail-salary">
                            <span class="icon-money job-detail-icon" aria-hidden="true"></span>
                            {{ $job['salary_min'] }}–{{ $job['salary_max'] }} triệu/tháng
                        </p>
                        <div class="row job-detail-facts">
                            <div class="col-sm-4 mb-3">
                                <span class="job-detail-label">
                                    <span class="icon-map-marker job-detail-icon" aria-hidden="true"></span>
                                    Địa điểm
                                </span>
                                <strong>{{ $job['location'] }}</strong>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <span class="job-detail-label">
                                    <span class="icon-graduation-cap job-detail-icon" aria-hidden="true"></span>
                                    Cấp bậc
                                </span>
                                <strong>{{ $job['level'] }}</strong>
                            </div>
                            <div class="col-sm-4 mb-3">
                                <span class="job-detail-label">
                                    <span class="icon-calendar job-detail-icon" aria-hidden="true"></span>
                                    Hạn ứng tuyển
                                </span>
                                <strong>{{ $job['deadline']->format('d/m/Y') }}</strong>
                            </div>
                        </div>
                        <div class="job-detail-actions">
                            <button type="button" class="btn btn-primary text-white" data-toggle="modal" data-target="#apply-modal" @disabled($applicationError)>
                                <span class="icon-paper-plane mr-2" aria-hidden="true"></span>
                                Ứng tuyển ngay
                            </button>
                            <form action="{{ route('candidate.save', $job['id']) }}" method="post">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary">
                                    <span class="icon-heart-o mr-2" aria-hidden="true"></span>
                                    {{ in_array($job['id'], $demo['saved']) ? 'Bỏ lưu' : 'Lưu việc' }}
                                </button>
                            </form>
                        </div>
                        @if ($applicationError)
                            <div class="alert alert-warning mt-3 mb-0" role="status">{{ $applicationError }}</div>
                        @endif
                    </section>

                    @include('Candidate.demo-notice')

                    <section class="job-detail-card" aria-labelledby="job-content-title">
                        <h2 id="job-content-title" class="job-detail-heading">
                            <span class="icon-file-text job-detail-icon" aria-hidden="true"></span>
                            Chi tiết tuyển dụng
                        </h2>
                        <div class="job-detail-section">
                            <h3>
                                <span class="icon-align-left job-detail-icon" aria-hidden="true"></span>
                                Mô tả công việc
                            </h3>
                            <ul>
                                <li>Tham gia xây dựng và phát triển sản phẩm công nghệ cùng đội ngũ chuyên môn.</li>
                                <li>Phối hợp với các thành viên để phân tích yêu cầu, triển khai và kiểm tra chất lượng công việc.</li>
                                <li>Đề xuất cải tiến, chia sẻ kiến thức và cập nhật tiến độ thực hiện.</li>
                            </ul>
                        </div>
                        <div class="job-detail-section">
                            <h3>
                                <span class="icon-check-circle job-detail-icon" aria-hidden="true"></span>
                                Yêu cầu ứng viên
                            </h3>
                            <ul>
                                <li>Có kiến thức và kinh nghiệm với {{ implode(', ', $job['skills']) }}.</li>
                                <li>Khả năng làm việc nhóm, giao tiếp rõ ràng và giải quyết vấn đề.</li>
                                <li>Chủ động học hỏi và chịu trách nhiệm với công việc được giao.</li>
                            </ul>
                        </div>
                        <div class="job-detail-section">
                            <h3>
                                <span class="icon-gift job-detail-icon" aria-hidden="true"></span>
                                Quyền lợi
                            </h3>
                            <ul>
                                <li>Thu nhập theo năng lực và phạm vi công việc.</li>
                                <li>Môi trường chia sẻ kiến thức, hỗ trợ phát triển kỹ năng chuyên môn.</li>
                                <li>Cơ hội tham gia sản phẩm thực tế và làm việc cùng đội ngũ phát triển.</li>
                            </ul>
                        </div>
                        <div class="job-detail-section">
                            <h3>
                                <span class="icon-tags job-detail-icon" aria-hidden="true"></span>
                                Kỹ năng yêu cầu
                            </h3>
                            <div class="job-detail-skills">
                                @foreach ($job['skills'] as $skill)
                                    <span class="badge badge-light border">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="job-detail-section mb-0">
                            <h3>
                                <span class="icon-map-marker job-detail-icon" aria-hidden="true"></span>
                                Địa điểm và hình thức làm việc
                            </h3>
                            <p>{{ $job['location'] }} · {{ $job['mode'] }} · {{ $job['type'] }}</p>
                            <p class="small text-muted mb-0">Nội dung tuyển dụng minh họa, chưa phải thông tin do nhà tuyển dụng đăng.</p>
                        </div>
                    </section>
                </div>
                <aside class="col-lg-4" aria-label="Thông tin công ty và công việc">
                    <section class="job-detail-card">
                        <div class="job-detail-company">
                            <img src="{{ asset('jobboard/images/'.$job['logo']) }}" alt="Logo minh họa {{ $job['company'] }}">
                            <h2>{{ $job['company'] }}</h2>
                        </div>
                        <dl class="job-detail-info mb-0">
                            <dt>
                                <span class="icon-map-marker job-detail-icon" aria-hidden="true"></span>
                                Địa điểm
                            </dt>
                            <dd>{{ $job['location'] }}</dd>
                            <dt>
                                <span class="icon-building job-detail-icon" aria-hidden="true"></span>
                                Thông tin công ty
                            </dt>
                            <dd class="mb-0">Công ty và logo được dùng để minh họa giao diện tuyển dụng.</dd>
                        </dl>
                    </section>
                    <section class="job-detail-card">
                        <h2 class="job-detail-heading">
                            <span class="icon-info-circle job-detail-icon" aria-hidden="true"></span>
                            Thông tin chung
                        </h2>
                        <dl class="job-detail-info mb-0">
                            <dt>
                                <span class="icon-graduation-cap job-detail-icon" aria-hidden="true"></span>
                                Cấp bậc
                            </dt>
                            <dd>{{ $job['level'] }}</dd>
                            <dt>
                                <span class="icon-laptop job-detail-icon" aria-hidden="true"></span>
                                Hình thức làm việc
                            </dt>
                            <dd>{{ $job['mode'] }}</dd>
                            <dt>
                                <span class="icon-briefcase job-detail-icon" aria-hidden="true"></span>
                                Loại công việc
                            </dt>
                            <dd>{{ $job['type'] }}</dd>
                            <dt>
                                <span class="icon-calendar job-detail-icon" aria-hidden="true"></span>
                                Thời hạn ứng tuyển
                            </dt>
                            <dd class="mb-0">{{ $job['deadline']->format('d/m/Y') }}</dd>
                        </dl>
                    </section>
                </aside>
            </div>
            <section class="job-detail-related" aria-labelledby="related-title">
                <h2 id="related-title" class="job-detail-heading">
                    <span class="icon-briefcase job-detail-icon" aria-hidden="true"></span>
                    Công việc liên quan
                </h2>
                <ul class="job-listings mb-0">
                    @foreach ($catalogJobs->where('id', '!=', $job['id'])->take(2) as $relatedJob)
                        @include('Candidate.job-row', ['job' => $relatedJob])
                    @endforeach
                </ul>
            </section>
        </div>
    </section>
    @include('Candidate.apply-modal')
@endsection
