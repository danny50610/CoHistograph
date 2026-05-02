@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if (!empty($backRoute) && !empty($backLabel))
                    <a href="{{ $backRoute }}" class="btn btn-secondary mb-2">
                        <i class="fa-solid fa-arrow-left"></i> {{ $backLabel }}
                    </a>
                @endif

                <div class="card shadow-sm">
                    <div class="card-body p-4 p-lg-5">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                            <div>
                                <h1>{{ $pageTitle }}</h1>
                                <p class="text-secondary mb-0">{{ $pageDescription }}</p>
                            </div>
                            <span class="badge text-bg-warning">WIP</span>
                        </div>

                        @if (!empty($referenceId))
                            <div class="alert alert-light border" role="alert">
                                目前路由參數 revision：{{ $referenceId }}
                            </div>
                        @endif

                        <div class="alert alert-info mb-0" role="alert">
                            目前已先建立 menu、權限、路由與 controller 骨架，畫面與業務邏輯待後續補齊。
                        </div>

                        @if (!empty($wipActions))
                            <div class="mt-4">
                                <h2>已保留的後續操作入口</h2>
                                <ul class="mb-0">
                                    @foreach ($wipActions as $wipAction)
                                        <li>{{ $wipAction }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection