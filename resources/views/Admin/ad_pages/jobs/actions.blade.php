<button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#admin-status-modal"
    data-status-url="{{ route('admin.jobs.status', $job->id) }}"
    data-current-status="{{ $job->status->value }}" data-updated-at="{{ $job->getRawOriginal('updated_at') }}"
    data-log-id="{{ $job->activity_logs_max_id ?? 0 }}"
    data-next-status="{{ $job->status->value === 'blocked' ? 'restore' : 'blocked' }}"
    data-action-label="{{ $job->status->value === 'blocked' ? 'Mở chặn' : 'Chặn' }}"
    data-record-name="tin #{{ $job->id }}">
    <i class="bi bi-{{ $job->status->value === 'blocked' ? 'unlock' : 'lock' }}" aria-hidden="true"></i>
    {{ $job->status->value === 'blocked' ? 'Mở chặn' : 'Chặn' }}
</button>
