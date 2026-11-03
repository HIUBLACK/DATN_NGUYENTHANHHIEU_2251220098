<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfileRequest;
use App\Http\Requests\ResumeUploadRequest;
use App\Services\CandidateDemo;
use App\Support\CandidateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CandidateDemoController extends Controller
{
    public function __construct(
        private CandidateDemo $demo,
        private CandidateCatalog $catalog,
    ) {}

    public function enter(): RedirectResponse
    {
        $this->demo->update('active', true);

        return redirect()->route('candidate.profile')->with('notice', 'Đã mở chế độ xem thử ứng viên, không phải đăng nhập thật.');
    }

    public function leave(): RedirectResponse
    {
        $this->demo->update('active', false);

        return redirect()->route('home')->with('notice', 'Đã thoát chế độ xem thử.');
    }

    public function upload(ResumeUploadRequest $request, ?string $id = null): RedirectResponse
    {
        $this->demo->upload($request->file('pdf'), $id);

        return back()->with('notice', 'PDF đã được lưu riêng tư cho phiên demo; đang chờ phân tích. Chưa chạy AI.');
    }

    public function resumeAction(Request $request, string $id, string $action): RedirectResponse
    {
        $rules = match ($action) {
            'rename' => ['name' => ['required', 'string', 'max:100']],
            'delete' => ['replacement' => ['nullable', 'uuid']],
            'state' => ['status' => ['required', 'in:pending,processing,needs_review,failed']],
            'skills' => ['skills' => ['required', 'string', 'max:1500']],
            default => [],
        };
        $input = $request->validate($rules, [
            'required' => 'Vui lòng điền thông tin bắt buộc.',
            'max' => 'Nội dung vượt quá độ dài cho phép.',
            'in' => 'Trạng thái không hợp lệ.',
        ]);
        $this->demo->changeResume($id, $action, $input);

        return back()->with('notice', 'Đã cập nhật CV trong phiên demo.');
    }

    public function pdf(Request $request, string $id)
    {
        $resume = $this->demo->resume($id);
        abort_unless($resume && Storage::disk('local')->exists($resume['path']), 404);
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'];

        if ($request->boolean('download')) {
            return Storage::disk('local')->download($resume['path'], $resume['filename'], $headers);
        }

        return Storage::disk('local')->response($resume['path'], $resume['filename'], $headers);
    }

    public function save(int $id): RedirectResponse
    {
        // ID 999 represents an unavailable job without exposing its former details.
        abort_unless($id === 999 || $this->catalog->find($id), 404);
        $this->demo->toggleSaved($id);

        return back()->with('notice', 'Đã cập nhật danh sách việc đã lưu trong phiên demo.');
    }

    public function apply(Request $request, int $id): RedirectResponse
    {
        $input = $request->validate([
            'resume' => ['required', 'uuid'],
            'letter' => ['nullable', 'string', 'max:2000'],
            'confirmation' => ['accepted'],
        ], [
            'resume.required' => 'Hãy chọn CV PDF trước khi ứng tuyển.',
            'confirmation.accepted' => 'Bạn cần xác nhận trước khi nộp.',
            'letter.max' => 'Thư giới thiệu tối đa 2.000 ký tự.',
        ]);
        $job = $this->catalog->find($id);
        abort_unless($job, 404);
        $this->demo->apply($job, $input['resume'], $input['letter'] ?? '');

        return redirect()->route('candidate.applications')->with('notice', 'Ứng tuyển demo thành công. Chưa gửi hồ sơ hoặc email thật.');
    }

    public function withdraw(string $id): RedirectResponse
    {
        $this->demo->withdraw($id);

        return back()->with('notice', 'Đã rút hồ sơ demo. Chưa gửi email thật.');
    }

    public function readNotification(string $id): RedirectResponse
    {
        $notifications = $this->demo->state()['notifications'];

        foreach ($notifications as &$notification) {
            if ($id === 'all' || $notification['id'] === $id) {
                $notification['read'] = true;
            }
        }

        $this->demo->update('notifications', $notifications);

        return back()->with('notice', 'Đã đánh dấu thông báo đã đọc.');
    }

    public function profile(CandidateProfileRequest $request): RedirectResponse
    {
        $profile = $request->safe()->except('avatar');
        $previous = $this->demo->state()['profile'];
        $profile['avatar'] = $previous['avatar'] ?? null;

        if ($request->hasFile('avatar')) {
            $profile['avatar'] = $request->file('avatar')->store('candidate-preview', 'local');

            if (! empty($previous['avatar'])) {
                Storage::disk('local')->delete($previous['avatar']);
            }
        }

        $this->demo->update('profile', $profile);

        return back()->with('notice', 'Đã cập nhật hồ sơ phiên demo.');
    }

    public function avatar()
    {
        $avatar = $this->demo->state()['profile']['avatar'] ?? null;

        if ($avatar && Storage::disk('local')->exists($avatar)) {
            return Storage::disk('local')->response($avatar, null, ['Cache-Control' => 'private, no-store']);
        }

        return response()->file(public_path('jobboard/images/person_1.jpg'));
    }

    public function authPreview(Request $request, string $mode): RedirectResponse
    {
        $rules = [];

        if (in_array($mode, ['login', 'register', 'forgot'], true)) {
            $rules['email'] = ['required', 'email'];
        }

        if (in_array($mode, ['login', 'register', 'reset', 'settings'], true)) {
            $rules['password'] = ['required', 'string', 'min:8'];
        }

        if (in_array($mode, ['register', 'reset', 'settings'], true)) {
            $rules['password'][] = 'confirmed';
        }

        if ($mode === 'settings') {
            $rules['current_password'] = ['required', 'string'];
        }

        $request->validate($rules, [
            'required' => 'Vui lòng điền thông tin bắt buộc.',
            'email' => 'Email không hợp lệ.',
            'min' => 'Mật khẩu cần ít nhất 8 ký tự.',
            'confirmed' => 'Xác nhận mật khẩu chưa khớp.',
        ]);

        return back()->with('notice', 'Biểu mẫu hợp lệ. Chưa kết nối xác thực: không tạo tài khoản, đổi mật khẩu hoặc gửi email.');
    }

    public function sampleApplications(): RedirectResponse
    {
        $applications = $this->demo->state()['applications'];

        foreach (['applied', 'reviewing', 'interview', 'hired', 'rejected', 'withdrawn'] as $index => $status) {
            $job = $this->catalog->find($index + 2);

            if (collect($applications)->contains('job_id', $job['id'])) {
                continue;
            }

            $application = [
                'id' => (string) Str::uuid(),
                'job_id' => $job['id'],
                'job_title' => $job['title'],
                'company' => $job['company'],
                'resume_name' => 'CV minh họa',
                'filename' => 'cv-minh-hoa.pdf',
                'letter' => '',
                'attempt' => 1,
                'status' => $status,
                'date' => now()->format('d/m/Y H:i'),
                'timeline' => [
                    ['status' => 'applied', 'date' => now()->subDay()->format('d/m/Y H:i')],
                    ['status' => $status, 'date' => now()->format('d/m/Y H:i')],
                ],
            ];
            $applications[] = $application;
            $this->demo->notify($application);
        }

        $this->demo->update('applications', $applications);

        return back()->with('notice', 'Đã thêm các trạng thái minh họa, không gửi hồ sơ hoặc email.');
    }

    public function reset(): RedirectResponse
    {
        foreach ($this->demo->state()['resumes'] as $resume) {
            Storage::disk('local')->delete($resume['path']);
        }

        $avatar = $this->demo->state()['profile']['avatar'] ?? null;

        if ($avatar) {
            Storage::disk('local')->delete($avatar);
        }

        session()->forget('candidate_preview');

        return redirect()->route('home')->with('notice', 'Đã xóa dữ liệu và tệp của phiên demo này.');
    }
}
