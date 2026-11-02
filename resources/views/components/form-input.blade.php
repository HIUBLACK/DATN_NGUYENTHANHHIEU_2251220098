@props(['name','label','type'=>'text','value'=>'','required'=>false])
<div class="field"><label for="{{ $name }}">{{ $label }} @if($required)<span aria-hidden="true">*</span>@endif</label><input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" @required($required) {{ $attributes }}><span class="field-error" id="{{ $name }}-error" aria-live="polite"></span></div>
