<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class DisplayDate
{
    public static function format(?DateTimeInterface $date, string $format = 'd/m/Y H:i'): string
    {
        // Chỉ đổi giờ hiển thị, không sửa đối tượng dùng cho lưu dữ liệu hoặc kiểm tra form cũ.
        return $date === null ? '' : CarbonImmutable::instance($date)->setTimezone('Asia/Ho_Chi_Minh')->format($format);
    }
}
