<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminStatusService
{
    public function change(User $admin, User|Company $subject, array $input): void
    {
        DB::transaction(function () use ($admin, $subject, $input) {
            // Cùng thứ tự khóa User -> Company như các dịch vụ tuyển dụng hiện có.
            $ownerId = $subject instanceof User ? $subject->id : $subject->recruiter_id;
            $users = User::whereIn('id', [$admin->id, $ownerId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $actor = $users->get($admin->id);
            $record = $subject instanceof User ? $users->get($subject->id) : Company::lockForUpdate()->findOrFail($subject->id);
            abort_unless($actor && $record, 404);
            Gate::forUser($actor)->authorize('moderate', $record);
            // User timestamp chỉ chính xác tới giây; audit ID chặn replay sau khóa/mở khóa trong cùng giây.
            $lastLogId = (int) $record->activityLogs()->max('id');
            if ($record->status->value !== $input['expected_status']
                || $lastLogId !== (int) $input['expected_log_id']
                || $record->getRawOriginal('updated_at') !== $input['updated_at']
                || $record->status->value === $input['status']) {
                throw ValidationException::withMessages(['status' => 'Dữ liệu đã thay đổi hoặc thao tác đã được thực hiện. Vui lòng tải lại trang.']);
            }
            if (trim($input['reason']) === '') {
                throw ValidationException::withMessages(['reason' => 'Vui lòng nhập lý do khóa hoặc mở khóa.']);
            }
            $previous = $record->status->value;
            $record->status = $input['status'];
            $record->save();
            $type = $record instanceof User ? 'user' : 'company';
            $log = new ActivityLog;
            $log->forceFill([
                'actor_id' => $actor->id, 'subject_type' => $type, 'subject_id' => $record->id,
                'action' => $type.($input['status'] === 'locked' ? '.lock' : '.unlock'),
                'old_values' => ['status' => $previous], 'new_values' => ['status' => $input['status']],
                'reason' => trim($input['reason']),
            ])->save();
        }, 3);
    }
}
