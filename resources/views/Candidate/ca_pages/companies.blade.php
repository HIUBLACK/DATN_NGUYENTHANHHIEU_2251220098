@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row">
                @forelse ($companyProfiles as $company)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="p-4 border rounded h-100">
                            @if ($company['logo_url'])
                                <img src="{{ $company['logo_url'] }}" alt="Logo {{ $company['name'] }}" class="company-upload-preview mb-3">
                            @endif
                            <h2 class="h4">{{ $company['name'] }}</h2>
                            <p>{{ $company['address'] }}</p>
                            <a href="{{ route('companies.show', $company['id']) }}" class="btn btn-primary text-white">Xem doanh nghiệp</a>
                        </div>
                    </div>
                @empty
                    <p class="col-12">Chưa có doanh nghiệp công khai.</p>
                @endforelse
            </div>
            {{ $companyProfiles->links() }}
        </div>
    </section>
@endsection
