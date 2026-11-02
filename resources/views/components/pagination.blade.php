@props(['paginator'])
@if($paginator->hasPages())<nav class="pagination-nav" aria-label="Phân trang">@for($i=1;$i<=$paginator->lastPage();$i++)<a href="{{ $paginator->url($i) }}" @class(['active'=>$paginator->currentPage()===$i]) @if($paginator->currentPage()===$i) aria-current="page" @endif aria-label="Trang {{ $i }}">{{ $i }}</a>@endfor</nav>@endif
