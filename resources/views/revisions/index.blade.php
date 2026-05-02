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
                        {{-- 第一行：status + title + updated_at --}}
                        <div class="d-flex align-items-center gap-2 mb-2">
                            @include('revisions.partials.status-badge', ['status' => $revision->status])
                            <h5 class="card-title mb-0 flex-grow-1">{{ $revision->title }}</h5>
                            <span class="text-secondary small text-nowrap">{{ $revision->updated_at }}</span>
                        </div>

                        {{-- 第二行：描述 --}}
                        <p class="card-text text-secondary small mb-2">
                            {{ $revision->description ?: '（無描述）' }}
                        </p>

                        {{-- 第三行：actions 數量 --}}
                        <div class="text-secondary small">
                            {{ $revision->actions_count }} 個操作
                        </div>
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
