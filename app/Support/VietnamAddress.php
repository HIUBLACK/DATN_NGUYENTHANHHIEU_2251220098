<?php

namespace App\Support;

use Closure;

class VietnamAddress
{
    public static function data(): array
    {
        static $data;

        return $data ??= json_decode(
            file_get_contents(resource_path('data/vietnam-administrative-address.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    public static function ward(?string $code): ?array
    {
        static $wards;
        $wards ??= array_column(self::data()['wards'], null, 'code');

        return $wards[$code ?? ''] ?? null;
    }

    public static function wardRule(mixed $province): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($province) {
            $ward = is_string($value) ? self::ward($value) : null;
            if (! $ward || ! is_string($province) || $ward['province_code'] !== $province) {
                $fail('Xã/phường/đặc khu không hợp lệ hoặc không thuộc tỉnh/thành đã chọn.');
            }
        };
    }

    /** Mã xã NULL là địa chỉ cũ: giữ nguyên, không tự tách/ghép chuỗi người dùng. */
    public static function format(?string $detail, ?string $province, ?string $ward): string
    {
        $unit = self::ward($ward);
        if (! $unit || $unit['province_code'] !== $province) {
            return $detail ?? '';
        }

        return implode(', ', array_filter([$detail, $unit['name'], Provinces::ALL[$province] ?? null]));
    }
}
