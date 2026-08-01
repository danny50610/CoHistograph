@extends('layouts.app')

@section('title', '首頁設定')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1>首頁設定</h1>
                <div class="card">
                    <div class="card-body">
                        <form role="form" method="POST" action="{{ route('admin.system-config.homepage.update') }}">
                            @method('put')
                            @csrf

                            <x-forms.textarea
                                id="tagline"
                                label="標題下方文字"
                                :value="$homepage->tagline"
                                placeholder="顯示於首頁標題下方的說明文字"
                                :rows="3"
                                required
                            />

                            <div class="form-group row">
                                <div class="col-md-10 ms-auto">
                                    <button type="submit" class="btn btn-primary">更新</button>
                                    <a href="{{ route('index') }}" class="btn btn-secondary" target="_blank">預覽首頁</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
