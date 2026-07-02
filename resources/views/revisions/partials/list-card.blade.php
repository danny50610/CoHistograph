@php
    /** @var \App\Models\Revision $revision */
    /** @var 'user-list'|'admin-list' $mode */

    $showRoute = $mode === 'admin-list'
        ? route('admin.revisions.show', $revision)
        : route('revisions.show', $revision);

    $latestReview = $revision->relationLoaded('reviews')
        ? $revision->reviews->sortByDesc('created_at')->first()
        : $revision->latestReview();
@endphp

<a href="{{ $showRoute }}"
   class="text-decoration-none text-reset d-block mb-3">
    <div class="card shadow-sm h-100">
        <div class="card-body">
            @if ($mode === 'admin-list')
                {{-- 第一行：標題 + 狀態 badge --}}
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h5 class="card-title mb-0 flex-grow-1">{{ $revision->title }}</h5>
                    @include('revisions.partials.status-badge', ['status' => $revision->status])
                </div>

                {{-- 第二行：建立者 --}}
                <div class="text-secondary small mb-1">
                    建立者：{{ $revision->user->name }}
                </div>

                {{-- 第三行：操作數量 --}}
                <div class="text-secondary small mb-1">
                    {{ $revision->actions_count }} 個操作
                </div>

                {{-- 第四行：最後更新 --}}
                <div class="text-secondary small mb-1">
                    最後更新：{{ $revision->updated_at }}
                </div>

                {{-- 第五行：最近一次審核時間 --}}
                <div class="text-secondary small mb-2">
                    最近一次審核：
                    @if ($latestReview)
                        {{ $latestReview->created_at }}
                    @else
                        —
                    @endif
                </div>

                <div class="small text-primary">
                    點擊查看審核詳情 <i class="fa-solid fa-arrow-right"></i>
                </div>
            @else
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
            @endif
        </div>
    </div>
</a>
