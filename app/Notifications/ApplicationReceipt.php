<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationReceipt extends Notification
{
    public function __construct(public string $applicationId, public string $title, public string $status) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['application_id' => $this->applicationId, 'title' => $this->title, 'status' => $this->status];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->status) {
            'withdrawn' => 'Đã rút hồ sơ ứng tuyển',
            'reviewing' => 'Hồ sơ của bạn đang được xem xét',
            'interview' => 'Bạn được mời phỏng vấn',
            'hired' => 'Bạn đã trúng tuyển',
            'rejected' => 'Thông báo kết quả: hồ sơ chưa được chấp nhận',
            default => 'Đã nộp hồ sơ ứng tuyển',
        };
        $url = rtrim(config('app.url'), '/').route('candidate.application-detail', $this->applicationId, false);

        return (new MailMessage)->subject($message.' — VIEJOB AI')->greeting('Xin chào!')
            ->line($message.' cho tin: '.$this->title)->action('Xem hồ sơ', $url)->salutation('VIEJOB AI');
    }
}
