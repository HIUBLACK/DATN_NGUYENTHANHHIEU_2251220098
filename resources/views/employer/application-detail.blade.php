@extends('employer.layout')

@section('content')
    <div id="application-detail-content"></div>
    <div class="card p-4 mt-4" id="application-note-card" hidden>
        <h2 class="h5">Ghi chú nội bộ</h2>
        <form id="application-note-form">
            <label for="application-note" class="form-label-custom">Nội dung ghi chú *</label>
            <textarea id="application-note" class="form-control-custom mb-3" rows="3" maxlength="2000" required></textarea>
            <button class="btn-dark-custom" type="submit">Thêm ghi chú (demo)</button>
        </form>
        <div id="application-notes" class="mt-4"></div>
    </div>
@endsection
