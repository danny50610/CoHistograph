@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="p-5 mb-4 bg-body-tertiary rounded-3">
            <div class="container-fluid py-5">
                <h1 class="display-5 fw-bold">{{ config('cohistograph.app.display-name') }}</h1>
                <p class="col-md-8 fs-4">一個協作式平台，用於構建、管理及探索歷史事件知識圖譜。</p>
                <a class="btn btn-primary btn-lg" type="button" href="{{ route('index') }}">開始探索</a>
            </div>
        </div>
    </div>
@endsection
