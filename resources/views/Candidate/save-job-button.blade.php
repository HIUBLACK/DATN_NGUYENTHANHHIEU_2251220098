@if ($job['real'] ?? false)
    @guest
        <a href="{{ route('login') }}" class="{{ $saveButtonClass ?? 'search-job-save' }}" aria-label="Đăng nhập để lưu việc làm">
            <span class="icon-heart-o" aria-hidden="true"></span>
        </a>
    @else
        @can('viewAny', App\Models\SavedJob::class)
            <form action="{{ route('candidate.save', $job['id']) }}" method="post" class="candidate-row-action" data-saved-job>
                @csrf
                <input type="hidden" name="saved" value="{{ in_array($job['id'], $savedJobIds, true) ? '0' : '1' }}">
                <button type="submit" class="{{ $saveButtonClass ?? 'search-job-save' }}" aria-label="{{ in_array($job['id'], $savedJobIds, true) ? 'Bỏ lưu' : 'Lưu việc' }} {{ $job['title'] }}" aria-pressed="{{ in_array($job['id'], $savedJobIds, true) ? 'true' : 'false' }}">
                    <span class="{{ in_array($job['id'], $savedJobIds, true) ? 'icon-heart' : 'icon-heart-o' }}" aria-hidden="true"></span>
                    @if ($saveButtonLabel ?? false)
                        {{ in_array($job['id'], $savedJobIds, true) ? 'Bỏ lưu' : 'Lưu việc' }}
                    @endif
                </button>
            </form>
        @endcan
    @endguest
@endif
