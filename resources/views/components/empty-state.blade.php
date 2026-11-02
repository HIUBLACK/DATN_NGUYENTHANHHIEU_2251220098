@props(['title'=>'Chưa có dữ liệu', 'description'=>'', 'href'=>null, 'action'=>'Khám phá việc làm'])
<div {{ $attributes->class(['empty-state']) }}><span class="empty-icon"><i class="far fa-folder-open" aria-hidden="true"></i></span><h3>{{ $title }}</h3><p>{{ $description }}</p>@if($href)<a class="button secondary" href="{{ $href }}">{{ $action }}</a>@endif{{ $slot }}</div>
