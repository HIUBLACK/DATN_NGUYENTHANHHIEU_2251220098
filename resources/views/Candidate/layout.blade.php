@extends('layouts.public')
@section('body-class', 'candidate-body')
@section('shell')
<div class="workspace page-container">
    @include('partials.candidate-sidebar')
    <main id="main" class="workspace-main" tabindex="-1">@yield('candidate-content')</main>
</div>
@endsection
