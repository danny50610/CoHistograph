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
            <h2 class="h3 mb-3">這是什麼</h2>
            <p class="col-lg-8 mb-0">
                這是一個協作式知識圖譜平台。你可以透過節點與關係，整理、瀏覽並探索資料之間的關聯。
            </p>
        </section>

        <section class="mb-5">
            <h2 class="h3 mb-3">我能做什麼</h2>
            <ul class="col-lg-8 mb-0">
                <li>探索現有的節點、關係與關聯脈絡</li>
                <li>登入後提出資料的新增或修改建議</li>
                <li>變更需經審核後才會套用，協助維持資料品質</li>
                <li>查看常見問題，了解更多使用方式</li>
            </ul>
        </section>

        <section class="mb-5">
            <h2 class="h3 mb-3">怎麼開始</h2>
            <ol class="col-lg-8 mb-4">
                <li>先探索現有資料，了解圖譜中有哪些內容</li>
                <li>登入後提交修訂建議</li>
                <li>等待審核通過後，變更才會正式生效</li>
            </ol>
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
