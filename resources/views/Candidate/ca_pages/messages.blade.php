@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.empty-state', [
                'emptyTitle' => 'Chưa có tin nhắn',
                'emptyDescription' => 'Kênh nhắn tin chưa được kết nối trong giai đoạn giao diện này.',
            ])
        </div>
    </section>
@endsection
