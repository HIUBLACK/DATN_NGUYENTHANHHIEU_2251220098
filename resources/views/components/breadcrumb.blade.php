@props(['label'])
<nav class="breadcrumb-nav" aria-label="Đường dẫn"><a href="{{ route('home') }}">Trang chủ</a><span aria-hidden="true">/</span><span aria-current="page">{{ $label }}</span></nav>
