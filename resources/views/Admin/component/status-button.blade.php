<button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#admin-status-modal"
    data-status-url="{{ route('admin.management.status', [$section, $record->id]) }}"
    data-current-status="{{ $record->status->value }}" data-updated-at="{{ $record->getRawOriginal('updated_at') }}"
    data-log-id="{{ $record->activity_logs_max_id ?? 0 }}"
    data-record-name="{{ $record->name }}">
    <i class="bi bi-{{ $record->status->value === 'locked' ? 'unlock' : 'lock' }}" aria-hidden="true"></i>
    {{ $record->status->value === 'locked' ? 'Mở khóa' : 'Khóa' }}
</button>
