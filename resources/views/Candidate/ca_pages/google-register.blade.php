@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <h2 class="mb-4">Hoàn tất đăng ký Google</h2>
                    <p>Email đã xác minh: {{ $googleEmail }}</p>
                    <form action="{{ route('google.complete') }}" method="post" class="p-4 border rounded">
                        @csrf
                        <div class="form-group">
                            <label for="google-role">Loại tài khoản</label>
                            <select id="google-role" name="role" class="form-control" required>
                                <option value="candidate" @selected(old('role') === 'candidate')>Ứng viên</option>
                                <option value="recruiter" @selected(old('role') === 'recruiter')>Nhà tuyển dụng</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Xác nhận đăng ký</button>
                    </form>
                    <a href="{{ route('register') }}" class="d-inline-block mt-3">Quay lại đăng ký</a>
                </div>
            </div>
        </div>
    </section>
@endsection
