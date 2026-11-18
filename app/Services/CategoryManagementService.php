<?php

namespace App\Services;

use App\Models\JobCategory;
use App\Models\User;
use App\Support\AdminCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryManagementService
{
    public function query(string $type)
    {
        [$model] = AdminCategory::definition($type);
        $query = $model::query();

        return match ($type) {
            'occupations' => $query->withCount('specializations as references_count'),
            'specialties' => $query->with('category')->withCount('jobVersions as references_count'),
            'industries' => $query->withCount(['jobVersions as references_count', 'companies']),
            'skills' => $query->withCount(['jobVersionSkills as references_count', 'resumeSkills']),
            default => $query->withCount('jobVersions as references_count'),
        };
    }

    public function save(User $actor, string $type, ?int $id, array $data, bool $statusOnly = false): Model
    {
        [$model] = AdminCategory::definition($type);
        try {
            return DB::transaction(function () use ($actor, $type, $id, $data, $statusOnly, $model) {
                $user = User::lockForUpdate()->findOrFail($actor->id);
                Gate::forUser($user)->authorize('manage-categories');
                // Khóa nhóm cha trước chuyên môn, cùng thứ tự với luồng lưu/gửi tin.
                $oldParent = $id && $type === 'specialties' ? $model::findOrFail($id)->job_category_id : null;
                $newParent = $type === 'specialties' ? (int) ($data['job_category_id'] ?? $oldParent) : null;
                $parents = JobCategory::whereIn('id', array_filter([$oldParent, $newParent]))
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $record = $id ? $model::lockForUpdate()->findOrFail($id) : new $model;
                if ($id && $record->getRawOriginal('updated_at') !== $data['updated_at']) {
                    throw ValidationException::withMessages(['category' => 'Dữ liệu đã thay đổi. Hãy tải lại trang trước khi lưu.']);
                }
                if ($type === 'specialties') {
                    $parent = $parents->get($newParent);
                    if (! $parent || ((! $id || $newParent !== $oldParent || $data['is_active']) && ! $parent->is_active)) {
                        throw ValidationException::withMessages(['job_category_id' => 'Hãy chọn nhóm nghề đang hiện.']);
                    }
                    if ($id && $record->job_category_id !== $oldParent) {
                        throw ValidationException::withMessages(['category' => 'Nhóm nghề đã thay đổi. Hãy tải lại trang.']);
                    }
                    // Locking read thấy tham chiếu vừa commit, không dùng snapshot cũ của REPEATABLE READ.
                    if ($id && $newParent !== $oldParent && $record->jobVersions()->select('id')->lockForUpdate()->first()) {
                        throw ValidationException::withMessages(['job_category_id' => 'Chuyên môn đã được tin tuyển dụng tham chiếu, không thể chuyển nhóm.']);
                    }
                    $record->job_category_id = $newParent;
                }
                if ($type === 'occupations' && $id && ! $data['is_active']
                    && $record->specializations()->where('is_active', true)->select('id')->lockForUpdate()->first()) {
                    throw ValidationException::withMessages(['is_active' => 'Không thể ẩn nhóm nghề còn chuyên môn đang hiện.']);
                }
                if (! $statusOnly) {
                    $this->setContent($record, $type, $data);
                }
                $record->is_active = (bool) $data['is_active'];
                $record->updated_at = now();
                $record->save();

                return $record;
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['name' => 'Tên hoặc mã danh mục đã tồn tại. Hãy kiểm tra và thử lại.']);
            }
            throw $exception;
        }
    }

    private function setContent(Model $record, string $type, array $data): void
    {
        $name = AdminCategory::cleanName($data['name']);
        $normalized = AdminCategory::normalizedName($name);
        $duplicate = $record->newQuery()->where('normalized_name', $normalized);
        if ($type === 'specialties') {
            $duplicate->where('job_category_id', $record->job_category_id);
        }
        if ($record->exists) {
            $duplicate->whereKeyNot($record->id);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['name' => 'Tên đã tồn tại trong danh mục này, kể cả bản ghi đang ẩn.']);
        }
        $record->name = $name;
        $record->normalized_name = $normalized;
        $record->description = $data['description'] ?? null;
        $record->sort_order = (int) $data['sort_order'];
        if (! $record->exists) {
            $suffix = bin2hex(random_bytes(8));
            $record->code = strtoupper(substr($type, 0, 3).$suffix);
            if ($type !== 'skills') {
                $record->slug = substr(Str::slug($name) ?: 'danh-muc', 0, 140).'-'.$suffix;
            }
        }
    }
}
