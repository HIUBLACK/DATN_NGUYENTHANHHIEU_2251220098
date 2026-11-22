@foreach ($metrics as [$label, $count, $icon])
    <div class="col-sm-6 col-xl-3">
        <div class="card card-stat p-4 h-100">
            <div class="d-flex justify-content-between gap-3">
                <span class="stat-label">{{ $label }}</span>
                <i class="bi bi-{{ $icon }} fs-5" aria-hidden="true"></i>
            </div>
            <strong class="stat-value">{{ $count }}</strong>
        </div>
    </div>
@endforeach
