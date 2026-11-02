<aside class="candidate-sidebar"><div class="sidebar-profile"><span class="avatar">MA</span><div><strong data-profile-name>Minh Anh</strong><span>Hồ sơ minh họa</span></div></div>
<nav aria-label="Không gian ứng viên">
@foreach(['ca.home'=>['Tổng quan','th-large'],'jobs'=>['Tìm việc làm','search'],'recommendations'=>['Việc làm gợi ý AI','magic'],'saved'=>['Việc làm đã lưu','bookmark'],'resumes'=>['Quản lý CV','file-pdf'],'applications'=>['Việc đã ứng tuyển','briefcase'],'notifications'=>['Thông báo','bell'],'profile'=>['Hồ sơ cá nhân','user'],'settings'=>['Cài đặt tài khoản','cog']] as $key=>$item)
<a href="{{ route($key==='jobs' ? 'jobs' : 'candidate.'.$key) }}" @class(['active'=>($page ?? '')===$key]) @if(($page ?? '')===$key) aria-current="page" @endif><i class="fas fa-{{ $item[1] }}" aria-hidden="true"></i>{{ $item[0] }}</a>
@endforeach
</nav><div class="sidebar-tip"><span class="eyebrow">MỘT BƯỚC NHỎ</span><h3>CV tốt, cơ hội mới.</h3><p>Thêm CV và xác nhận kỹ năng để khám phá gợi ý phù hợp.</p><a href="{{ route('candidate.resumes') }}">Cập nhật CV ↗</a></div>
<button class="text-button" data-logout><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Thoát trải nghiệm</button></aside>
