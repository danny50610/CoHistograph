@php
    use App\Enums\RevisionActionType;

    $actionLabels = [
        RevisionActionType::CreateVertex->value          => '新增 Vertex',
        RevisionActionType::DeleteVertex->value          => '刪除 Vertex',
        RevisionActionType::CreateEdge->value            => '新增 Edge',
        RevisionActionType::DeleteEdge->value            => '刪除 Edge',
        RevisionActionType::CreateVertexProperty->value  => '新增 Vertex 屬性',
        RevisionActionType::UpdateVertexProperty->value  => '修改 Vertex 屬性',
        RevisionActionType::DeleteVertexProperty->value  => '刪除 Vertex 屬性',
        RevisionActionType::CreateEdgeProperty->value    => '新增 Edge 屬性',
        RevisionActionType::UpdateEdgeProperty->value    => '修改 Edge 屬性',
        RevisionActionType::DeleteEdgeProperty->value    => '刪除 Edge 屬性',
    ];

    $actionTypeLabel = $actionLabels[$action->action->value] ?? $action->action->value;

    // 目標摘要
    $targetLabel = null;
    if (!is_null($action->target_ref_order)) {
        $targetLabel = '#' . ($action->target_ref_order + 1) . ' 建立的項目';
    } elseif (!is_null($action->target_age_id)) {
        $targetLabel = 'ID:' . $action->target_age_id;
    }

    $startLabel = null;
    if (!is_null($action->start_vertex_ref_order)) {
        $startLabel = '#' . ($action->start_vertex_ref_order + 1) . ' 建立的 Vertex';
    } elseif (!is_null($action->start_vertex_age_id)) {
        $startLabel = 'ID:' . $action->start_vertex_age_id;
    }

    $endLabel = null;
    if (!is_null($action->end_vertex_ref_order)) {
        $endLabel = '#' . ($action->end_vertex_ref_order + 1) . ' 建立的 Vertex';
    } elseif (!is_null($action->end_vertex_age_id)) {
        $endLabel = 'ID:' . $action->end_vertex_age_id;
    }

    $summary = match ($action->action) {
        RevisionActionType::CreateVertex         => '新增 Vertex：' . ($action->vertex_type_label ?? '—'),
        RevisionActionType::DeleteVertex         => '刪除 Vertex：' . ($targetLabel ?? '—'),
        RevisionActionType::CreateEdge           => '新增 Edge：' . ($startLabel ?? '—') . ' - ' . ($action->edge_type_label ?? '—') . ' - ' . ($endLabel ?? '—'),
        RevisionActionType::DeleteEdge           => '刪除 Edge：' . ($targetLabel ?? '—'),
        RevisionActionType::CreateVertexProperty => '新增 Vertex 屬性：' . ($targetLabel ?? '—') . '.' . ($action->age_property_name ?? '—') . ' = ' . ($action->value ?? '—'),
        RevisionActionType::UpdateVertexProperty => '修改 Vertex 屬性：' . ($targetLabel ?? '—') . '.' . ($action->age_property_name ?? '—') . ' = ' . ($action->value ?? '—'),
        RevisionActionType::DeleteVertexProperty => '刪除 Vertex 屬性：' . ($targetLabel ?? '—') . '.' . ($action->age_property_name ?? '—'),
        RevisionActionType::CreateEdgeProperty   => '新增 Edge 屬性：' . ($targetLabel ?? '—') . '.' . ($action->age_property_name ?? '—') . ' = ' . ($action->value ?? '—'),
        RevisionActionType::UpdateEdgeProperty   => '修改 Edge 屬性：' . ($targetLabel ?? '—') . '.' . ($action->age_property_name ?? '—') . ' = ' . ($action->value ?? '—'),
        RevisionActionType::DeleteEdgeProperty   => '刪除 Edge 屬性：' . ($targetLabel ?? '—') . '.' . ($action->age_property_name ?? '—'),
    };
@endphp

<div class="card mb-2 {{ $hasError ?? false ? 'border-danger' : '' }}"
     id="action-card-{{ $action->order }}">
    <div class="card-body py-2 px-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-1 mb-1">
            <span class="fw-semibold small text-secondary">
                #{{ $action->order + 1 }} &middot; {{ $actionTypeLabel }}
            </span>
            @if ($isEditable ?? false)
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1"
                            onclick="revisionMoveAction({{ $action->order }}, 'up')" title="上移">
                        <i class="fa-solid fa-arrow-up"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1"
                            onclick="revisionMoveAction({{ $action->order }}, 'down')" title="下移">
                        <i class="fa-solid fa-arrow-down"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1"
                            onclick="revisionEditAction({{ $action->order }})" title="編輯">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                            onclick="revisionDeleteAction({{ $action->order }})" title="刪除">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            @endif
        </div>
        <div class="small">{{ $summary }}</div>

        @if ($hasError ?? false)
            @foreach ($actionErrors ?? [] as $error)
                <div class="text-danger small mt-1">
                    <i class="fa-solid fa-circle-exclamation"></i> {{ $error }}
                </div>
            @endforeach
        @endif
    </div>
</div>
