<!DOCTYPE html>
<html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $messageTitle }}</title>
    </head>
    <body style="margin: 0; background-color: #f4f6f8; font-family: Arial, sans-serif; color: #243746;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td align="center" style="padding: 24px 12px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff;">
                        <tr>
                            <td style="padding: 24px; background-color: #0069d1; color: #ffffff; font-size: 24px; font-weight: bold;">VIEJOB</td>
                        </tr>
                        <tr>
                            <td style="padding: 24px; font-size: 16px; line-height: 1.6;">
                                <p>Xin chào {{ $recipientName }},</p>
                                <h1 style="font-size: 22px; line-height: 1.4;">{{ $messageTitle }}</h1>
                                <p><strong>Công việc:</strong> {{ $jobTitle }}</p>
                                @if ($companyName !== '')
                                    <p><strong>Doanh nghiệp:</strong> {{ $companyName }}</p>
                                @endif
                                <p>Bạn có thể đăng nhập VIEJOB để xem chi tiết hồ sơ và lịch sử cập nhật.</p>
                                <p style="margin: 28px 0;">
                                    <a href="{{ $applicationUrl }}" style="display: inline-block; padding: 12px 20px; background-color: #0069d1; color: #ffffff; text-decoration: none; border-radius: 6px;">Xem hồ sơ ứng tuyển</a>
                                </p>
                                <p style="font-size: 13px;">Nếu nút không hoạt động, hãy mở liên kết sau:</p>
                                <p style="font-size: 13px; overflow-wrap: anywhere;">
                                    <a href="{{ $applicationUrl }}">{{ $applicationUrl }}</a>
                                </p>
                                <p>Trân trọng,<br>VIEJOB</p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
