@extends('layouts.public')
@section('title','Khám phá công ty')
@section('content')<div class="page-container"><x-breadcrumb label="Công ty"/><div class="page-heading"><span class="eyebrow">CÙNG NHAU XÂY DỰNG TƯƠNG LAI</span><h1>Tìm môi trường dành cho bạn.</h1><p>Các công ty minh họa trong lĩnh vực công nghệ thông tin.</p></div><div class="company-grid mb-5">@foreach($companies as $company)<x-company-card :company="$company"/>@endforeach</div></div>@endsection
