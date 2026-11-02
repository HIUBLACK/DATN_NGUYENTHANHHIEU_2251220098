@extends('Candidate.layout')
@section('title','Xác nhận kỹ năng CV')
@section('candidate-content')<div class="workspace-heading"><div><h1>Hiểu rõ thế mạnh của bạn</h1><p>Kiểm tra, chuẩn hóa và xác nhận kỹ năng trong CV.</p></div><a class="button secondary compact" href="{{ route('candidate.resumes') }}">Quay lại CV</a></div><x-alert>Kỹ năng mẫu không được trích xuất từ PDF. Bạn có thể sửa danh sách để thử luồng xác nhận; nội dung PDF không thay đổi.</x-alert><section id="skills-content" class="panel"></section>@endsection
