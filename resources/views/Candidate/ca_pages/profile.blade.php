@extends('Candidate.master')

@section('content')
    <section class="site-section">
        <div class="container">
            @include('Candidate.demo-notice')
            <form action="{{ route('candidate.profile-save') }}" method="post" enctype="multipart/form-data" class="p-4 border rounded">
                @csrf
                <div class="form-group">
                    <img src="{{ route('candidate.avatar') }}" alt="Ảnh đại diện" class="candidate-profile-avatar rounded-circle mb-3">
                    <label for="avatar" class="d-block">Ảnh đại diện (JPG, PNG, WebP; tối đa 2 MB)</label>
                    <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp" class="form-control-file">
                </div>
                <div class="row">
                    @foreach ($profileFields as $fieldName => $field)
                        <div class="col-md-6">
                            @include('Candidate.input', [
                                'fieldName' => $fieldName,
                                'fieldLabel' => $field['label'],
                                'fieldType' => $field['type'],
                                'fieldValue' => $demo['profile'][$fieldName],
                                'required' => $field['required'],
                                'maxLength' => 150,
                            ])
                        </div>
                    @endforeach
                </div>
                <div class="form-group">
                    <label for="bio">Giới thiệu bản thân</label>
                    <textarea class="form-control" id="bio" name="bio" rows="5" maxlength="1500">{{ old('bio', $demo['profile']['bio']) }}</textarea>
                </div>
                <button class="btn btn-primary text-white">Cập nhật hồ sơ demo</button>
            </form>
        </div>
    </section>
@endsection
