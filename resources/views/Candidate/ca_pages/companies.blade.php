@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            @if ($ownCompanyPreview)
                <div class="alert alert-info">
                    Hồ sơ vừa chỉnh sửa trong phiên demo:
                    <a href="{{ route('companies.show', $ownCompanyPreview['id']) }}">{{ $ownCompanyPreview['name'] }}</a>
                </div>
            @endif
            <div class="row">
                @foreach ($companyProfiles as $company)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="p-4 border rounded h-100">
                            <img src="{{ $company['logo_url'] }}" alt="Logo công ty minh họa" class="img-fluid mb-3">
                            <h2 class="h4">{{ $company['name'] }}</h2>
                            <p>Công ty công nghệ minh họa · {{ $company['address'] }}</p>
                            <a href="{{ route('companies.demo', $company['id']) }}" class="btn btn-primary text-white">Xem doanh nghiệp</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
