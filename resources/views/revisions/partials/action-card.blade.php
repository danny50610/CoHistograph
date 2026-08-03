@php
    use App\Enums\RevisionActionType;
    use App\Support\EnumPropertyDiff;
    use App\Support\LocalizedPropertyLabelResolver;

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

    $propertyName = $action->age_property_name ?? '—';
    if (
        $action->age_property_name
        && isset($revisionActions, $vertexTypes, $edgeTypes)
    ) {
        $propertyName = app(LocalizedPropertyLabelResolver::class)->formatForAction(
            $action,
            $revisionActions,
            $vertexTypes,
            $edgeTypes,
        );
    }

    $enumDiff = null;
    $valueDisplay = $action->value;
    if (isset($revisionActions, $vertexTypes, $edgeTypes)) {
        $enumDiff = app(EnumPropertyDiff::class)->forAction(
            $action,
            $revisionActions,
            $vertexTypes,
            $edgeTypes,
        );
        if ($enumDiff !== null) {
            $valueDisplay = $enumDiff['value_labels'];
        } elseif (is_array($action->value)) {
            $valueDisplay = implode(', ', array_map('strval', $action->value));
        } elseif (is_bool($action->value)) {
            $valueDisplay = $action->value ? 'true' : 'false';
        }
    } elseif (is_array($action->value)) {
        $valueDisplay = implode(', ', array_map('strval', $action->value));
    } elseif (is_bool($action->value)) {
        $valueDisplay = $action->value ? 'true' : 'false';
    }

    $summary = match ($action->action) {
        RevisionActionType::CreateVertex         => '新增 Vertex：' . ($action->vertex_type_label ?? '—'),
        RevisionActionType::DeleteVertex         => '刪除 Vertex：' . ($targetLabel ?? '—'),
        RevisionActionType::CreateEdge           => '新增 Edge：' . ($startLabel ?? '—') . ' - ' . ($action->edge_type_label ?? '—') . ' - ' . ($endLabel ?? '—'),
        RevisionActionType::DeleteEdge           => '刪除 Edge：' . ($targetLabel ?? '—'),
        RevisionActionType::CreateVertexProperty => '新增 Vertex 屬性：' . ($targetLabel ?? '—') . '.' . $propertyName . ' = ' . ($valueDisplay ?? '—'),
        RevisionActionType::UpdateVertexProperty => '修改 Vertex 屬性：' . ($targetLabel ?? '—') . '.' . $propertyName . ' = ' . ($valueDisplay ?? '—'),
        RevisionActionType::DeleteVertexProperty => '刪除 Vertex 屬性：' . ($targetLabel ?? '—') . '.' . $propertyName,
        RevisionActionType::CreateEdgeProperty   => '新增 Edge 屬性：' . ($targetLabel ?? '—') . '.' . $propertyName . ' = ' . ($valueDisplay ?? '—'),
        RevisionActionType::UpdateEdgeProperty   => '修改 Edge 屬性：' . ($targetLabel ?? '—') . '.' . $propertyName . ' = ' . ($valueDisplay ?? '—'),
        RevisionActionType::DeleteEdgeProperty   => '刪除 Edge 屬性：' . ($targetLabel ?? '—') . '.' . $propertyName,
    };
@endphp

<div class="card mb-2 {{ ($hasError ?? false) ? 'border-danger' : (($hasWarning ?? false) ? 'border-warning' : '') }}"
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

        @if ($enumDiff !== null)
            <div class="small mt-2 ps-1 border-start border-2">
                <div>
                    <span class="text-body-secondary">現有：</span>{{ $enumDiff['before_labels'] }}
                    @if ($enumDiff['is_create_ref_target'])
                        <div class="text-body-secondary">此目標於本修訂新建，圖上尚無值</div>
                    @endif
                </div>
                @if ($enumDiff['added_labels'] !== '')
                    <div class="text-success">
                        <span class="text-body-secondary">新增：</span>{{ $enumDiff['added_labels'] }}
                    </div>
                @endif
                @if ($enumDiff['removed_labels'] !== '')
                    <div class="text-danger">
                        <span class="text-body-secondary">移除：</span>{{ $enumDiff['removed_labels'] }}
                    </div>
                @endif
            </div>
        @endif

        @if ($hasError ?? false)
            @foreach ($actionErrors ?? [] as $error)
                <div class="text-danger small mt-1">
                    <i class="fa-solid fa-circle-exclamation"></i> {{ $error }}
                </div>
            @endforeach
        @endif

        @if ($hasWarning ?? false)
            @foreach ($actionWarnings ?? [] as $warning)
                <div class="text-warning-emphasis small mt-1">
                    <i class="fa-solid fa-triangle-exclamation"></i> {{ $warning }}
                </div>
            @endforeach
        @endif
    </div>
</div>
