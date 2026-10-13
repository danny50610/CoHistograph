@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="p-5 mb-4 bg-body-tertiary rounded-3">
            <div class="container-fluid py-5">
                <h1 class="display-5 fw-bold">{{ config('cohistograph.app.display-name') }}</h1>
                <p class="col-md-8 fs-4">{{ $homepage->tagline }}</p>
                <a class="btn btn-primary btn-lg" href="{{ route('overview') }}">開始探索</a>
            </div>
        </div>

        <section class="mb-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex gap-3">
                        <div class="text-primary fs-3" aria-hidden="true">
                            <i class="fa-solid fa-circle-nodes"></i>
                        </div>
                        <div>
                            <h2 class="h4 card-title">這是什麼</h2>
                            <p class="card-text mb-0">
                                這是一個協作式知識圖譜平台。你可以透過節點與關係，整理、瀏覽並探索資料之間的關聯。
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <h2 class="h3 mb-3">我能做什麼</h2>
            <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="text-primary mb-2" aria-hidden="true">
                                <i class="fa-solid fa-compass"></i>
                            </div>
                            <h3 class="h6 card-title">探索圖譜</h3>
                            <p class="card-text text-body-secondary mb-0">探索現有的節點、關係與關聯脈絡</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="text-primary mb-2" aria-hidden="true">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </div>
                            <h3 class="h6 card-title">提出建議</h3>
                            <p class="card-text text-body-secondary mb-0">登入後提出資料的新增或修改建議</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="text-primary mb-2" aria-hidden="true">
                                <i class="fa-solid fa-clipboard-check"></i>
                            </div>
                            <h3 class="h6 card-title">審核後生效</h3>
                            <p class="card-text text-body-secondary mb-0">變更需經審核後才會套用，協助維持資料品質</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="text-primary mb-2" aria-hidden="true">
                                <i class="fa-solid fa-circle-question"></i>
                            </div>
                            <h3 class="h6 card-title">查看說明</h3>
                            <p class="card-text text-body-secondary mb-0">查看常見問題，了解更多使用方式</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <h2 class="h3 mb-3">怎麼開始</h2>
            <div class="list-group mb-4">
                <div class="list-group-item d-flex align-items-start gap-3 py-3">
                    <span class="badge text-bg-primary rounded-pill">1</span>
                    <span>先探索現有資料，了解圖譜中有哪些內容</span>
                </div>
                <div class="list-group-item d-flex align-items-start gap-3 py-3">
                    <span class="badge text-bg-primary rounded-pill">2</span>
                    <span>登入後提交修訂建議</span>
                </div>
                <div class="list-group-item d-flex align-items-start gap-3 py-3">
                    <span class="badge text-bg-primary rounded-pill">3</span>
                    <span>等待審核通過後，變更才會正式生效</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-primary" href="{{ route('overview') }}">開始探索</a>
                @if (auth()->guest())
                    <a class="btn btn-outline-primary" href="{{ route('login') }}">登入</a>
                @else
                    <a class="btn btn-outline-primary" href="{{ route('revisions.create') }}">提交修訂</a>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('faq') }}">常見問題</a>
            </div>
        </section>
    </div>
@endsection
