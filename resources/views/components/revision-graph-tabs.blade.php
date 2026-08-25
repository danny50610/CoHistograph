@props(['graphPreview'])

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <button
            class="nav-link active"
            id="revision-actions-tab"
            data-bs-toggle="tab"
            data-bs-target="#revision-actions-pane"
            type="button"
            role="tab"
            aria-controls="revision-actions-pane"
            aria-selected="true"
        >
            操作清單
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            class="nav-link"
            id="revision-graph-tab"
            data-bs-toggle="tab"
            data-bs-target="#revision-graph-pane"
            type="button"
            role="tab"
            aria-controls="revision-graph-pane"
            aria-selected="false"
        >
            視覺化
        </button>
    </li>
</ul>

<div class="tab-content" data-revision-graph-root>
    <div
        class="tab-pane fade show active"
        id="revision-actions-pane"
        role="tabpanel"
        aria-labelledby="revision-actions-tab"
        tabindex="0"
    >
        {{ $slot }}
    </div>
    <div
        class="tab-pane fade"
        id="revision-graph-pane"
        role="tabpanel"
        aria-labelledby="revision-graph-tab"
        tabindex="0"
    >
        <script type="application/json" data-revision-graph-json>@json($graphPreview)</script>
        <div data-revision-graph-mount></div>
    </div>
</div>
