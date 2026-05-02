<div class="row g-3">
    {{-- Vertex 操作 --}}
    <div class="col-12">
        <div class="fw-semibold small text-secondary mb-2">Vertex 操作</div>
        <div class="d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('create_vertex')">
                <div class="fw-semibold">新增 Vertex</div>
                <div class="small text-secondary">在指定 VertexType 下建立一個新 Vertex</div>
            </button>
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('delete_vertex')">
                <div class="fw-semibold">刪除 Vertex</div>
                <div class="small text-secondary">刪除一個既有 Vertex</div>
            </button>
        </div>
    </div>

    {{-- Edge 操作 --}}
    <div class="col-12">
        <div class="fw-semibold small text-secondary mb-2">Edge 操作</div>
        <div class="d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('create_edge')">
                <div class="fw-semibold">新增 Edge</div>
                <div class="small text-secondary">在指定 EdgeType 下新增一條 Edge</div>
            </button>
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('delete_edge')">
                <div class="fw-semibold">刪除 Edge</div>
                <div class="small text-secondary">刪除一條既有 Edge</div>
            </button>
        </div>
    </div>

    {{-- Vertex 屬性操作 --}}
    <div class="col-12">
        <div class="fw-semibold small text-secondary mb-2">Vertex 屬性操作</div>
        <div class="d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('create_vertex_property')">
                <div class="fw-semibold">新增 Vertex 屬性</div>
                <div class="small text-secondary">在既有 Vertex 上設定某個 property 值</div>
            </button>
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('update_vertex_property')">
                <div class="fw-semibold">修改 Vertex 屬性</div>
                <div class="small text-secondary">修改既有 Vertex 上的 property 值</div>
            </button>
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('delete_vertex_property')">
                <div class="fw-semibold">刪除 Vertex 屬性</div>
                <div class="small text-secondary">移除既有 Vertex 上的 property 值</div>
            </button>
        </div>
    </div>

    {{-- Edge 屬性操作 --}}
    <div class="col-12">
        <div class="fw-semibold small text-secondary mb-2">Edge 屬性操作</div>
        <div class="d-flex flex-column gap-2">
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('create_edge_property')">
                <div class="fw-semibold">新增 Edge 屬性</div>
                <div class="small text-secondary">在既有 Edge 上設定某個 property 值</div>
            </button>
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('update_edge_property')">
                <div class="fw-semibold">修改 Edge 屬性</div>
                <div class="small text-secondary">修改既有 Edge 上的 property 值</div>
            </button>
            <button type="button" class="btn btn-outline-secondary text-start"
                    onclick="revisionSelectActionType('delete_edge_property')">
                <div class="fw-semibold">刪除 Edge 屬性</div>
                <div class="small text-secondary">移除既有 Edge 上的 property 值</div>
            </button>
        </div>
    </div>
</div>
