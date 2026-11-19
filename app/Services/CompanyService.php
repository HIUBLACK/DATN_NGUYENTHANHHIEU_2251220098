<?php

namespace App\Services;

use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use App\Support\CompanyOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyService
{
    public const FIELDS = [
        'name' => 'name', 'description' => 'description', 'size' => 'company_size',
        'company_type' => 'company_type', 'country' => 'country', 'website' => 'website',
        'email' => 'email', 'phone' => 'phone', 'address' => 'address', 'working_hours' => 'working_hours',
        'ot_policy' => 'overtime_policy', 'work_environment' => 'work_environment',
    ];

    public function __construct(private CompanyImageStorage $images) {}

    public function publicQuery(): Builder
    {
        return Company::with('industry')->where('status', CompanyStatus::Active)
            ->whereHas('recruiter', function (Builder $query) {
                $query->where('role', UserRole::Recruiter)->where('status', UserStatus::Active);
            });
    }

    public function formData(Company $company): array
    {
        $data = [];
        foreach (self::FIELDS as $input => $column) {
            $data[$input] = $company->$column;
        }
        foreach (CompanyOptions::DAYS as $from => $fromLabel) {
            foreach (CompanyOptions::DAYS as $to => $toLabel) {
                if ($company->working_hours === $fromLabel.' – '.$toLabel) {
                    $data['working_from'] = $from;
                    $data['working_to'] = $to;
                }
            }
        }

        return $data + [
            'id' => $company->public_id, 'industry_id' => $company->industry_id,
            'updated_at' => $company->getRawOriginal('updated_at'), 'status' => $company->status->value,
            'logo' => $company->logo_path, 'office_images' => $company->office_images,
        ];
    }

    public function publicData(Company $company, bool $privateMedia = false): array
    {
        $profile = [];
        foreach (self::FIELDS as $input => $column) {
            if (! in_array($input, ['email', 'phone'], true)) {
                $profile[$input] = $company->$column;
            }
        }
        $profile['id'] = $company->public_id;
        $profile['industry'] = $company->industry?->name;
        if (! preg_match('/^https?:\/\//i', $profile['website'] ?? '')) {
            $profile['website'] = null;
        }
        $route = $privateMedia ? 'recruiter.company-media' : 'companies.media';
        $profile['logo_url'] = $this->images->owns($company->public_id, $company->logo_path) ? route($route, [$company->public_id, 'logo']) : null;
        $profile['gallery_urls'] = [];
        foreach ($company->office_images ?? [] as $index => $image) {
            if ($this->images->owns($company->public_id, $image['path'] ?? null)) {
                $profile['gallery_urls'][] = route($route, [$company->public_id, 'office-'.$index]);
            }
        }

        return $profile;
    }

    public function save(User $actor, ?Company $existing, array $data): Company
    {
        $publicId = $existing?->public_id ?? (string) Str::uuid();
        $uploaded = [];
        try {
            // Upload chỉ sau Form Request/Policy; file chưa được public cho tới DB commit.
            foreach (['logo'] as $slot) {
                if (isset($data[$slot])) {
                    $uploaded[$slot] = $this->images->store($data[$slot], $publicId, $slot);
                }
            }
            foreach ($data['office_images'] ?? [] as $file) {
                $uploaded['office'][] = $this->images->store($file, $publicId, 'office');
            }

            return DB::transaction(function () use ($actor, $existing, $data, $publicId, $uploaded) {
                $company = $this->lockCompany($actor, $existing, $publicId, $data);
                $this->assignIndustry($company, $data['industry_id'] ?? null);
                foreach (self::FIELDS as $input => $column) {
                    if ($input === 'working_hours' && empty($data['working_from'])) {
                        continue;
                    }
                    if ($input === 'ot_policy' && empty($data['ot_policy']) && $existing) {
                        continue;
                    }
                    $company->$column = $data[$input] ?? null;
                }
                if (! empty($data['working_from'])) {
                    $company->working_hours = CompanyOptions::DAYS[$data['working_from']].' – '.CompanyOptions::DAYS[$data['working_to']];
                }
                if (! empty($data['no_website'])) {
                    $company->website = null;
                }
                $oldPaths = $this->paths($company);
                $this->assignImages($company, $data, $uploaded);
                $company->updated_at = now();
                $company->save();
                $removed = array_diff($oldPaths, $this->paths($company));
                DB::afterCommit(function () use ($company, $removed) {
                    try {
                        $current = $company->fresh();
                        if ($current) {
                            $this->images->delete($company->public_id, array_diff($removed, $this->paths($current)));
                        }
                    } catch (Throwable) {
                        // Hồ sơ đã commit; lỗi dọn file không được báo nhầm thành lỗi lưu hồ sơ.
                        Log::warning('Company image cleanup needs retry.', ['company_public_id' => $company->public_id]);
                    }
                });

                return $company;
            });
        } catch (Throwable $exception) {
            $paths = array_merge(array_filter([$uploaded['logo'] ?? null]), $uploaded['office'] ?? []);
            // Không xóa file nếu lỗi phát sinh sau khi commit (ví dụ dọn ảnh cũ lỗi).
            try {
                $persisted = Company::where('public_id', $publicId)->first();
                $this->images->delete($publicId, array_diff($paths, $persisted ? $this->paths($persisted) : []));
            } catch (Throwable) {
                Log::warning('Uncommitted company images need review.', ['company_public_id' => $publicId]);
            }
            if ($exception instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['company' => 'Hồ sơ doanh nghiệp đã tồn tại. Vui lòng tải lại trang.']);
            }
            if ($exception instanceof QueryException) {
                Log::error('Company save failed.', ['company_public_id' => $publicId, 'sql_state' => $exception->getCode()]);
                throw ValidationException::withMessages(['company' => 'Chưa lưu được hồ sơ doanh nghiệp. Vui lòng thử lại sau.']);
            }
            throw $exception;
        }
    }

    private function lockCompany(User $actor, ?Company $existing, string $publicId, array $data): Company
    {
        $user = User::lockForUpdate()->findOrFail($actor->id);
        if ($existing) {
            $company = Company::lockForUpdate()->findOrFail($existing->id);
            Gate::forUser($user)->authorize('update', $company);
            if ($data['updated_at'] !== $company->getRawOriginal('updated_at')) {
                throw ValidationException::withMessages(['company' => 'Hồ sơ đã được thay đổi ở nơi khác. Vui lòng tải lại trang trước khi lưu.']);
            }
        } else {
            Gate::forUser($user)->authorize('create', Company::class);
            if (Company::where('recruiter_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['company' => 'Bạn đã có hồ sơ doanh nghiệp. Vui lòng chỉnh sửa hồ sơ hiện có.']);
            }
            $company = new Company;
            $company->public_id = $publicId;
            $company->recruiter_id = $user->id;
            $company->status = CompanyStatus::Active;
            $company->slug = Str::limit(Str::slug($data['name']) ?: 'doanh-nghiep', 110, '').'-'.$publicId;
            $company->office_images = [];
        }

        return $company;
    }

    private function assignIndustry(Company $company, mixed $selectedId): void
    {
        $industryId = $selectedId !== null ? (int) $selectedId : null;
        if ($industryId !== null) {
            $industry = Industry::lockForUpdate()->find($industryId);
            if (! $industry || (! $industry->is_active && $company->industry_id !== $industryId)) {
                throw ValidationException::withMessages(['industry_id' => 'Lĩnh vực không còn hoạt động. Vui lòng chọn lại.']);
            }
        }
        $company->industry_id = $industryId;
    }

    private function assignImages(Company $company, array $data, array $uploaded): void
    {
        foreach (['logo' => 'logo_path'] as $slot => $column) {
            if (isset($uploaded[$slot])) {
                $company->$column = $uploaded[$slot];
            } elseif (! empty($data['remove_'.$slot])) {
                $company->$column = null;
            }
        }
        $gallery = [];
        foreach ($company->office_images as $index => $image) {
            if (! in_array($index, array_map('intval', $data['remove_office_images'] ?? []), true)) {
                $gallery[] = $image;
            }
        }
        foreach ($uploaded['office'] ?? [] as $path) {
            $gallery[] = ['path' => $path, 'caption' => null];
        }
        if (count($gallery) > 6) {
            throw ValidationException::withMessages(['office_images' => 'Tối đa 6 ảnh văn phòng sau cập nhật.']);
        }
        $company->office_images = $gallery;
    }

    private function paths(Company $company): array
    {
        return array_values(array_filter(array_merge([$company->logo_path], array_column($company->office_images ?? [], 'path'))));
    }
}
