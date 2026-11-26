@extends('Admin.layout.admin')

@section('content')
    <div class="card p-4 mb-4">
        <h2 class="h4">Lịch sử tin #{{ $job->id }} · {{ $job->company->name }}</h2>
        <a href="{{ route('admin.jobs.show', ['id' => $job->id]) }}">Quay lại chi tiết tin</a>
    </div>
    @include('Admin.ad_pages.jobs.histories')
@endsection
