@php
    /** @var \App\Models\Revision $revision */
    $historyEntries = $revision->reviewHistoryEntries();
    $showEmptyState = $showEmptyState ?? true;
@endphp

@if ($historyEntries !== [])
    <div class="card mb-3">
        <div class="card-header fw-semibold">審核紀錄</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                @foreach ($historyEntries as $entry)
                    <li class="list-group-item">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-semibold">
                                {{ $entry['actor_name'] ?? '(已刪除使用者)' }}
                                &nbsp;
                                @if ($entry['action'] === \App\Enums\RevisionReviewAction::Approved)
                                    <span class="badge text-bg-success">通過</span>
                                @else
                                    <span class="badge text-bg-danger">退回</span>
                                @endif
                            </div>
                            <div class="small text-secondary">
                                {{ $entry['occurred_at'] }}
                            </div>
                        </div>
                        @if ($entry['comment'])
                            <div class="small text-secondary">{{ $entry['comment'] }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@elseif ($showEmptyState)
    <div class="card mb-3">
        <div class="card-header fw-semibold">審核紀錄</div>
        <div class="card-body p-0">
            <div class="text-secondary text-center py-4">
                目前尚無任何審核紀錄
            </div>
        </div>
    </div>
@endif
