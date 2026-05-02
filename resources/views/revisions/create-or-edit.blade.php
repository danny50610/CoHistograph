@extends('layouts.app')

@php
    $isEditMode = isset($revision);
    $methodText = $isEditMode ? '編輯修訂' : '新增修訂';
@endphp

@section('title', $methodText)

@section('content')
    <div class="container">
        <a href="{{ $isEditMode ? route('revisions.show', $revision) : route('revisions.index') }}"
            class="btn btn-secondary mb-2">
            <i class="fa-solid fa-arrow-left"></i>
            {{ $isEditMode ? '返回修訂詳情' : '返回我的修訂' }}
        </a>

        <h1 class="h3 mb-3">{{ $methodText }}</h1>

        <div class="mb-3 d-flex flex-wrap gap-2">
            @if ($isEditMode)
                <button type="button" class="btn btn-primary" id="saveRevisionBtn">
                    <i class="fa-solid fa-floppy-disk"></i> 儲存變更
                </button>
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
            @endif
        </div>

        {{-- Error summary --}}
        @if ($errors->isNotEmpty())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">請修正以下錯誤後再提交：</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
                action="{{ $isEditMode ? route('revisions.update', $revision) : route('revisions.store') }}"
                id="revisionForm">
            @csrf
            @if ($isEditMode)
                @method('PUT')
            @endif

            {{-- Basic info card --}}
            <div class="card mb-3">
                <div class="card-body">
                    <x-forms.input
                        id="title"
                        label="標題"
                        :value="old('title', $revision->title ?? '')"
                        required
                    />

                    <div class="mb-3 row">
                        <label for="description" class="col-md-2 col-form-label">描述</label>
                        <div class="col-md-10">
                            <textarea id="description" name="description"
                                        class="form-control @error('description') is-invalid @enderror"
                                        rows="3"
                                        placeholder="選填">{{ old('description', $revision->description ?? '') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @if(!$isEditMode)
                    <div class="row mb-3">
                        <div class="col-md-10 ms-auto d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-floppy-disk"></i> 建立草稿
                            </button>
                            <a href="{{ route('revisions.index') }}" class="btn btn-secondary">取消</a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            @if ($isEditMode)
                <h2>操作清單</h2>

                <button type="button" class="btn btn-outline-primary mb-3"
                        onclick="revisionAddAction()">
                    <i class="fa-solid fa-plus"></i> 新增操作
                </button>

                {{-- Actions list card --}}
                <div class="card mb-3">
                    <div class="card-body" id="actionsContainer">
                        <div id="actionsHiddenInputs"></div>
                        <div class="text-secondary text-center py-4" id="actionsEmptyState"
                            @if ($revision->actions->isNotEmpty()) style="display:none" @endif>
                            尚無任何操作，點擊右上方「新增操作」開始
                        </div>
                    </div>
                </div>
            @endif
        </form>{{-- end revisionForm --}}
    </div>

    {{-- Action Modal (edit mode only) --}}
    @if ($isEditMode)
        <div class="modal fade" id="actionModal" tabindex="-1" aria-labelledby="actionModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="actionModalLabel">操作</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="actionModalBody">
                        {{-- Content swapped by JS --}}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="button" class="btn btn-primary" id="actionModalConfirmBtn">確認</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@if ($isEditMode)
@php
    $jsActions = $revision->actions->map(fn ($a) => [
        'order'                  => $a->order,
        'action'                 => $a->action->value,
        'target_age_id'          => $a->target_age_id,
        'target_ref_order'       => $a->target_ref_order,
        'vertex_type_label'      => $a->vertex_type_label,
        'edge_type_label'        => $a->edge_type_label,
        'start_vertex_age_id'    => $a->start_vertex_age_id,
        'start_vertex_ref_order' => $a->start_vertex_ref_order,
        'end_vertex_age_id'      => $a->end_vertex_age_id,
        'end_vertex_ref_order'   => $a->end_vertex_ref_order,
        'age_property_name'      => $a->age_property_name,
        'value'                  => $a->value,
    ])->values();

    $vertexTypesWithProps = $vertexTypes->load('properties');
    $edgeTypesWithProps   = $edgeTypes->load('properties');
    $allActions           = $revision->actions;

    $jsTypeSelectHtml = view('revisions.partials.modal-action-type-select')->render();

    $jsVertexFormHtmls = [
        'create_vertex' => view('revisions.partials.modal-vertex-form', ['action' => null, 'vertexTypes' => $vertexTypes, 'actionType' => 'create_vertex', 'allActions' => $allActions])->render(),
        'delete_vertex' => view('revisions.partials.modal-vertex-form', ['action' => null, 'vertexTypes' => $vertexTypes, 'actionType' => 'delete_vertex', 'allActions' => $allActions])->render(),
    ];

    $jsEdgeFormHtmls = [
        'create_edge' => view('revisions.partials.modal-edge-form', ['action' => null, 'edgeTypes' => $edgeTypes, 'actionType' => 'create_edge', 'allActions' => $allActions])->render(),
        'delete_edge' => view('revisions.partials.modal-edge-form', ['action' => null, 'edgeTypes' => $edgeTypes, 'actionType' => 'delete_edge', 'allActions' => $allActions])->render(),
    ];

    $jsVpFormHtmls = [
        'create_vertex_property' => view('revisions.partials.modal-vertex-property-form', ['action' => null, 'vertexTypes' => $vertexTypesWithProps, 'actionType' => 'create_vertex_property', 'allActions' => $allActions])->render(),
        'update_vertex_property' => view('revisions.partials.modal-vertex-property-form', ['action' => null, 'vertexTypes' => $vertexTypesWithProps, 'actionType' => 'update_vertex_property', 'allActions' => $allActions])->render(),
        'delete_vertex_property' => view('revisions.partials.modal-vertex-property-form', ['action' => null, 'vertexTypes' => $vertexTypesWithProps, 'actionType' => 'delete_vertex_property', 'allActions' => $allActions])->render(),
    ];

    $jsEpFormHtmls = [
        'create_edge_property' => view('revisions.partials.modal-edge-property-form', ['action' => null, 'edgeTypes' => $edgeTypesWithProps, 'actionType' => 'create_edge_property', 'allActions' => $allActions])->render(),
        'update_edge_property' => view('revisions.partials.modal-edge-property-form', ['action' => null, 'edgeTypes' => $edgeTypesWithProps, 'actionType' => 'update_edge_property', 'allActions' => $allActions])->render(),
        'delete_edge_property' => view('revisions.partials.modal-edge-property-form', ['action' => null, 'edgeTypes' => $edgeTypesWithProps, 'actionType' => 'delete_edge_property', 'allActions' => $allActions])->render(),
    ];
@endphp
@push('js')
<script>
(function () {
    'use strict';

    let actions = @json($jsActions);

    const typeSelectHtml  = @json($jsTypeSelectHtml);
    const vertexFormHtmls = @json($jsVertexFormHtmls);
    const edgeFormHtmls   = @json($jsEdgeFormHtmls);
    const vpFormHtmls     = @json($jsVpFormHtmls);
    const epFormHtmls     = @json($jsEpFormHtmls);

    const actionLabels = {
        create_vertex:          '新增 Vertex',
        delete_vertex:          '刪除 Vertex',
        create_edge:            '新增 Edge',
        delete_edge:            '刪除 Edge',
        create_vertex_property: '新增 Vertex 屬性',
        update_vertex_property: '修改 Vertex 屬性',
        delete_vertex_property: '刪除 Vertex 屬性',
        create_edge_property:   '新增 Edge 屬性',
        update_edge_property:   '修改 Edge 屬性',
        delete_edge_property:   '刪除 Edge 屬性',
    };

    // ----------------------------------------------------------------
    // Modal helpers
    // ----------------------------------------------------------------
    let modalInstance = null;
    let modalMode     = null;
    let editingOrder  = null;
    let currentType   = null;

    function getModal() {
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(document.getElementById('actionModal'));
        }
        return modalInstance;
    }

    function setModalTitle(title) {
        document.getElementById('actionModalLabel').textContent = title;
    }

    function setModalBody(html) {
        document.getElementById('actionModalBody').innerHTML = html;
    }

    // ----------------------------------------------------------------
    // Step 1 — type selection
    // ----------------------------------------------------------------
    window.revisionAddAction = function () {
        modalMode    = 'add';
        editingOrder = null;
        currentType  = null;
        setModalTitle('新增操作 — 選擇類型');
        setModalBody(typeSelectHtml);
        document.getElementById('actionModalConfirmBtn').style.display = 'none';
        getModal().show();
    };

    window.revisionSelectActionType = function (type) {
        currentType = type;
        setModalTitle((modalMode === 'edit' ? '編輯操作：' : '新增操作：') + (actionLabels[type] ?? type));
        setModalBody(getFormHtml(type));
        document.getElementById('actionModalConfirmBtn').style.display = '';
        document.getElementById('actionModalConfirmBtn').onclick = modalMode === 'edit'
            ? confirmEditAction
            : confirmAddAction;
    };

    function getFormHtml(type) {
        if (type in vertexFormHtmls) { return vertexFormHtmls[type]; }
        if (type in edgeFormHtmls)   { return edgeFormHtmls[type]; }
        if (type in vpFormHtmls)     { return vpFormHtmls[type]; }
        if (type in epFormHtmls)     { return epFormHtmls[type]; }
        return '<div class="text-danger">未知的操作類型</div>';
    }

    // ----------------------------------------------------------------
    // Step 2 — collect form values
    // ----------------------------------------------------------------
    function collectModalFormData() {
        const body      = document.getElementById('actionModalBody');
        const getValue  = (name) => body.querySelector(`[name="${name}"]`)?.value?.trim() || null;
        const getIntVal = (name) => { const v = getValue(name); return v ? parseInt(v, 10) : null; };

        return {
            action:                 currentType,
            vertex_type_label:      getValue('vertex_type_label'),
            edge_type_label:        getValue('edge_type_label'),
            target_age_id:          getIntVal('target_age_id'),
            target_ref_order:       getIntVal('target_ref_order'),
            start_vertex_age_id:    getIntVal('start_vertex_age_id'),
            start_vertex_ref_order: getIntVal('start_vertex_ref_order'),
            end_vertex_age_id:      getIntVal('end_vertex_age_id'),
            end_vertex_ref_order:   getIntVal('end_vertex_ref_order'),
            age_property_name:      getValue('age_property_name'),
            value:                  getValue('value'),
        };
    }

    // ----------------------------------------------------------------
    // Add / Edit / Delete / Move
    // ----------------------------------------------------------------
    function confirmAddAction() {
        const data = collectModalFormData();
        data.order = actions.length + 1;
        actions.push(data);
        rebuildActionCards();
        getModal().hide();
    }

    window.revisionEditAction = function (order) {
        const idx = actions.findIndex(a => a.order === order);
        if (idx === -1) { return; }
        modalMode    = 'edit';
        editingOrder = order;
        currentType  = actions[idx].action;
        setModalTitle('編輯操作：' + (actionLabels[currentType] ?? currentType));
        setModalBody(typeSelectHtml);
        document.getElementById('actionModalConfirmBtn').style.display = 'none';
        getModal().show();
        revisionSelectActionType(currentType);
        setTimeout(() => prefillEditForm(actions[idx]), 50);
    };

    function prefillEditForm(action) {
        const body   = document.getElementById('actionModalBody');
        const setVal = (name, val) => {
            const el = body.querySelector(`[name="${name}"]`);
            if (el && val !== null && val !== undefined) { el.value = String(val); }
        };
        setVal('vertex_type_label',      action.vertex_type_label);
        setVal('edge_type_label',        action.edge_type_label);
        setVal('target_age_id',          action.target_age_id);
        setVal('target_ref_order',       action.target_ref_order);
        setVal('start_vertex_age_id',    action.start_vertex_age_id);
        setVal('start_vertex_ref_order', action.start_vertex_ref_order);
        setVal('end_vertex_age_id',      action.end_vertex_age_id);
        setVal('end_vertex_ref_order',   action.end_vertex_ref_order);
        setVal('age_property_name',      action.age_property_name);
        setVal('value',                  action.value);
    }

    function confirmEditAction() {
        const data = collectModalFormData();
        const idx  = actions.findIndex(a => a.order === editingOrder);
        if (idx !== -1) {
            data.order   = editingOrder;
            actions[idx] = data;
        }
        rebuildActionCards();
        getModal().hide();
    }

    window.revisionDeleteAction = function (order) {
        if (!confirm('確認刪除此操作？')) { return; }
        actions = actions.filter(a => a.order !== order);
        renumberActions();
        rebuildActionCards();
    };

    window.revisionMoveAction = function (order, direction) {
        const idx     = actions.findIndex(a => a.order === order);
        if (idx === -1) { return; }
        const swapIdx = direction === 'up' ? idx - 1 : idx + 1;
        if (swapIdx < 0 || swapIdx >= actions.length) { return; }
        [actions[idx], actions[swapIdx]] = [actions[swapIdx], actions[idx]];
        renumberActions();
        rebuildActionCards();
    };

    function renumberActions() {
        actions.forEach((a, i) => { a.order = i + 1; });
    }

    // ----------------------------------------------------------------
    // Rebuild cards
    // ----------------------------------------------------------------
    function actionSummary(a) {
        const t            = (label) => label ?? '—';
        const refOrId      = (ref, id) => ref ? `#${ref} 建立的項目` : (id ? `ID:${id}` : '—');
        const vertexRefOrId = (ref, id) => ref ? `#${ref} 建立的 Vertex` : (id ? `ID:${id}` : '—');

        switch (a.action) {
            case 'create_vertex':          return `新增 Vertex：${t(a.vertex_type_label)}`;
            case 'delete_vertex':          return `刪除 Vertex：${refOrId(a.target_ref_order, a.target_age_id)}`;
            case 'create_edge':            return `新增 Edge：${vertexRefOrId(a.start_vertex_ref_order, a.start_vertex_age_id)} - ${t(a.edge_type_label)} - ${vertexRefOrId(a.end_vertex_ref_order, a.end_vertex_age_id)}`;
            case 'delete_edge':            return `刪除 Edge：${refOrId(a.target_ref_order, a.target_age_id)}`;
            case 'create_vertex_property': return `新增 Vertex 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
            case 'update_vertex_property': return `修改 Vertex 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
            case 'delete_vertex_property': return `刪除 Vertex 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)}`;
            case 'create_edge_property':   return `新增 Edge 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
            case 'update_edge_property':   return `修改 Edge 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)} = ${t(a.value)}`;
            case 'delete_edge_property':   return `刪除 Edge 屬性：${refOrId(a.target_ref_order, a.target_age_id)}.${t(a.age_property_name)}`;
            default:                       return a.action;
        }
    }

    function rebuildActionCards() {
        const container  = document.getElementById('actionsContainer');
        const emptyState = document.getElementById('actionsEmptyState');

        container.querySelectorAll('.action-dynamic-card').forEach(el => el.remove());

        if (actions.length === 0) {
            if (emptyState) { emptyState.style.display = ''; }
        } else {
            if (emptyState) { emptyState.style.display = 'none'; }
            actions.forEach(a => {
                const div       = document.createElement('div');
                div.className   = 'card mb-2 action-dynamic-card';
                div.id          = `action-card-${a.order}`;
                div.innerHTML   = `
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-1 mb-1">
                            <span class="fw-semibold small text-secondary">
                                #${a.order} &middot; ${actionLabels[a.action] ?? a.action}
                            </span>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1"
                                        onclick="revisionMoveAction(${a.order}, 'up')" title="上移">
                                    <i class="fa-solid fa-arrow-up"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1"
                                        onclick="revisionMoveAction(${a.order}, 'down')" title="下移">
                                    <i class="fa-solid fa-arrow-down"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary py-0 px-1"
                                        onclick="revisionEditAction(${a.order})" title="編輯">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1"
                                        onclick="revisionDeleteAction(${a.order})" title="刪除">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="small">${actionSummary(a)}</div>
                    </div>`;
                container.appendChild(div);
            });
        }

        buildHiddenInputs();
    }

    // ----------------------------------------------------------------
    // Hidden inputs — injected into form before submit
    // ----------------------------------------------------------------
    function buildHiddenInputs() {
        const container = document.getElementById('actionsHiddenInputs');
        container.innerHTML = '';

        const fields = [
            'action', 'target_age_id', 'target_ref_order',
            'vertex_type_label', 'edge_type_label',
            'start_vertex_age_id', 'start_vertex_ref_order',
            'end_vertex_age_id', 'end_vertex_ref_order',
            'age_property_name', 'value',
        ];

        actions.forEach((a, i) => {
            fields.forEach(field => {
                if (a[field] !== null && a[field] !== undefined && a[field] !== '') {
                    const input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = `actions[${i}][${field}]`;
                    input.value = String(a[field]);
                    container.appendChild(input);
                }
            });
        });
    }

    // ----------------------------------------------------------------
    // Save button
    // ----------------------------------------------------------------
    document.getElementById('saveRevisionBtn')?.addEventListener('click', function () {
        buildHiddenInputs();
        document.getElementById('revisionForm').submit();
    });

    rebuildActionCards();

})();
</script>
@endpush
@endif
