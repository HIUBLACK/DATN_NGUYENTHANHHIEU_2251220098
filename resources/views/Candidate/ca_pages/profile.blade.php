@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            <form action="{{ route('candidate.profile-save') }}" method="post" enctype="multipart/form-data" class="p-4 border rounded">
                @csrf
                <div class="form-group">
                    <img src="{{ route('candidate.avatar') }}" alt="Ảnh đại diện" class="candidate-profile-avatar rounded-circle mb-3">
                    <label for="avatar" class="d-block">Ảnh đại diện (JPG, PNG, WebP; tối đa 2 MB, 5000 × 5000 pixel)</label>
                    <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp" class="form-control-file">
                    @if ($hasAvatar)
                        <label class="mt-2">
                            <input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar'))>
                            Xóa ảnh đại diện khi lưu hồ sơ
                        </label>
                    @endif
                </div>
                <div class="row">
                    @foreach ($profileFields as $fieldName => $field)
                        <div class="col-md-6">
                            @if ($fieldName === 'email')
                                <div class="form-group">
                                    <label class="text-black" for="email">Email (chỉ đọc)</label>
                                    <input type="email" id="email" class="form-control" value="{{ $profileData['email'] }}" readonly>
                                </div>
                            @else
                                @include('Candidate.input', [
                                    'fieldName' => $fieldName,
                                    'fieldLabel' => $field['label'],
                                    'fieldType' => $field['type'],
                                    'fieldValue' => $profileData[$fieldName],
                                    'required' => $field['required'],
                                    'showRequiredMark' => true,
                                    'maxLength' => $field['max'],
                                ])
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="form-group">
                    <label for="bio">Giới thiệu bản thân</label>
                    <textarea class="form-control" id="bio" name="bio" rows="5" maxlength="1500">{{ old('bio', $profileData['bio']) }}</textarea>
                </div>
                <p class="small text-muted">Phương thức đăng nhập: {{ $loginMethods }}. Email và mật khẩu không được thay đổi qua form này.</p>
                <button class="btn btn-primary text-white">Cập nhật hồ sơ</button>
            </form>
        </div>
    </section>
@endsection
