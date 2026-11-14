<?php

namespace App\Services;

use App\Support\RecruiterCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyProfiles
{
    public const FIELDS = ['name', 'short_name', 'summary', 'description', 'founded_year', 'size', 'industry', 'company_type', 'country', 'website', 'email', 'phone', 'address', 'working_hours', 'ot_policy', 'benefits', 'technologies'];

    public function __construct(private RecruiterCatalog $catalog) {}

    public function current(): array
    {
        $company = session('company_profile_preview');
        if (! is_array($company)) {
            $company = $this->catalog->data()['company'];
            $company['id'] = (string) Str::uuid();
            $company['owner_token'] = (string) Str::uuid();
            $company['status'] = 'active';
            $company['cover'] = null;
            $company['office_images'] = [];
            session(['company_profile_preview' => $company, 'company_profile_owner' => $company['owner_token']]);
        }

        return $company;
    }

    public function find(string $id): ?array
    {
        $company = session('company_profile_preview');

        return is_array($company) && ($company['id'] ?? null) === $id ? $company : null;
    }

    public function canEdit(?string $id): bool
    {
        $company = session('company_profile_preview');

        return is_array($company)
            && ($id === null || $company['id'] === $id)
            && $company['status'] === 'active'
            && hash_equals($company['owner_token'], (string) session('company_profile_owner', ''));
    }

    public function save(?string $id, array $data): array
    {
        abort_unless($this->canEdit($id), 403);
        $company = $this->current();
        $createdPaths = [];
        $removedPaths = [];
        try {
            foreach (self::FIELDS as $field) {
                $company[$field] = $data[$field] ?? null;
            }
            foreach (['logo', 'cover'] as $field) {
                if (! empty($data['remove_'.$field]) || isset($data[$field])) {
                    if (! empty($company[$field])) {
                        $removedPaths[] = $company[$field];
                    }
                    $company[$field] = isset($data[$field]) ? $this->storeImage($data[$field], $createdPaths) : null;
                }
            }
            $gallery = [];
            foreach ($company['office_images'] as $index => $path) {
                if (in_array($index, array_map('intval', $data['remove_office_images'] ?? []), true)) {
                    $removedPaths[] = $path;
                } else {
                    $gallery[] = $path;
                }
            }
            if (count($gallery) + count($data['office_images'] ?? []) > 6) {
                throw ValidationException::withMessages(['office_images' => 'Tổng ảnh văn phòng sau cập nhật không được vượt quá 6 ảnh.']);
            }
            foreach ($data['office_images'] ?? [] as $file) {
                $gallery[] = $this->storeImage($file, $createdPaths);
            }
            $company['office_images'] = $gallery;
            session(['company_profile_preview' => $company]);
        } catch (Throwable $error) {
            Storage::disk('local')->delete($createdPaths);
            throw $error;
        }
        Storage::disk('local')->delete($removedPaths);

        return $company;
    }

    private function storeImage(UploadedFile $file, array &$createdPaths): string
    {
        $info = @getimagesize($file->getRealPath());
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $info['mime'] ?? '';
        $contents = $file->get();
        if (! isset($extensions[$mime]) || preg_match('/<\?(?:php|=)/i', $contents)) {
            throw ValidationException::withMessages(['logo' => 'Ảnh không hợp lệ hoặc có nội dung thực thi.']);
        }
        $path = 'company-images/'.Str::uuid().'.'.$extensions[$mime];
        if (! Storage::disk('local')->put($path, $contents)) {
            throw ValidationException::withMessages(['logo' => 'Không lưu được ảnh doanh nghiệp. Hồ sơ chưa được cập nhật.']);
        }
        $createdPaths[] = $path;

        return $path;
    }

    public function publicData(array $company): array
    {
        // Danh sách cho phép rõ ràng, không đưa email, điện thoại hoặc ID chủ sở hữu lên trang công khai.
        $fields = array_diff(self::FIELDS, ['email', 'phone']);
        $profile = array_replace(array_fill_keys($fields, null), array_intersect_key($company, array_flip($fields)));
        if (! preg_match('/^https?:\/\//i', $profile['website'] ?? '')) {
            $profile['website'] = null;
        }
        $profile['logo_url'] = $company['logo'] ? route('companies.media', [$company['id'], 'logo']) : null;
        $profile['cover_url'] = $company['cover'] ? route('companies.media', [$company['id'], 'cover']) : null;
        $profile['gallery_urls'] = [];
        foreach ($company['office_images'] ?? [] as $index => $path) {
            $profile['gallery_urls'][] = route('companies.media', [$company['id'], 'office-'.$index]);
        }

        return $profile;
    }
}
