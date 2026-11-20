<link rel="stylesheet" href="{{ asset('css/form-validation.css') }}">
<div id="validation-toast" class="validation-toast" role="alert" aria-live="assertive" hidden></div>
<script type="application/json" id="validation-server-errors">{!! Illuminate\Support\Js::encode($errors->messages()) !!}</script>
<script src="{{ asset('js/form-validation.js') }}"></script>
