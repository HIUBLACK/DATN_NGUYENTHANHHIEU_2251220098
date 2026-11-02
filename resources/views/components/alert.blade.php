@props(['tone'=>'info'])
<div {{ $attributes->class(['notice', 'notice-'.$tone]) }} role="{{ $tone==='error' ? 'alert' : 'status' }}">{{ $slot }}</div>
