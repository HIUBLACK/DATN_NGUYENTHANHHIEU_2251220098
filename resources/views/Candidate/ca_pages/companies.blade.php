@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            <div class="row">
                @foreach ($catalogJobs->unique('company') as $job)
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="p-4 border rounded h-100">
                            <img src="{{ asset('jobboard/images/'.$job['logo']) }}" alt="Logo công ty minh họa" class="img-fluid mb-3">
                            <h2 class="h4">{{ $job['company'] }}</h2>
                            <p>Công ty công nghệ minh họa · {{ $job['location'] }}</p>
                            <a href="{{ route('jobs', ['q' => $job['company']]) }}" class="btn btn-primary text-white">Xem việc làm</a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
