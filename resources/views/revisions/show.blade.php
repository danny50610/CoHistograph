@extends('layouts.app')

@section('title', $revision->title)

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">

                {{-- Back --}}
                <a href="{{ route('revisions.index') }}" class="btn btn-secondary mb-2">
                    <i class="fa-solid fa-arrow-left"></i> 返回我的修訂
                </a>

                {{-- Header --}}
                <div class="d-flex align-items-center gap-2 mb-3">
                    <h1 class="h3 mb-0">{{ $revision->title }}</h1>
                    @include('revisions.partials.status-badge', ['status' => $revision->status])
                </div>

                {{-- Operation bar --}}
                @if ($revision->isDraft() && auth()->id() === $revision->user_id)
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a href="{{ route('revisions.edit', $revision) }}" class="btn btn-primary">
                            <i class="fa-solid fa-pen-to-square"></i> 編輯草稿
                        </a>
                        <form method="POST" action="{{ route('revisions.submit', $revision) }}"
                              onsubmit="return confirm('確認提交此修訂進行審核？提交後將無法再編輯。')">
                            @csrf
                            <button type="submit" class="btn btn-success">
                                <i class="fa-solid fa-paper-plane"></i> 提交審核
                            </button>
                        </form>
                        <form method="POST" action="{{ route('revisions.destroy', $revision) }}"
                              onsubmit="return confirm('確認刪除此修訂草稿？此操作無法復原。')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                <i class="fa-solid fa-trash"></i> 刪除草稿
                            </button>
                        </form>
                    </div>
                @elseif ($revision->isRejected() && auth()->id() === $revision->user_id)
                    <div class="mb-3">
                        <form method="POST" action="{{ route('revisions.reopen', $revision) }}"
                              onsubmit="return confirm('確認重新開啟修訂？狀態將回到草稿。')">
                            @csrf
                            <button type="submit" class="btn btn-warning">
                                <i class="fa-solid fa-rotate-left"></i> 重新開啟編輯
                            </button>
                        </form>
                    </div>
                @endif

                {{-- Basic info card --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-md-2">標題</dt>
                            <dd class="col-md-10">{{ $revision->title }}</dd>

                            <dt class="col-md-2">描述</dt>
                            <dd class="col-md-10">{{ $revision->description }}</dd>

                            <dt class="col-md-2">建立者</dt>
                            <dd class="col-md-10">{{ $revision->user->name }}</dd>

                            <dt class="col-md-2">建立時間</dt>
                            <dd class="col-md-10">{{ $revision->created_at }}</dd>

                            <dt class="col-md-2">最後更新</dt>
                            <dd class="col-md-10">{{ $revision->updated_at }}</dd>
                        </dl>
                    </div>
                </div>

                {{-- Actions list --}}
                <div class="card mb-3">
                    <div class="card-header fw-semibold">操作清單</div>
                    <div class="card-body">
                        @forelse ($revision->actions as $action)
                            @include('revisions.partials.action-card', [
                                'action'       => $action,
                                'isEditable'   => false,
                                'hasError'     => false,
                                'actionErrors' => [],
                            ])
                        @empty
                            <div class="text-secondary text-center py-4">
                                尚無任何操作
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Reviews history --}}
                @if ($revision->reviews->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header fw-semibold">審核紀錄</div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach ($revision->reviews->sortByDesc('created_at') as $review)
                                    <li class="list-group-item">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <div class="fw-semibold">
                                                {{ $review->actorUser?->name ?? '(已刪除使用者)' }}
                                                &nbsp;
                                                @if ($review->action === \App\Enums\RevisionReviewAction::Approved)
                                                    <span class="badge text-bg-success">接受</span>
                                                @else
                                                    <span class="badge text-bg-danger">退回</span>
                                                @endif
                                            </div>
                                            <div class="small text-secondary">
                                                {{ $review->created_at }}
                                            </div>
                                        </div>
                                        @if ($review->comment)
                                            <div class="small text-secondary">{{ $review->comment }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

            </div>
        </div>
    </div>

@endsection
