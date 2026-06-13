@extends('layouts.app')

@section('title', $revision->title)

@section('content')
    @php
        /** @var array<int, list<string>> $actionErrorsByOrder */
        $actionErrorsByOrder = session('revision_action_errors') ?? $validationResult->actionMessages();
        $generalErrors = $validationResult->generalErrors();
        if ($errors->isNotEmpty() && session()->has('revision_error_summary')) {
            $generalErrors = $errors->all();
        }
        $isValidationValid = $validationResult->isValid();
        $submittedAt = $revision->isDraft() ? null : $revision->updated_at;
    @endphp

    <div class="container">

        {{-- Back --}}
        <a href="{{ route('admin.revisions.index') }}" class="btn btn-secondary mb-2">
            <i class="fa-solid fa-arrow-left"></i> 返回修訂審核
        </a>

        {{-- Header --}}
        <div class="d-flex align-items-center gap-2 mb-3">
            <h1 class="h3 mb-0">{{ $revision->title }}</h1>
            @include('revisions.partials.status-badge', ['status' => $revision->status])
        </div>

        {{-- Review operations --}}
        @if ($revision->isPendingReview())
            <div class="d-flex flex-wrap gap-2 mb-3">
                <form method="POST" action="{{ route('admin.revisions.approve', $revision) }}"
                      onsubmit="return confirm('確認接受並套用此修訂？此操作無法復原。')">
                    @csrf
                    <button type="submit" class="btn btn-success"
                            @disabled(! $isValidationValid)
                            title="{{ $isValidationValid ? '' : '驗證未通過，無法接受' }}">
                        <i class="fa-solid fa-check"></i> 接受並套用
                    </button>
                </form>

                <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                        data-bs-target="#rejectRevisionModal">
                    <i class="fa-solid fa-xmark"></i> 退回
                </button>

                <a href="{{ route('admin.revisions.index') }}" class="btn btn-outline-secondary">
                    返回列表
                </a>
            </div>
        @else
            <div class="mb-3">
                <a href="{{ route('admin.revisions.index') }}" class="btn btn-outline-secondary">
                    返回列表
                </a>
            </div>
        @endif

        {{-- Review summary --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold">審核摘要</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-md-2">建立者</dt>
                    <dd class="col-md-10">{{ $revision->user->name }}</dd>

                    <dt class="col-md-2">建立時間</dt>
                    <dd class="col-md-10">{{ $revision->created_at }}</dd>

                    <dt class="col-md-2">送審時間</dt>
                    <dd class="col-md-10">{{ $submittedAt ?? '—' }}</dd>

                    <dt class="col-md-2">驗證結果</dt>
                    <dd class="col-md-10">
                        @if ($isValidationValid)
                            <span class="text-success fw-semibold">驗證通過</span>
                        @else
                            <span class="text-danger fw-semibold">驗證未通過</span>
                        @endif
                        <span class="text-secondary small ms-2">（進入頁面時重新驗證）</span>
                    </dd>
                </dl>
            </div>
        </div>

        {{-- Basic info --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold">基本資料</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-md-2">標題</dt>
                    <dd class="col-md-10">{{ $revision->title }}</dd>

                    <dt class="col-md-2">描述</dt>
                    <dd class="col-md-10">{{ $revision->description ?: '—' }}</dd>

                    <dt class="col-md-2">最後更新</dt>
                    <dd class="col-md-10">{{ $revision->updated_at }}</dd>
                </dl>
            </div>
        </div>

        {{-- Validation errors --}}
        @if (! $isValidationValid)
            <div class="alert alert-danger mb-3">
                <div class="fw-semibold mb-1">
                    {{ session('revision_error_summary') ?? '驗證未通過，無法接受此修訂' }}
                </div>
                @if ($generalErrors !== [])
                    <ul class="mb-0">
                        @foreach ($generalErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mb-0">部分操作項目有問題，請查看下方標記的操作卡片。</p>
                @endif
            </div>
        @endif

        @if ($errors->has('lock'))
            <div class="alert alert-warning mb-3">
                {{ $errors->first('lock') }}
            </div>
        @endif

        @if (session('global'))
            <div class="alert alert-success mb-3">
                {{ session('global') }}
            </div>
        @endif

        <h2>操作清單</h2>

        {{-- Actions list --}}
        <div class="card mb-3">
            <div class="card-body">
                @forelse ($revision->actions as $action)
                    @include('revisions.partials.action-card', [
                        'action'       => $action,
                        'isEditable'   => false,
                        'hasError'     => isset($actionErrorsByOrder[$action->order]),
                        'actionErrors' => $actionErrorsByOrder[$action->order] ?? [],
                    ])
                @empty
                    <div class="text-secondary text-center py-4">
                        尚無任何操作
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Reviews history --}}
        <div class="card mb-3">
            <div class="card-header fw-semibold">審核紀錄</div>
            <div class="card-body p-0">
                @if ($revision->reviews->isNotEmpty())
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
                @else
                    <div class="text-secondary text-center py-4">
                        目前尚無任何審核紀錄
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($revision->isPendingReview())
        <div class="modal fade" id="rejectRevisionModal" tabindex="-1"
             aria-labelledby="rejectRevisionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.revisions.reject', $revision) }}">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="rejectRevisionModalLabel">退回修訂</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                    aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <label for="reject-comment" class="form-label">退回理由 <span class="text-danger">*</span></label>
                            <textarea id="reject-comment" name="comment" class="form-control" rows="4"
                                      required minlength="1" placeholder="請說明退回原因"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                            <button type="submit" class="btn btn-danger">確認退回</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
