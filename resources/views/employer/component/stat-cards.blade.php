@foreach ($metrics as [$label, $count, $icon])
    <div class="col-sm-6 col-xl-3">
        <div class="card card-stat p-4 h-100">
            <div class="d-flex justify-content-between align-items-center">
                <span class="stat-label">{{ $label }}</span>
                <i class="bi bi-{{ $icon }} recruiter-stat-icon" aria-hidden="true"></i>
            </div>
            <div class="stat-value">{{ $count }}</div>
        </div>
    </div>
@endforeach
