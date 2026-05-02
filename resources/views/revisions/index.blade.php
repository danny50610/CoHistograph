@extends('layouts.app')

@section('title', '我的修訂')

@section('content')
    <div class="container">
        <h1>我的修訂</h1>
        <div class="mb-3">
            <a href="{{ route('revisions.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> 新增修訂
            </a>
        </div>

        @forelse ($revisions as $revision)
            <a href="{{ route('revisions.show', $revision) }}"
               class="text-decoration-none text-reset d-block mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-2">
                            <h5 class="card-title mb-0">{{ $revision->title }}</h5>
                            @include('revisions.partials.status-badge', ['status' => $revision->status])
                        </div>

                        <div class="text-secondary small">
                            <div>{{ $revision->actions_count }} 個操作</div>
                            <div>最後更新：{{ $revision->updated_at }}</div>
                            @if ($revision->reviews->isNotEmpty())
                                <div>最近一次審核：{{ $revision->reviews->sortByDesc('created_at')->first()->created_at }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="card-footer text-secondary small d-flex align-items-center justify-content-end gap-1">
                        點擊查看詳情 <i class="fa-solid fa-chevron-right"></i>
                    </div>
                </div>
            </a>
        @empty
            <div class="card shadow-sm">
                <div class="card-body text-center text-secondary py-5">
                    目前還沒有任何修訂
                </div>
            </div>
        @endforelse

        <div class="mt-3">
            {{ $revisions->links() }}
        </div>
    </div>
@endsection
