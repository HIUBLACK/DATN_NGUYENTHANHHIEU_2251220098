<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\User;
use App\Notifications\ApplicationReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class SendApplicationReceipt implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $userId, public ApplicationReceipt $receipt) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user || $this->receipt->status === 'reviewing') {
            return;
        }
        // Các tác vụ cũ chưa lưu tên doanh nghiệp; chỉ tra đúng hồ sơ của người nhận.
        if ($this->receipt->company === '') {
            $application = Application::where('public_id', $this->receipt->applicationId)
                ->where('candidate_id', $user->id)->with('job.company')->first();
            $this->receipt->company = $application?->job?->company?->name ?? '';
        }
        try {
            $user->notifyNow($this->receipt, ['mail']);
        } catch (Throwable) {
            // Không đưa phản hồi SMTP có thể chứa thông tin bí mật vào failed_jobs/log.
            throw new RuntimeException('Không gửi được email thông báo. Kiểm tra kết nối và cấu hình SMTP.');
        }
    }
}
