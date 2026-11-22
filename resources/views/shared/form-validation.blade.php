<link rel="stylesheet" href="{{ asset('css/form-validation.css') }}">
<div id="validation-toast" class="validation-toast"></div>
@foreach (['notice' => 'success', 'company_notice' => 'success', 'job_notice' => 'success', 'category_notice' => 'success', 'moderation_notice' => 'success', 'status' => 'success', 'success' => 'success', 'error' => 'error', 'warning' => 'warning', 'info' => 'info'] as $key => $type)
    @if (is_string(session($key)))
        <span data-flash-type="{{ $type }}" hidden>{{ session($key) }}</span>
    @endif
@endforeach
<script type="application/json" id="validation-server-errors">{!! Illuminate\Support\Js::encode($errors->messages()) !!}</script>
<script src="{{ asset('js/form-validation.js') }}"></script>
