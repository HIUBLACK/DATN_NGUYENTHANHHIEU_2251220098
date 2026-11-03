@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            <div class="row align-items-center mb-5">
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <div class="d-flex align-items-center">
                        <div class="border p-2 d-inline-block mr-3 rounded">
                            <img src="{{ asset('jobboard/images/'.$job['logo']) }}" alt="Logo công ty minh họa">
                        </div>
                        <div>
                            <h2>{{ $job['title'] }}</h2>
                            <span class="mr-3">{{ $job['company'] }}</span>
                            <span class="mr-3">{{ $job['location'] }}</span>
                            <span class="text-primary">{{ $job['type'] }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="row">
                        <div class="col-6">
                            <form action="{{ route('candidate.save', $job['id']) }}" method="post">
                                @csrf
                                <button class="btn btn-block btn-light btn-md">
                                    {{ in_array($job['id'], $demo['saved']) ? 'Bỏ lưu' : 'Lưu việc' }}
                                </button>
                            </form>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-block btn-primary btn-md" data-toggle="modal" data-target="#apply-modal" @disabled($applicationError)>
                                Ứng tuyển
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @if ($applicationError)
                <div class="alert alert-warning">{{ $applicationError }}</div>
            @endif
            <div class="row">
                <div class="col-lg-8">
                    <div class="mb-5">
                        <figure class="mb-5">
                            <img src="{{ asset('jobboard/images/job_single_img_1.jpg') }}" alt="Môi trường làm việc minh họa" class="img-fluid rounded">
                        </figure>
                        <h3 class="h5 d-flex align-items-center mb-4 text-primary">
                            <span class="icon-align-left mr-3" aria-hidden="true"></span>
                            Mô tả công việc
                        </h3>
                        <p>Tham gia xây dựng sản phẩm công nghệ, phối hợp cùng đội ngũ phát triển và cải tiến chất lượng phần mềm. Đây là mô tả việc làm minh họa.</p>
                    </div>
                    <div class="mb-5">
                        <h3 class="h5 text-primary mb-4">Yêu cầu ứng viên</h3>
                        <ul>
                            <li>Có kiến thức và kinh nghiệm với {{ implode(', ', $job['skills']) }}.</li>
                            <li>Khả năng làm việc nhóm, giao tiếp rõ ràng và giải quyết vấn đề.</li>
                            <li>Chủ động học hỏi và chịu trách nhiệm với công việc được giao.</li>
                        </ul>
                    </div>
                    <div class="mb-5">
                        <h3 class="h5 text-primary mb-4">Quyền lợi</h3>
                        <p>Môi trường chia sẻ kiến thức, hỗ trợ phát triển kỹ năng; thu nhập theo năng lực và cơ hội tham gia sản phẩm thực tế.</p>
                    </div>
                </div>
                <aside class="col-lg-4">
                    <div class="bg-light p-3 border rounded mb-4">
                        <h3 class="text-primary mt-3 h5 pl-3 mb-3">Thông tin công việc</h3>
                        <ul class="list-unstyled pl-3 mb-0">
                            <li class="mb-2">Mức lương: {{ $job['salary_min'] }}–{{ $job['salary_max'] }} triệu/tháng</li>
                            <li class="mb-2">Địa điểm: {{ $job['location'] }}</li>
                            <li class="mb-2">Cấp bậc: {{ $job['level'] }}</li>
                            <li class="mb-2">Hình thức: {{ $job['mode'] }}</li>
                            <li class="mb-2">Hạn: {{ $job['deadline']->format('d/m/Y') }}</li>
                            <li class="mb-2">Trạng thái: {{ $statusLabels[$job['status']] }}</li>
                        </ul>
                    </div>
                    <div class="bg-light p-3 border rounded">
                        <h3 class="h5 text-primary">Kỹ năng yêu cầu</h3>
                        @foreach ($job['skills'] as $skill)
                            <span class="badge badge-light border mb-2">{{ $skill }}</span>
                        @endforeach
                    </div>
                </aside>
            </div>
            <h2 class="section-title mb-4">Công việc liên quan</h2>
            <ul class="job-listings">
                @foreach ($catalogJobs->where('id', '!=', $job['id'])->take(2) as $relatedJob)
                    @include('Candidate.job-row', ['job' => $relatedJob])
                @endforeach
            </ul>
        </div>
    </section>
    @include('Candidate.apply-modal')
@endsection
