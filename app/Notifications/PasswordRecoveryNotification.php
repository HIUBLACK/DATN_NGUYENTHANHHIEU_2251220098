<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class PasswordRecoveryNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        // Dùng APP_URL tin cậy, không lấy host/redirect từ request gửi tới website.
        $url = rtrim(config('app.url'), '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        return (new MailMessage)
            ->mailer('smtp')
            ->subject('Đặt lại mật khẩu VIEJOB AI')
            ->greeting('Xin chào!')
            ->line('Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản VIEJOB AI của bạn.')
            ->action('Đặt lại mật khẩu', $url)
            ->line('Liên kết có hiệu lực trong '.config('auth.passwords.users.expire').' phút và chỉ sử dụng được một lần.')
            ->line('Nếu bạn không yêu cầu thay đổi này, hãy bỏ qua email. Mật khẩu của bạn sẽ không thay đổi.')
            ->salutation('VIEJOB AI');
    }
}
