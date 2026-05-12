@extends('layouts.app')

@section('title', '新增修訂')

@section('content')
    <div class="container">
        <a href="{{ route('revisions.index') }}" class="btn btn-secondary mb-2">
            <i class="fa-solid fa-arrow-left"></i>
            返回我的修訂
        </a>

        <h1 class="h3 mb-3">新增修訂</h1>

        {{-- Error summary --}}
        @if ($errors->isNotEmpty())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">請修正以下錯誤後再提交：</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('revisions.store') }}">
            @csrf

            <div class="card mb-3">
                <div class="card-body">
                    <x-forms.input
                        id="title"
                        label="標題"
                        :value="old('title', '')"
                        required
                    />

                    <div class="mb-3 row">
                        <label for="description" class="col-md-2 col-form-label">描述</label>
                        <div class="col-md-10">
                            <textarea id="description" name="description"
                                      class="form-control @error('description') is-invalid @enderror"
                                      rows="3"
                                      placeholder="選填">{{ old('description', '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-10 ms-auto d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> 建立草稿
                            </button>
                            <a href="{{ route('revisions.index') }}" class="btn btn-secondary">取消</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
