<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyImageStorage
{
    public function store(UploadedFile $file, string $publicId, string $slot): string
    {
        $info = @getimagesize($file->getRealPath());
        $mime = $info['mime'] ?? '';
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $contents = $file->get();
        if (! isset($extensions[$mime]) || $file->getMimeType() !== $mime || preg_match('/<\?(?:php|=)/i', $contents)) {
            throw ValidationException::withMessages([$slot => 'Ảnh không hợp lệ hoặc có nội dung thực thi.']);
        }
        if (! function_exists('imagecreatefromstring')) {
            throw ValidationException::withMessages([$slot => 'Máy chủ chưa bật GD để kiểm tra ảnh. Vui lòng liên hệ quản trị viên hoặc lưu hồ sơ không kèm ảnh.']);
        }
        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            throw ValidationException::withMessages([$slot => 'Không thể giải mã ảnh. Vui lòng chọn ảnh khác.']);
        }
        // Mã hóa lại để bỏ metadata và dữ liệu nối thêm trước khi lưu.
        ob_start();
        try {
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($image, null, 90),
                'image/webp' => imagewebp($image, null, 90),
                default => $this->encodePng($image),
            };
            $contents = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($image);
        }
        if (! $encoded) {
            throw ValidationException::withMessages([$slot => 'Không xử lý được ảnh. Vui lòng chọn ảnh khác.']);
        }
        $path = 'companies/'.$publicId.'/'.$slot.'/'.Str::uuid().'.'.$extensions[$mime];
        if (! Storage::disk('local')->put($path, $contents)) {
            Storage::disk('local')->delete($path);
            throw ValidationException::withMessages([$slot => 'Không lưu được ảnh. Hồ sơ chưa được cập nhật.']);
        }

        return $path;
    }

    private function encodePng(\GdImage $image): bool
    {
        imagesavealpha($image, true);

        return imagepng($image);
    }

    public function owns(string $publicId, mixed $path): bool
    {
        return is_string($path) && preg_match('#^companies/'.preg_quote($publicId, '#').'/(logo|cover|office)/[a-f0-9-]{36}\.(png|jpg|webp)$#D', $path) === 1;
    }

    public function delete(string $publicId, array $paths): void
    {
        foreach ($paths as $path) {
            if ($this->owns($publicId, $path)) {
                if (! Storage::disk('local')->delete($path)) {
                    Log::warning('Company image cleanup failed.', ['company_public_id' => $publicId]);
                }
            }
        }
    }
}
