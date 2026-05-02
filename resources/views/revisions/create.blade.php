@extends('layouts.app')

@section('title', '新增修訂')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <a href="{{ route('revisions.index') }}" class="btn btn-secondary mb-2">
                    <i class="fa-solid fa-arrow-left"></i> 返回我的修訂
                </a>

                <h1>新增修訂</h1>

                <div class="card">
                    <div class="card-body p-4">
                        <form method="POST" action="{{ route('revisions.store') }}">
                            @csrf

                            <x-forms.input
                                id="title"
                                label="標題"
                                :value="old('title', '')"
                                required
                            />

                            <div class="row mb-3">
                                <label for="description" class="col-md-2 col-form-label">描述</label>
                                <div class="col-md-10">
                                    <textarea
                                        id="description"
                                        name="description"
                                        class="form-control @error('description') is-invalid @enderror"
                                        rows="4"
                                        placeholder="選填，描述這份修訂的目的"
                                    >{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary">建立草稿</button>
                                    <a href="{{ $backRoute }}" class="btn btn-secondary">取消</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
