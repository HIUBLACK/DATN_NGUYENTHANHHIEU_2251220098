<div class="bg-light border rounded p-4 text-center">
    <span class="icon-folder-open text-primary h2" aria-hidden="true"></span>
    <h3 class="h5 mt-3">{{ $emptyTitle ?? 'Chưa có dữ liệu' }}</h3>
    <p>{{ $emptyDescription ?? 'Dữ liệu sẽ xuất hiện sau khi bạn thao tác trong bản demo.' }}</p>
    <a href="{{ $emptyUrl ?? route('jobs') }}" class="btn btn-primary text-white">
        {{ $emptyAction ?? 'Tìm việc làm' }}
    </a>
</div>
